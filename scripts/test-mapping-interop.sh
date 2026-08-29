#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
NODE_BIN=${NODE_BIN:-node}
PHP_BIN=${PHP_BIN:-php}
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

PACKAGE="$ROOT/sitepack-spec/examples/semantic-mapping"
CAPS="$ROOT/sitepack-spec/mapping-fixtures/capabilities"
RECIPES="$ROOT/sitepack-spec/mapping-fixtures/recipes"
NODE_COMPAT="$ROOT/sitepack-tools-node/src/compatibility-cli.js"
NODE_VALIDATE="$ROOT/sitepack-tools-node/src/cli.js"
PHP_COMPAT="$ROOT/sitepack-tools-php/bin/sitepack-compatibility"
PHP_VALIDATE="$ROOT/sitepack-tools-php/bin/sitepack-validate"
package_tree_digest() {
  find "$PACKAGE" -type f -exec shasum -a 256 {} + | sort | shasum -a 256 | awk '{print $1}'
}
PACKAGE_DIGEST_BEFORE=$(package_tree_digest)

run_pair() {
  local name=$1 capabilities=$2 recipe=$3 expected_exit=$4 expected_status=$5
  set +e
  "$NODE_BIN" "$NODE_COMPAT" "$PACKAGE" --capabilities "$capabilities" --recipe "$recipe" --format json > "$TMP/node-$name.json"
  local node_exit=$?
  "$PHP_BIN" "$PHP_COMPAT" "$PACKAGE" --capabilities "$capabilities" --recipe "$recipe" --format json > "$TMP/php-$name.json"
  local php_exit=$?
  set -e
  test "$node_exit" -eq "$expected_exit"
  test "$php_exit" -eq "$expected_exit"
  test "$(jq -r '.report.status' "$TMP/node-$name.json")" = "$expected_status"
  diff -u <(jq -S -c . "$TMP/node-$name.json") <(jq -S -c . "$TMP/php-$name.json")
}

run_pair exact "$CAPS/exact.json" "$RECIPES/exact.json" 0 compatible
run_pair transformed "$CAPS/exact.json" "$RECIPES/transformed.json" 0 compatible
run_pair fallback "$CAPS/fallback.json" "$RECIPES/fallback.json" 0 partial
run_pair blocked "$CAPS/blocked.json" "$RECIPES/exact.json" 1 incompatible
run_pair bitrix-adapter "$CAPS/bitrix-demo.json" "$RECIPES/portable-identity.json" 0 compatible
run_pair larena-adapter "$CAPS/larena-demo.json" "$RECIPES/portable-identity.json" 0 compatible
run_pair wordpress-adapter "$CAPS/wordpress-demo.json" "$RECIPES/portable-identity.json" 0 partial

jq '.source.digest = "sha256:0000000000000000000000000000000000000000000000000000000000000000"' \
  "$RECIPES/exact.json" > "$TMP/profile-digest-mismatch.json"
run_pair profile-digest-mismatch "$CAPS/exact.json" "$TMP/profile-digest-mismatch.json" 1 incompatible

"$NODE_BIN" "$NODE_VALIDATE" mapping-recipe "$RECIPES/unknown-optional.json" --format json > "$TMP/node-optional.json"
"$PHP_BIN" "$PHP_VALIDATE" mapping-recipe "$RECIPES/unknown-optional.json" --format json > "$TMP/php-optional.json"
test "$(jq -r '.messages[0].level' "$TMP/node-optional.json")" = warning
test "$(jq -r '.messages[0].level' "$TMP/php-optional.json")" = warning

set +e
"$NODE_BIN" "$NODE_VALIDATE" mapping-recipe "$RECIPES/unknown-required.json" --format json >/dev/null
node_required=$?
"$PHP_BIN" "$PHP_VALIDATE" mapping-recipe "$RECIPES/unknown-required.json" --format json >/dev/null
php_required=$?
"$NODE_BIN" "$NODE_VALIDATE" mapping-recipe "$RECIPES/executable-invalid.json" --format json >/dev/null
node_executable=$?
"$PHP_BIN" "$PHP_VALIDATE" mapping-recipe "$RECIPES/executable-invalid.json" --format json >/dev/null
php_executable=$?
set -e
test "$node_required" -eq 1
test "$php_required" -eq 1
test "$node_executable" -eq 1
test "$php_executable" -eq 1

jq '.version = "1.0.1"' "$RECIPES/exact.json" > "$TMP/changed-recipe.json"
"$NODE_BIN" "$NODE_COMPAT" "$PACKAGE" --capabilities "$CAPS/exact.json" --recipe "$TMP/changed-recipe.json" --format json > "$TMP/changed.json"
test "$(jq -r '.plan.digest' "$TMP/changed.json")" != "$(jq -r '.plan.digest' "$TMP/node-exact.json")"

jq '.tool.version = "1.0.1"' "$CAPS/exact.json" > "$TMP/changed-capabilities.json"
"$NODE_BIN" "$NODE_COMPAT" "$PACKAGE" --capabilities "$TMP/changed-capabilities.json" --recipe "$RECIPES/exact.json" --format json > "$TMP/changed-capabilities-result.json"
test "$(jq -r '.plan.digest' "$TMP/changed-capabilities-result.json")" != "$(jq -r '.plan.digest' "$TMP/node-exact.json")"

update_catalog_artifact() {
  local package_root=$1 artifact_id=$2 artifact_path=$3
  local size digest
  size=$(wc -c < "$package_root/$artifact_path" | tr -d ' ')
  digest="sha256:$(shasum -a 256 "$package_root/$artifact_path" | awk '{print $1}')"
  jq --arg id "$artifact_id" --arg digest "$digest" --argjson size "$size" \
    '(.artifacts[] | select(.id == $id)) |= (.digest = $digest | .size = $size)' \
    "$package_root/sitepack.catalog.json" > "$package_root/sitepack.catalog.json.tmp"
  mv "$package_root/sitepack.catalog.json.tmp" "$package_root/sitepack.catalog.json"
}

cp -R "$PACKAGE" "$TMP/changed-profile-package"
jq '.title = "Website core mapping test"' "$TMP/changed-profile-package/artifacts/mapping/profile.json" \
  > "$TMP/changed-profile-package/artifacts/mapping/profile.json.tmp"
mv "$TMP/changed-profile-package/artifacts/mapping/profile.json.tmp" "$TMP/changed-profile-package/artifacts/mapping/profile.json"
update_catalog_artifact "$TMP/changed-profile-package" portable.profile artifacts/mapping/profile.json
"$NODE_BIN" "$NODE_COMPAT" "$TMP/changed-profile-package" --capabilities "$CAPS/exact.json" --recipe "$RECIPES/exact.json" --format json > "$TMP/changed-profile-result.json"
test "$(jq -r '.plan.digest' "$TMP/changed-profile-result.json")" != "$(jq -r '.plan.digest' "$TMP/node-exact.json")"

cp -R "$PACKAGE" "$TMP/changed-artifact-package"
jq '.note = "Raw snapshot changed"' "$TMP/changed-artifact-package/artifacts/extensions/raw.json" \
  > "$TMP/changed-artifact-package/artifacts/extensions/raw.json.tmp"
mv "$TMP/changed-artifact-package/artifacts/extensions/raw.json.tmp" "$TMP/changed-artifact-package/artifacts/extensions/raw.json"
update_catalog_artifact "$TMP/changed-artifact-package" source.raw artifacts/extensions/raw.json
"$NODE_BIN" "$NODE_COMPAT" "$TMP/changed-artifact-package" --capabilities "$CAPS/exact.json" --recipe "$RECIPES/exact.json" --format json > "$TMP/changed-artifact-result.json"
test "$(jq -r '.plan.digest' "$TMP/changed-artifact-result.json")" != "$(jq -r '.plan.digest' "$TMP/node-exact.json")"
test "$(package_tree_digest)" = "$PACKAGE_DIGEST_BEFORE"

echo 'Mapping interoperability: 18 checks passed.'

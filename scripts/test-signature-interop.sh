#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
NODE=${NODE:-node}
PHP=${PHP:-php}
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

printf 'portable-sitepack-signature\n' > "$TMP/payload.sitepack"
cat > "$TMP/sitepack.volumes.json" <<'JSON'
{
  "spec": {"name": "sitepack", "version": "1.0.0"},
  "kind": "volume-set",
  "packageId": "signature-interop",
  "container": "zip",
  "maxPartSize": 104857600,
  "volumes": [
    {
      "index": 1,
      "count": 1,
      "file": "example.part-0001-of-0001.sitepack",
      "size": 1,
      "sha256": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
    }
  ]
}
JSON

$PHP -r '
  $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
  $pair = sodium_crypto_sign_seed_keypair($seed);
  file_put_contents($argv[1], base64_encode($seed));
  file_put_contents($argv[2], json_encode(["keys" => ["interop" => [
    "algorithm" => "Ed25519",
    "publicKey" => base64_encode(sodium_crypto_sign_publickey($pair)),
  ]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' "$TMP/seed" "$TMP/trust.json"

(
  cd "$ROOT/sitepack-tools-node"
  $NODE src/cli.js sign "$TMP/payload.sitepack" --key-id interop --private-key "$TMP/seed" --output "$TMP/node.sig.json" >/dev/null
)
(
  cd "$ROOT/sitepack-tools-php"
  $PHP bin/sitepack-validate signature "$TMP/payload.sitepack" "$TMP/node.sig.json" --trust-store="$TMP/trust.json" >/dev/null
  $PHP bin/sitepack-validate sign "$TMP/payload.sitepack" --key-id=interop --private-key="$TMP/seed" --output="$TMP/php.sig.json" >/dev/null
)
(
  cd "$ROOT/sitepack-tools-node"
  $NODE src/cli.js signature "$TMP/payload.sitepack" "$TMP/php.sig.json" --trust-store "$TMP/trust.json" >/dev/null
)

printf 'tampered\n' >> "$TMP/payload.sitepack"
if (
  cd "$ROOT/sitepack-tools-node"
  $NODE src/cli.js signature "$TMP/payload.sitepack" "$TMP/php.sig.json" --trust-store "$TMP/trust.json" >/dev/null 2>&1
); then
  echo 'Tampered payload unexpectedly verified.' >&2
  exit 1
fi

(
  cd "$ROOT/sitepack-tools-node"
  $NODE src/cli.js sign "$TMP/sitepack.volumes.json" --key-id interop --private-key "$TMP/seed" >/dev/null
)
test -f "$TMP/sitepack.volumes.json.sig.json"
(
  cd "$ROOT/sitepack-tools-php"
  $PHP bin/sitepack-validate signature "$TMP/sitepack.volumes.json" "$TMP/sitepack.volumes.json.sig.json" --trust-store="$TMP/trust.json" >/dev/null
)

printf '\n' >> "$TMP/sitepack.volumes.json"
if (
  cd "$ROOT/sitepack-tools-php"
  $PHP bin/sitepack-validate signature "$TMP/sitepack.volumes.json" "$TMP/sitepack.volumes.json.sig.json" --trust-store="$TMP/trust.json" >/dev/null 2>&1
); then
  echo 'Tampered Volume Set descriptor unexpectedly verified.' >&2
  exit 1
fi

echo 'Signature interoperability: 5 checks passed.'

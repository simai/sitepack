# SitePack 1.0 Audit

## Findings

1. The pre-1.0 asset index models only package-local files and chunks. Generic external entity links do not preserve file size, digest, version, provider identity, or enrichment status.
2. The same schemas are copied into PHP and Node tools. Release automation must retain the existing schema parity gate.
3. Profile aliases create more than one public vocabulary for the same shapes. SitePack 1.0 uses canonical names only.
4. Blob validation assumes every asset exists inside the package. External assets require semantic validation without package-local file access.
5. The core has no portable enrichment report or explicit metadata contract for later target recipes.

## Target architecture

- One SitePack 1.0 schema set shared byte-for-byte by specification and tools.
- Three asset variants: embedded file, embedded chunks, external locator.
- Compact and full snapshot profiles.
- Enrichment creates a new package and a machine-readable report.
- Core stays platform-neutral; provider credentials and portal rules remain outside packages.
- Canonical entity metadata and namespaced extensions preserve future migration inputs without promising design parity.

## Removed pre-1 surface

- Profile aliases such as `content+assets`, `snapshot`, and `full+code`.
- Asset entries without an explicit `storage` value.
- Claims that alternate package blob layouts are a compatibility requirement.
- Tool and documentation references to SitePack 0.x.

## Readiness criteria

- PHP and Node validators accept valid 1.0 fixtures and reject invalid fixtures.
- External locators containing secret-like URL material are rejected semantically.
- Blob checks skip external resolution unless a provider is supplied by a host implementation.
- Mixed, chunked, compact, full, and enrichment fixtures are covered.
- All copied schema directories are identical.

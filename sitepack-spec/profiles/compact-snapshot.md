# Profile: `compact-snapshot`

Purpose: preserve a portable site snapshot while keeping large files in an external storage provider.

- MUST include at least one site-map, entity-graph, or asset-index artifact.
- Asset-index entries MAY use `storage=external`.
- External entries MUST use stable provider locators and MUST NOT contain credentials, cookies, bearer tokens, or temporary signed URLs.
- `sourceUrl`, when present, is informational only and MUST NOT be used by a generic importer to retrieve the asset.
- The package MUST retain enough relations and source metadata to inspect external dependencies without resolving them.
- Validators MUST report unavailable, changed, and unsupported external assets explicitly.

This profile is suitable for compact backups. It is not self-contained until enrichment succeeds.

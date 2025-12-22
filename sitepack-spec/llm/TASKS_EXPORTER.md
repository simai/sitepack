# Task: SitePack Exporter

## Goal
Generate an unpacked SitePack package and (optionally) pack it into `.sitepack` (ZIP).

## Input
Any site data model (content, assets, config, tables). The exporter decides which profiles are applicable.

## Requirements
- Create `sitepack.manifest.json` and `sitepack.catalog.json` at the root.
- Fill `manifest.spec` and `manifest.package.id`.
- `manifest.createdAt` — UTC date-time (ISO 8601).
- `manifest.profiles` — selected profiles.
- `manifest.artifacts` — list of artifact IDs.
- `catalog.artifacts[]` — full list of artifacts with `id`, `mediaType`, `path`, `size`, and (preferably) `digest`.
- `size` — byte length of the file.
- `digest` — `sha256:<hex>`.

## NDJSON
- One JSON object per line.
- No extra spaces and no empty lines.

## Profiles (recommendations)
- `config-only`: config only.
- `content-only`: entities without assets.
- `content+assets`: entities + asset index.
- `full`: content + assets + config + recordsets.
- `full+code`: `full` + code/software manifest.

## Output
- Unpacked package directory.
- Optional: ZIP file with `.sitepack` extension.
- `reports/export.json` summary:
```json
{
  "packageId": "...",
  "profiles": ["..."],
  "artifacts": 0,
  "files": 0,
  "bytes": 0,
  "digests": "sha256"
}
```

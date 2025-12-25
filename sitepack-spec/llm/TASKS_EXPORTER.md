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
- `relations` MUST be emitted as an object mapping relation keys to `Link[]`.
- A Link MUST be either a string or an object with `ref` and optional `meta`.
- If producing a split distribution, generate `sitepack.volumes.json` with `spec.version = 0.4.0`, `kind = volume-set`, and a `volumes[]` list.
- `maxPartSize` SHOULD be 104857600 bytes (100 MiB) unless the user specifies otherwise.

## NDJSON
- One JSON object per line.
- No extra spaces and no empty lines.

## Chunked assets
- If an asset is chunked, emit `chunks[]` with `index`, `size`, `sha256`, and `path` for each chunk.
- For chunked assets, omit `path` and include `chunks` at the asset level.
- Compute and store both per-chunk sha256 and the overall asset sha256/size.

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

# Task: SitePack Validator

## Goal
Implement a validator for an unpacked SitePack package and a validator for the encrypted envelope header.

## Input
1) Path to the unpacked package (directory).
2) Optional: path to `*.sitepack.enc.json`.

## Package checks
- The root MUST contain `sitepack.manifest.json` and `sitepack.catalog.json`.
- JSON must be valid.
- Validate `sitepack.manifest.json` against `schemas/manifest.schema.json`.
- Validate `sitepack.catalog.json` against `schemas/catalog.schema.json`.
- All `manifest.artifacts` MUST be present in the catalog.
- For each artifact in the catalog:
  - file at `path` exists;
  - `size` matches actual byte size;
  - if `digest` is present, it matches file `sha256`.
- NDJSON artifacts: each line must be a valid JSON object.
- For known media types, validate each record against the matching schema:
  - entity-graph -> `schemas/entity.schema.json`
  - asset-index -> `schemas/asset-index.schema.json`
  - config-kv -> `schemas/config-kv.schema.json`
  - recordset -> `schemas/recordset.schema.json`
- Enforce relations encoding for entities: `relations` MUST be an object mapping keys to `Link[]`, where Link is a string or an object with `ref` and optional `meta`.
- Unknown `mediaType` MUST be skipped but MUST be logged.

## Envelope checks
If a path to `*.sitepack.enc.json` is provided:
- JSON must be valid.
- Validate against `schemas/envelope.schema.json`.
- Check that `payload.payloadDigest` exists and matches `sha256:<hex>`.

## Report
Write `reports/validate.json` next to the package. Minimum structure:
```json
{
  "ok": true,
  "errors": [],
  "warnings": [],
  "summary": {
    "artifactsTotal": 0,
    "artifactsValidated": 0,
    "unknownMediaTypes": 0,
    "ndjsonLines": 0
  }
}
```
- `ok=false` if there are errors.
- Use machine-readable codes and human-readable messages in `errors` and `warnings`.

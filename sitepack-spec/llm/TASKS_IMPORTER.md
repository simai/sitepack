# Task: SitePack Importer

## Goal
Import data from an unpacked SitePack package into the target system.

## Input
Path to the unpacked package (directory).

## Requirements
- Read `sitepack.manifest.json` and `sitepack.catalog.json`.
- Determine profile(s) from `manifest.profiles`.
- Process artifacts from the catalog:
  - For known `mediaType`, perform import.
  - Unknown `mediaType` MUST be skipped and logged.
- Unknown `entity.type` MUST NOT crash; MAY import as opaque.
- Respect `applyPolicy` for config (e.g., `never` is not auto-applied).
- If a `Transform Plan` exists and is supported, MAY apply steps in order.
- Enforce path safety (no traversal, no absolute, no null bytes).

## Relations
- Importers SHOULD use a two-pass strategy:
  1) create entities (or placeholders) and build a mapping `sitepackEntityId -> targetSystemId`,
  2) resolve and apply relations using the mapping.
- If a Link cannot be resolved, it MUST be recorded as a warning and MUST NOT be fatal.

## Output
Create `reports/import.json` with at least this structure:
```json
{
  "packageId": "...",
  "profiles": ["..."],
  "imported": {
    "entities": 0,
    "assets": 0,
    "config": 0,
    "recordsets": 0
  },
  "skipped": {
    "unknownMediaTypes": 0,
    "unknownEntityTypes": 0
  },
  "warnings": [],
  "errors": []
}
```

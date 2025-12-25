# sitepack-tools-node

CLI validator for unpacked SitePack v0.4.0 packages. It validates manifest, catalog, artifacts, and content against schemas, and writes `reports/validate.json`.

## Requirements
- Node.js >= 18
- npm

Note: the validator uses Ajv 2020-12 (`ajv/dist/2020`) because SitePack schemas are draft 2020-12.

## Installation
Local usage:
```
npm i
npm link
```

## Usage
Validate an unpacked package:
```
sitepack-validate /path/to/unpacked/sitepack
```

Validate with a profile:
```
sitepack-validate /path --profile content+assets
```

Validate an encrypted envelope header:
```
sitepack-validate envelope /path/to/example.sitepack.enc.json --check-payload-file
```

Validate a volume set:
```
sitepack-validate volumes /path/to/sitepack.volumes.json
```

Create a volume set from an unpacked package:
```
sitepack-volumes create /path/to/unpacked/sitepack /path/to/output --max-part-size 104857600
```
Recommended `--max-part-size` is 104857600 (100 MiB) unless you need smaller parts.

Extract volumes into a directory (ZIP overlay):
```
sitepack-volumes extract /path/to/sitepack.volumes.json /path/to/output
```

Options:
- `--schemas <dir>` — path to JSON schemas (default `./schemas`).
- `--no-digest` — skip digest verification.
- `--strict` — treat warnings as errors (exit code 1).
- `--check-asset-blobs` — verify asset blob files referenced in asset-index (including chunked assets).
- `--format text|json` — console output format.
- `--quiet` — minimal console output.

## What is validated
1. Presence and schema validity of `sitepack.manifest.json` and `sitepack.catalog.json`.
2. For each catalog artifact:
   - safe path (no absolute paths or traversal),
   - file exists,
   - `size` matches,
   - `digest` matches (if provided).
3. Core NDJSON types are validated line-by-line against schemas:
   - entity-graph, asset-index, config-kv, recordset.
   - asset-index supports both single-blob and chunked assets.
4. JSON artifacts (capabilities/transform-plan) are validated as full JSON documents.
5. Object index/passport artifacts are validated and cross-checked when an object index is present.
6. Unknown `mediaType` does not fail validation: file/size/digest are checked, content is skipped with a warning.
7. Volume Set descriptors can be validated and assembled with the `volumes` command.

Note on NDJSON empty lines: an empty line is skipped with a warning.

## Profile mode
The `--profile` option verifies that the profile exists in `manifest.profiles` and validates the artifacts for that profile. In v0.4, profiles are an array, so the validator falls back to `manifest.artifacts` if a profile-to-artifact map is not available.

## Report
After validation, the tool writes:
```
<packageRoot>/reports/validate.json
```

Report structure:
```json
{
  "tool": { "name": "sitepack-validate", "version": "0.4.0" },
  "startedAt": "...",
  "finishedAt": "...",
  "target": { "type": "package|envelope|volume-set", "path": "..." },
  "summary": {
    "errors": 0,
    "warnings": 0,
    "artifactsTotal": 0,
    "artifactsValidated": 0,
    "artifactsSkipped": 0,
    "ndjsonLinesValidated": 0
  },
  "artifacts": [],
  "messages": []
}
```

## Exit codes
- `0` — no errors (warnings allowed).
- `1` — validation errors (or warnings in strict mode).
- `2` — CLI usage error.

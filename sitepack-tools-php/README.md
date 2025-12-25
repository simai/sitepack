# sitepack-tools-php

CLI validator for unpacked SitePack v0.4.0 packages on PHP 8.1+. It validates manifest, catalog, artifacts, and NDJSON against draft 2020-12 JSON Schema and writes `reports/validate.json`.

## Requirements
- PHP 8.1+
- composer

## Installation
```
composer install
```

## Usage
Validate an unpacked package:
```
./bin/sitepack-validate /path/to/unpacked/sitepack
```

Validate with a profile:
```
./bin/sitepack-validate /path --profile content+assets
```

Validate an encrypted envelope header:
```
./bin/sitepack-validate envelope /path/to/example.sitepack.enc.json --check-payload-file
```

Validate a volume set:
```
./bin/sitepack-validate volumes /path/to/sitepack.volumes.json
```

Options:
- `--schemas=<dir>` — path to schemas directory (default `./schemas`).
- `--no-digest` — skip digest verification.
- `--strict` — treat warnings as errors (exit code 1).
- `--check-asset-blobs` — check existence and integrity of files referenced in asset-index (including chunked assets).
- `--format=json|text` — console output format.
- `--quiet` — minimal output (package command only).

## Report
The report is written to:
```
<packageRoot>/reports/validate.json
```
For envelope mode, the report is written next to the header file in `reports/validate.json`.

## Behavior
- Unknown `mediaType` does not fail validation: path/size/digest are still checked, content is skipped with a warning.
- Empty NDJSON lines are skipped with a warning.
- Volume Set descriptors can be validated and assembled with the `volumes` command.

## Examples
Run example validation for `sitepack-spec/examples`:
```
php scripts/validate-examples.php /path/to/sitepack-spec/examples
```

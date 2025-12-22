# SitePack v0.1.0 — Short Specification

## 1. Purpose
SitePack is a packaging format for website data export/import. A package includes required metadata and a set of artifacts.

## 2. Container
- Canonical extension: `.sitepack`.
- Default container: ZIP.
- Importer MUST detect container format by content.
- TAR MAY be supported additionally.

## 3. Required files
The root of an unpacked package MUST include:
- `sitepack.manifest.json`
- `sitepack.catalog.json`

## 4. Manifest (short)
`sitepack.manifest.json` includes:
- `spec`: `{ name: "sitepack", version: "0.1.0" }`
- `package.id`
- `createdAt` (date-time)
- `profiles` (array of strings)
- `artifacts` (array of artifact IDs)

## 5. Catalog (short)
`sitepack.catalog.json` includes an array of artifacts, each with:
- `id`
- `mediaType`
- `path`
- `size`
- `digest` (SHOULD, sha256)

## 6. Core media types (MUST understand)
- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`
- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.recordset+ndjson`

## 7. Profiles
- `config-only`
- `content-only`
- `content+assets`
- `full`
- `full+code`
- `snapshot` (descriptive profile)

## 8. Unknown handling
- Unknown `mediaType`: MUST skip and log.
- Unknown `entity.type`: MUST NOT fail; MAY skip or import as opaque.

## 9. Digest and size
- `digest` format: `sha256:<hex>`.
- In v0.1 `digest` SHOULD, but `size` MUST.

## 10. Import security
- Block path traversal, absolute paths, null bytes.
- Size/file count limits MUST be enforced.
- Do not execute code automatically.
- Do not auto-apply secret config.

## 11. Encrypted Envelope (optional)
For transfer:
- `*.sitepack.enc` — age-encrypted bytes of the original `.sitepack`.
- `*.sitepack.enc.json` — public JSON header.

The header MUST match `schemas/envelope.schema.json` and include `payload.payloadDigest` (sha256).
After decryption, the package MUST be validated as a regular SitePack.

## 12. Optional extensions
- `Capabilities`: `application/vnd.sitepack.capabilities+json`
- `Transform Plan`: `application/vnd.sitepack.transform-plan+json`

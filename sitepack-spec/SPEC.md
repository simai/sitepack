# SitePack v0.2.0 — Specification

## Keywords
The keywords **MUST**, **SHOULD**, and **MAY** are to be interpreted as described in RFC 2119.

## 1. Overview and goals
SitePack is an open format for packaging website data for transfer between systems. The format defines required package metadata, an artifact catalog, and artifact content. Goals:
- interoperability for export/import across platforms;
- predictable structure and safe parsing;
- extensibility without breaking core compatibility.

## 2. Terms
- **Package**: a container with SitePack files.
- **Artifact**: a file inside the package described in the catalog.
- **Catalog**: the `sitepack.catalog.json` file listing artifacts.
- **Entity**: a content object (page, post, item, etc.).
- **Recordset**: tabular data (NDJSON records).
- **Asset**: a binary resource (image, file, media).
- **Profile**: a package profile defining expected artifacts.
- **Provenance**: origin metadata (source, version, authors), optional in metadata.
- **Capabilities**: tool capabilities (export/import).
- **Transform Plan**: plan of transformations applied during import/export.
- **Envelope**: external JSON header for encrypted transfer.

## 3. Container and file extensions
- Canonical package extension: `.sitepack`.
- Default container: ZIP.
- Implementations **MUST** support ZIP.
- TAR support **MAY** be implemented additionally.
- Importers **MUST** detect container format by content, not by extension.

## 4. Required files
The root of an unpacked package **MUST** contain:
- `sitepack.manifest.json`
- `sitepack.catalog.json`

### 4.1 sitepack.manifest.json format (minimum fields)
- `spec.name` = `sitepack`.
- `spec.version` — format version (SemVer).
- `package.id` — package identifier.
- `createdAt` — creation timestamp at the top level of the manifest.
- `profiles` — list of profiles; semantically a profile -> `artifact.id` map referring to `artifacts`.
- `artifacts` — array of `artifact.id` present in the package.
- Additional fields are allowed (extensions, provenance, etc.).

### 4.2 sitepack.catalog.json format (minimum fields)
- `artifacts[]` — list of objects.
- Each `artifact` includes: `id`, `mediaType`, `path`, `size` (digest optional).
- `digest` format: `sha256:<hex>`.
- Additional fields are allowed (e.g., `annotations`, schema hints).

### 4.3 Interpretation rule
- The importer **MUST** use manifest+catalog as the source of truth for package contents; file extensions MUST NOT be treated as the source of truth.

## 5. Artifacts
An artifact is a file inside the package described in the catalog. The catalog contains an array of artifact objects with fields:
- `id` (**MUST**): unique artifact identifier within the package.
- `mediaType` (**MUST**): artifact media type.
- `path` (**MUST**): relative path inside the package.
- `size` (**MUST**): file size in bytes.
- `digest` (**SHOULD** in v0.2): file checksum.
- `annotations` (**MAY**): extension object.

## 6. Entities (portable layer)

### 6.1 Relation links (Link) and relations encoding

#### A) `relations` field
- `relations` is an object mapping `relationKey` -> `Link[]`.
- Each relation value **MUST** be an array.

#### B) Relation key naming
- A relation key **SHOULD** be one of:
  - a well-known key from `registry/entity-types.md` (e.g., `assets`, `parent`, `children`, `categories`, `tags`, `author`, `related`),
  - `property.<CODE>` for CMS-style properties (e.g., `property.BRAND`, `property.RELATED_PRODUCTS`),
  - `field.<CODE>` for CMS-style fields,
  - vendor-namespaced keys **MAY** be used (e.g., `bitrix.property.BRAND`) but **SHOULD** be avoided in the portable layer unless necessary.

#### C) Link encoding
A Link **MUST** be either:
1) a string (shorthand) containing a reference identifier, or
2) an object:
   `{ "ref": "<string>", "meta"?: { ... } }`

Additional rules:
- `ref` **MUST** be a string.
- `meta` (if present) **MUST** be a JSON object.
- `meta` is for non-normative hints (e.g., `sourceField`, `role`, `cardinality`) and **MUST NOT** be required for correctness.

#### D) Reference targets
- `ref` **MAY** point to:
  - a SitePack entity id (e.g., `ent_...`),
  - a SitePack asset id (e.g., `asset_...`),
  - an external reference (URN), e.g., `urn:<namespace>:<type>:<id>`.
- Importers **MUST NOT** fail on unknown URN namespaces; they **MUST** record a warning and continue.

#### E) Importer behavior
- Importers **SHOULD** perform a two-pass import:
  1) create entities (or placeholders) and build a mapping `sitepackEntityId -> targetSystemId`,
  2) resolve and apply relations using the mapping.
- If a Link cannot be resolved:
  - it **MUST NOT** be fatal,
  - it **MUST** be recorded as a warning in the import report,
  - it **MUST NOT** block importing other entities.

#### F) Examples
Example 1 (shorthand string link):
```json
{
  "relations": {
    "property.BRAND": ["ent_brand_1"]
  }
}
```

Example 2 (link with meta):
```json
{
  "relations": {
    "related": [
      { "ref": "ent_42", "meta": { "role": "upsell" } }
    ]
  }
}
```

Example 3 (external URN link):
```json
{
  "relations": {
    "property.CRM_DEAL": ["urn:bitrix:crm.deal:123"]
  }
}
```

This repository includes a canonical end-to-end example at `sitepack-spec/examples/cross-relations/` demonstrating entity→entity relations (`property.BRAND`, `property.CITY`) and entity→asset relations (`assets`) via `artifacts/assets/index.ndjson` and `artifacts/assets/blobs/sha256/9809156062446115f511b5367e69e86c695987b3b90021634a4e059d8f497b45.png`.

## 7. Core media types
A compatible tool **MUST** understand:
- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`
- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.recordset+ndjson`

## 8. Profiles
Profiles define the expected artifacts and intent of the package:
- `config-only`: configuration only (key-value).
- `content-only`: content entities without assets.
- `content+assets`: content entities + asset index.
- `full`: content + assets + config + recordsets.
- `full+code`: `full` plus code artifacts and software manifest.
- `snapshot`: full static export/snapshot (descriptive profile, no implementation requirements in v0.2).

## 9. Unknown handling
- Unknown `mediaType`: the importer **MUST** skip the artifact and **MUST** log the event.
- Unknown `entity.type`: the importer **MUST NOT** fail; it **MAY** skip or import as opaque.

## 10. Integrity and digest
- Digest format: `sha256:<hex>`.
- In v0.2, `digest` in the catalog **SHOULD** be provided but is not required.
- `size` for each artifact **MUST** be provided.

## 11. Import security
Importers **MUST** protect against:
- path traversal (`..`), absolute paths, and null bytes in paths;
- excessive file sizes and file counts (limits **MUST** be enforced; specific numbers **MAY** be implementation-defined).

Additional requirements:
- Code artifacts **MUST NOT** be installed or executed automatically.
- Secret configs **MUST NOT** be applied automatically.

## 12. Capabilities (optional extension)
Extension for describing tool export/import capabilities. Media type:
`application/vnd.sitepack.capabilities+json`.

## 13. Transform Plan (optional extension)
Extension for describing transformation steps. Media type:
`application/vnd.sitepack.transform-plan+json`.

## 14. Encrypted Envelope (optional extension)
For secure transfer, an external envelope is allowed:
- `*.sitepack.enc` — age-encrypted file containing bytes of the original `.sitepack`.
- `*.sitepack.enc.json` — public JSON header.

Requirements:
- The header **MUST** conform to `schemas/envelope.schema.json`.
- The header **MUST** include `payload.payloadDigest` for the original `.sitepack`.

Decryption example:
```
age -d input.sitepack.enc > output.sitepack
```
After decryption, the package **MUST** be validated as a regular `.sitepack`.

## 15. Version compatibility
SitePack uses SemVer.
- A **major** mismatch indicates incompatibility; the importer **MUST** reject such packages.
- With matching **major**, the importer **MUST** accept packages with **minor** less than or equal to the supported version.
- The importer **MAY** accept a higher **minor** if it can safely ignore unknown fields/artifacts.

## 16. Appendix: recommended directory structure
Recommended unpacked structure:
```
sitepack.manifest.json
sitepack.catalog.json
artifacts/
  entities/
  assets/
  config/
  recordsets/
  code/
```
Specific directory names and artifact sets are defined by the profile and catalog.

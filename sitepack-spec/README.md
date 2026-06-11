# SitePack v0.4.0

SitePack is an open format for packaging website data for export/import between systems. A package contains two required files (manifest and catalog) and a set of artifacts (entities, assets, config, recordsets, etc.).

Key decisions:
- Canonical package extension: `.sitepack`.
- Default container: ZIP (container format is detected by content, not by extension).
- Secure transfer uses an external age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.
- Relations are standardized using Link encoding with array-based `relations` values.
- Volume Sets describe split package distribution (`sitepack.volumes.json`).
- Asset index supports chunked blobs (`chunks[]`).
- Conformance levels define what Reader, Validator, Archive, Previewer, Importer, and Exporter support mean.
- Profile contracts describe portable obligations for configuration, site structure, content/assets, snapshots, and product packages.
- Extension governance keeps Bitrix, Larena, WordPress, and other platform-specific data outside SitePack Core.

Documentation and materials:
- Specification: `SPEC.md`.
- Conformance: `CONFORMANCE.md`.
- Profile contracts: `profiles/`.
- Extension governance: `extensions/`.
- Adapter proofs: `adapter-proofs/`.
- JSON schemas: `schemas/`.
- Registries: `registry/`.
- Example packages: `examples/`.
- LLM materials: `llm/`.

Examples:
- `cross-relations`: Demonstrates entity-to-entity and entity-to-asset relations using Link[] (`property.BRAND`, `property.CITY`, `assets`) plus an asset index and blob.
- `chunked-assets`: Demonstrates chunked asset blobs using `chunks[]` in the asset index.
- `objects-two-objects`: Demonstrates object index + passports + dataset selectors.
- `volume-set-real`: Real multi-volume example with descriptor and `.sitepack` volume files.
- `volume-set-index-only`: Descriptor-only Volume Set (no volume files).
- `small-docs-site`: Adapter-neutral documentation site structure with pages, menu, redirect, and portable content entities.
- `small-blog-site`: Adapter-neutral page/post structure with a WordPress-style extension hint.

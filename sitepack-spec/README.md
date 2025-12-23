# SitePack v0.2.0

SitePack is an open format for packaging website data for export/import between systems. A package contains two required files (manifest and catalog) and a set of artifacts (entities, assets, config, recordsets, etc.).

Key decisions:
- Canonical package extension: `.sitepack`.
- Default container: ZIP (container format is detected by content, not by extension).
- Secure transfer uses an external age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.
- Relations are standardized using Link encoding with array-based `relations` values.

Documentation and materials:
- Specification: `SPEC.md`.
- JSON schemas: `schemas/`.
- Registries: `registry/`.
- Example packages: `examples/`.
- LLM materials: `llm/`.

Examples:
- `cross-relations`: Demonstrates entity-to-entity and entity-to-asset relations using Link[] (`property.BRAND`, `property.CITY`, `assets`) plus an asset index and blob.

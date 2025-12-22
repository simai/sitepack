# SitePack v0.1.0

SitePack is an open format for packaging website data for export/import between systems. A package contains two required files (manifest and catalog) and a set of artifacts (entities, assets, config, recordsets, etc.).

Key decisions:
- Canonical package extension: `.sitepack`.
- Default container: ZIP (container format is detected by content, not by extension).
- Secure transfer uses an external age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.

Documentation and materials:
- Specification: `SPEC.md`.
- JSON schemas: `schemas/`.
- Registries: `registry/`.
- Example packages: `examples/`.
- LLM materials: `llm/`.

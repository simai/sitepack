---
title: "SitePack specification"
description: "Readme"
---
# SitePack 1.2

SitePack is an open format for packaging website data for export, backup, inspection, and import between systems. The canonical specification lives in `sitepack-spec/`.

Key decisions:
- Canonical package extension: `.sitepack`.
- Default container: ZIP (container format is detected by content, not by extension).
- Secure transfer uses an external age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.
- Relations are standardized using Link encoding with array-based `relations` values.
- Volume Sets describe split package distribution (`sitepack.volumes.json`).
- Asset index supports chunked blobs (`chunks[]`).
- Compact and full snapshots distinguish external and embedded assets.
- Detached Ed25519 signatures protect package integrity without embedding trust.
- Semantic profiles and declarative recipes provide portable mapping without platform-pair matrices.

Documentation and materials:
- Specification: `SPEC.md`.
- Semantic mapping and compatibility: `MAPPING.md`.
- JSON schemas: `schemas/`.
- Registries: `registry/`.
- Example packages: `examples/`.
- Release notes: `releases/`.
- LLM materials: `llm/`.

Examples:
- `cross-relations`: Demonstrates entity-to-entity and entity-to-asset relations using Link[] (`property.BRAND`, `property.CITY`, `assets`) plus an asset index and blob.
- `chunked-assets`: Demonstrates chunked asset blobs using `chunks[]` in the asset index.
- `objects-two-objects`: Demonstrates object index + passports + dataset selectors.
- `volume-set-real`: Real multi-volume example with descriptor and `.sitepack` volume files.
- `volume-set-index-only`: Descriptor-only Volume Set (no volume files).
- `semantic-mapping`: raw source data and a portable semantic layer in one package.

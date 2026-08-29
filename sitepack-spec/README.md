# SitePack 1.2.0

SitePack is an open format for packaging structured data and digital assets for
transfer between systems. A package contains two required files (manifest and
catalog) and a set of artifacts (entities, assets, config, recordsets, etc.).
Websites, backups, installers, and other distributions are expressed through
profiles and extensions without changing the core package contract.

Key decisions:
- Canonical package extension: `.sitepack`.
- Default container: ZIP (container format is detected by content, not by extension).
- Secure transfer uses an external age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.
- Relations are standardized using Link encoding with array-based `relations` values.
- Volume Sets describe split package distribution (`sitepack.volumes.json`).
- Asset index supports embedded files, chunked files, and safe external locators.
- Compact packages can be enriched into new self-contained packages with a machine-readable report.
- Conformance levels define what Reader, Validator, Archive, Previewer, Importer, and Exporter support mean.
- Profile contracts describe portable obligations for configuration, site structure, content/assets, snapshots, and product packages.
- Extension governance keeps Bitrix, Larena, WordPress, and other platform-specific data outside SitePack Core.
- Optional detached Ed25519 signatures authenticate final package bytes without embedding trust or private keys in the archive.
- Semantic profiles, declarative recipes, and compatibility reports support cross-platform transfer without pairwise CMS matrices.

Documentation and materials:
- Specification: `SPEC.md`.
- Conformance: `CONFORMANCE.md`.
- Profile contracts: `profiles/`.
- Extension governance: `extensions/`.
- Adapter proofs: `adapter-proofs/`.
- Release notes: `releases/`.
- Detached signature contract: `SIGNATURES.md`.
- Semantic mapping and compatibility: `MAPPING.md`.
- JSON schemas: `schemas/`.
- Registries: `registry/`.
- Example packages: `examples/`.
- LLM materials: `llm/`.

Examples:
- `cross-relations`: Demonstrates entity-to-entity and entity-to-asset relations using Link[] (`property.BRAND`, `property.CITY`, `assets`) plus an asset index and blob.
- `chunked-assets`: Demonstrates chunked asset blobs using `chunks[]` in the asset index.
- `compact-external`: Demonstrates a compact snapshot with a stable external file locator.
- `objects-two-objects`: Demonstrates object index + passports + dataset selectors.
- `volume-set-real`: Real multi-volume example with descriptor and `.sitepack` volume files.
- `volume-set-index-only`: Descriptor-only Volume Set (no volume files).
- `small-docs-site`: Adapter-neutral documentation site structure with pages, menu, redirect, and portable content entities.
- `small-blog-site`: Adapter-neutral page/post structure with a WordPress-style extension hint.
- `semantic-mapping`: Raw platform data and a portable semantic layer in one package.

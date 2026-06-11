# Changelog

## Unreleased
- Added conformance levels for Reader, Validator, Archive, Previewer, Importer, and Exporter.
- Added portable profile contracts for configuration, content, site structure, content assets, snapshots, and product packages.
- Added extension governance for platform-specific adapter data outside SitePack Core.
- Added `application/vnd.sitepack.site-map+json` and the `site-structure` profile.
- Added the `small-docs-site` example package for an adapter-neutral documentation site.
- Added adapter-proof documentation and the `small-blog-site` example package.
- Added `small-docs-site` and `small-blog-site` to Node and PHP example validation coverage.
- Added validator checks for declared profile requirements in the Node and PHP reference tools.
- Added schema sync and aggregate validation targets to the root Makefile.
- Updated the Node lockfile to remove known audit vulnerabilities in transitive dependencies.

## 0.4.0 - 2025-12-23
- Added Objects layer (objects/index.json + passports + dataset selectors).
- Added objects example package `examples/objects-two-objects`.

## 0.3.0 - 2025-12-23
- Added Volume Sets (multi-file distribution via `sitepack.volumes.json`, recommended 100 MiB parts).
- Added Chunked Assets (large blobs split into chunks, validated by sha256).
- Kept single-version policy across the whole project (package + envelope + volume-set).

## 0.2.0 - 2025-12-22
- Standardized relation links (Link) and `relations: relationKey -> Link[]`.
- Added recommended relation key patterns: `property.<CODE>`, `field.<CODE>`, URN refs.
- Tightened entity schema to enforce relations format.
- Added canonical example `examples/cross-relations` (entity-to-entity and entity-to-asset with asset-index and blob).
- Synced reference validators schemas and example coverage.
- Aligned sitepack-envelope header/schema version to 0.2.0 (no format changes).

## 0.1.1 - 2025-12-22
- Standardize relation links (Link) and array-based relations encoding.
- Update entity schema to validate relations structure.
- Extend the entity registry with recommended relation key patterns.

## 0.1.0 - 2025-12-22

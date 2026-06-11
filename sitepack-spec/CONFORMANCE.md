# SitePack conformance levels

The keywords MUST, SHOULD, and MAY are to be interpreted as described in RFC 2119.

This document makes the phrase "supports SitePack" precise. A tool MUST NOT claim a conformance level unless it satisfies every requirement for that level and every lower level it depends on.

## Principles

- SitePack Core is platform-neutral. Bitrix, Larena, WordPress, Drupal, static-site and custom-system behavior belongs in adapters or declared extensions.
- The manifest and catalog are the package source of truth.
- Unknown artifacts and extensions MUST be handled predictably: preserve, skip, warn, or partially import with a report.
- Importers and exporters SHOULD declare supported profiles and extensions.
- Importers MUST produce a machine-readable report for unsupported, skipped, partially applied, or failed artifacts.

## Reader

A Reader can inspect a package without applying it.

Requirements:

- MUST parse `sitepack.manifest.json`.
- MUST parse `sitepack.catalog.json`.
- MUST list catalog artifacts by `id`, `mediaType`, `path`, `size`, and `digest` when present.
- MUST reject unsafe catalog paths.
- SHOULD expose declared profiles and extensions.

Allowed degradation:

- MAY ignore artifact contents.
- MAY ignore unknown media types after reporting them.

## Validator

A Validator checks package structure and artifacts.

Requirements:

- MUST satisfy Reader.
- MUST validate manifest and catalog schemas.
- MUST verify catalog paths and artifact existence.
- MUST verify `size`.
- SHOULD verify `digest` when present.
- MUST validate known core media types against their schemas.
- MUST check declared profile requirements when profile contracts are available.
- MUST report unknown media types as warnings, not silent success.

Allowed degradation:

- MAY make asset blob verification optional, but the report MUST say whether blobs were checked.

## Archive

An Archive tool preserves SitePack packages without semantic import.

Requirements:

- MUST satisfy Reader.
- MUST preserve every file recorded in the catalog.
- MUST preserve unknown media types and extension artifacts byte-for-byte.
- MUST NOT rewrite package content unless producing a new validated package with new metadata.

Allowed degradation:

- MAY refuse oversized packages by policy, but MUST report the limit.

## Previewer

A Previewer renders enough of a package for human inspection.

Requirements:

- MUST satisfy Reader.
- SHOULD render `application/vnd.sitepack.site-map+json` when present.
- SHOULD render content entities and assets when enough portable data exists.
- MUST report which artifacts were used for preview and which were ignored.

Allowed degradation:

- MAY show a structural preview instead of exact visual output.
- MUST NOT claim full import readiness from preview success alone.

## Importer Basic

An Importer Basic applies portable data to a target platform.

Requirements:

- MUST satisfy Validator.
- MUST declare supported profiles.
- MUST import the core media types it claims to support.
- MUST produce an import report.
- MUST NOT execute code artifacts automatically.
- MUST NOT automatically apply secret configuration.
- MUST warn and continue for unknown entity types, unknown URN namespaces, and unsupported extension artifacts.

Allowed degradation:

- MAY import unsupported content as opaque records when the target platform supports opaque storage.
- MAY skip unsupported artifacts with warnings.

## Importer Advanced

An Importer Advanced resolves relations and profile obligations.

Requirements:

- MUST satisfy Importer Basic.
- SHOULD perform two-pass relation resolution.
- MUST record unresolved relations in the import report.
- MUST implement the importer obligations for each profile it claims.
- MUST expose destructive or security-sensitive operations as explicit user decisions.

Allowed degradation:

- MAY partially import a profile only when the report clearly marks the profile as partially applied.

## Extension Importer

An Extension Importer supports one or more declared extension families.

Requirements:

- MUST satisfy Importer Basic.
- MUST declare supported extension ids and versions.
- MUST validate extension artifacts when schemas are available.
- MUST report unsupported extension versions.
- MUST keep extension behavior outside SitePack Core semantics.

Allowed degradation:

- MAY skip optional extension artifacts with warnings.
- MUST fail or request user confirmation when a required extension artifact cannot be applied safely.

## Exporter Core

An Exporter Core creates valid SitePack packages.

Requirements:

- MUST emit valid manifest and catalog files.
- MUST emit valid artifacts for the profiles it declares.
- MUST declare source platform provenance.
- MUST use portable media types for portable data.
- MUST use extensions for platform-specific data.
- SHOULD include digests.

Allowed degradation:

- MAY export a partial package if the profile and report make the limitation explicit.

## Exporter Full

An Exporter Full creates profile-complete packages with adapter metadata.

Requirements:

- MUST satisfy Exporter Core.
- MUST declare supported profiles and extension artifacts.
- MUST include an export report or equivalent provenance when data is skipped or transformed.
- MUST include enough artifacts for another compliant importer to evaluate profile completeness.

Allowed degradation:

- MAY include platform-specific extensions, but the package MUST remain readable and archivable by core tools.

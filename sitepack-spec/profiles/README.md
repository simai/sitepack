# SitePack profile contracts

Profiles define portable package intent and importer/exporter obligations. They are not CMS names.

The keywords MUST, SHOULD, and MAY are to be interpreted as described in RFC 2119.

Initial profile contracts:

- [`config-only`](config-only.md): configuration and settings/options data.
- [`content-only`](content-only.md): content entities without required binary assets.
- [`site-structure`](site-structure.md): site identity, locales, routes, pages, menus, redirects, and metadata.
- [`content-assets`](content-assets.md): content entities and binary assets.
- [`site-snapshot`](site-snapshot.md): archival or preview-oriented site snapshot.
- [`product-package`](product-package.md): installable product, solution, theme, plugin, or starter-site metadata.

Compatibility aliases:

- `content+assets` is a legacy alias of `content-assets`.
- `full+code` is a legacy alias of `full-code`.
- `snapshot` is a legacy alias of `site-snapshot` when the package is preview/archive oriented.

Profiles SHOULD be tested against at least two adapters before becoming stable. In the first phase, Bitrix and Larena are the main adapter checks. WordPress is the anti-bias check for portable website semantics.

## Common importer obligations

An importer that claims a profile MUST:

- read manifest and catalog as source of truth;
- validate required artifacts when schemas are available;
- import supported artifacts or report why they were skipped;
- produce a machine-readable import report for warnings, skipped artifacts, partial imports, and unsupported extensions;
- preserve or report unknown extension artifacts;
- avoid automatic execution of code or destructive operations.

## Common exporter obligations

An exporter that declares a profile MUST:

- include all required media types for that profile;
- avoid platform-specific media types for portable data;
- declare platform-specific data as extensions;
- include source provenance;
- include enough metadata for another tool to evaluate profile completeness.

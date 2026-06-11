# Profile: site-structure

Purpose: transfer portable website structure without requiring a specific CMS data model.

Required media types:

- `application/vnd.sitepack.site-map+json`

Optional media types:

- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`
- `application/vnd.sitepack.transform-plan+json`

Exporter obligations:

- MUST describe site identity and route/page/menu intent in `site-map`.
- SHOULD include locales when the source site is localized.
- SHOULD include redirects when known.
- SHOULD link pages to content entities, assets, snapshots, or extension artifacts through references rather than embedding platform-specific objects.

Importer obligations:

- MUST map routes, page tree and menu intent where the target platform supports them.
- MUST report pages, menus, redirects, locales, or metadata that cannot be applied.
- SHOULD preserve source identifiers for later relation resolution and re-import.
- MUST NOT require Bitrix iblocks, Laravel Eloquent models, WordPress post ids, or any other platform-specific object model.

Adapter examples:

- Bitrix: public sections, menus, URL rewrite rules, page metadata.
- Larena: Docara pages, routes, menu tree, future page/layout model.
- WordPress: pages, menus, permalinks, redirects.

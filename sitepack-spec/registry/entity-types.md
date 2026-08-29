# Entity Types Registry

The names below predate the stable SitePack 1.0 website vocabulary. They remain
valid entity type strings for backward compatibility, but they are not aliases
for `sitepack.website-core` types. Cross-platform tools SHOULD use the explicit
`sitepack.legacy-entity-types-to-website-core@1.0.0` mapping recipe.

## Core types
- `content.page` — page.
- `content.post` — post/news.
- `content.item` — catalog item/general record.
- `document.page` — documentation or long-form page.
- `taxonomy.category` — category.
- `taxonomy.tag` — tag.
- `route.redirect` — redirect rule.

## Core recommended
- `system.user` — user (author).

## Recommended relation keys and patterns
- `assets`: Link[] to asset ids (asset_*).
- `parent`: Link[] (typically single) to entity ids.
- `children`: Link[] to entity ids.
- `categories`: Link[] to taxonomy category ids.
- `tags`: Link[] to taxonomy tag ids.
- `author`: Link[] to `system.user` (or external) ids.
- `related`: Link[] for generic relationships.
- `property.<CODE>`: Link[] for CMS-style property references.
  - CODE is exporter-defined; importers SHOULD treat it as case-sensitive by default.
- `field.<CODE>`: Link[] for CMS-style field references.
- External references:
  - URN format: `urn:<namespace>:<entityType>:<nativeId>`.
  - Examples: `urn:bitrix:crm.deal:123`, `urn:bitrix:task:456`.
  - URNs are not guaranteed to be resolvable across systems; keep them as informational links unless supported.

Examples:
- `property.BRAND`: `["ent_brand_1"]`
- `field.RELATED_PRODUCTS`: `[{ "ref": "ent_200", "meta": { "role": "cross-sell" } }]`
- `property.CRM_DEAL`: `["urn:bitrix:crm.deal:123"]`

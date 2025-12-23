# Entity Types Registry

## Core types
- `content.page` — page.
- `content.post` — post/news.
- `content.item` — catalog item/general record.
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

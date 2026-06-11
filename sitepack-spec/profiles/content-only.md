# Profile: content-only

Purpose: transfer portable content entities without requiring binary assets.

Required media types:

- `application/vnd.sitepack.entity-graph+ndjson`

Optional media types:

- `application/vnd.sitepack.site-map+json`
- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.recordset+ndjson`
- `application/vnd.sitepack.object-index+json`
- `application/vnd.sitepack.object-passport+json`

Exporter obligations:

- MUST emit content entities in portable entity graph form.
- SHOULD use portable `type`, `attributes`, and `relations` fields before platform-specific extensions.
- SHOULD declare platform-specific content details as extensions.

Importer obligations:

- MUST import or preserve content entities it supports.
- MUST report unsupported entity types.
- SHOULD preserve source identifiers for later relation resolution.

Adapter examples:

- Bitrix: iblock elements without files.
- Larena: Docara/content records without filesystem blobs.
- WordPress: posts/pages without media library blobs.

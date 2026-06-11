# Profile: content-assets

Purpose: transfer content entities and their binary assets.

Required media types:

- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`

Optional media types:

- `application/vnd.sitepack.site-map+json`
- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.recordset+ndjson`
- `application/vnd.sitepack.object-index+json`
- `application/vnd.sitepack.object-passport+json`

Exporter obligations:

- MUST emit content entities in portable entity graph form.
- MUST emit asset index records for referenced binary assets.
- SHOULD link entities to assets through `relations.assets`.
- SHOULD include SHA-256 and size metadata for asset blobs.

Importer obligations:

- MUST import or preserve content entities it supports.
- MUST follow recorded asset paths and MUST NOT assume fixed blob directories.
- MUST report missing, unsupported, or unresolved assets.
- SHOULD perform two-pass relation resolution.

Adapter examples:

- Bitrix: iblock elements, sections, files.
- Larena: Docara content, filesystem assets, future page/content models.
- WordPress: posts, pages, media library items.

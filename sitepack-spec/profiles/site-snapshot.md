# Profile: site-snapshot

Purpose: preserve or preview a site state even when full semantic import is impossible.

Required media types:

- At least one preview, content, site-structure, or extension artifact sufficient for inspection.

Recommended media types:

- `application/vnd.sitepack.site-map+json`
- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`

Exporter obligations:

- SHOULD include enough structure for a previewer to render a route/page list.
- SHOULD include static snapshot artifacts through declared extensions when HTML snapshots are exported.
- MUST declare non-portable snapshot details as extensions.

Importer obligations:

- MAY import snapshot data as static pages or archived content.
- MUST report whether the import was semantic, static, partial, or archive-only.
- MUST NOT claim full reconstruction when only snapshot data was applied.

Adapter examples:

- Bitrix: public page snapshots plus files.
- Larena: static preview import, Docara draft import, archive inspection.
- WordPress: static pages or archive preview.

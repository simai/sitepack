# Profile: product-package

Purpose: describe an installable product, solution, theme, plugin, industry pack, or starter site independently of one platform runtime.

Required media types:

- `application/vnd.sitepack.config-kv+ndjson` or `application/vnd.sitepack.capabilities+json`

Optional media types:

- `application/vnd.sitepack.site-map+json`
- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`
- `application/vnd.sitepack.transform-plan+json`
- declared extension artifacts

Exporter obligations:

- MUST declare product/package identity, version, source platform, and capabilities through portable artifacts or extension manifests.
- SHOULD declare install/update/import requirements.
- MUST use extensions for platform-specific installation behavior.
- MUST NOT rely on automatic code execution for correctness.

Importer obligations:

- MUST treat product-package imports as potentially operationally sensitive.
- SHOULD present install/update requirements before applying data.
- MUST report unsupported package capabilities.
- MUST NOT install code artifacts automatically.

Adapter examples:

- Bitrix: module/site solution payload.
- Larena: package/product install metadata, Docara product pack.
- WordPress: plugin/theme/starter-site package metadata.

# Profile: config-only

Purpose: transfer settings, options, configuration values, and environment-independent setup data.

Required media types:

- `application/vnd.sitepack.config-kv+ndjson`

Optional media types:

- `application/vnd.sitepack.capabilities+json`
- `application/vnd.sitepack.transform-plan+json`

Exporter obligations:

- MUST represent settings as portable key/value records.
- SHOULD include `namespace` when a setting belongs to a product, module, package, or solution.
- MUST mark secrets with `sensitivity = "secret"` and `applyPolicy = "never"` or another non-automatic policy.
- SHOULD include context fields as additional properties when values are scoped by site, page, locale, role, user, or environment.

Importer obligations:

- MUST NOT automatically apply secret settings.
- SHOULD map settings to the target platform settings/options model.
- MUST report unsupported or conflicting settings.
- SHOULD support dry-run or review mode for destructive or security-sensitive settings.

Adapter examples:

- Bitrix: module options, site options, SF5 settings.
- Larena: `larena/setting` or a future platform settings subsystem.
- WordPress: options, theme settings, plugin settings.

# SitePack extension governance

The keywords MUST, SHOULD, and MAY are to be interpreted as described in RFC 2119.

Extensions let adapters carry platform-specific data without redefining SitePack Core.

## Extension identity

Extension ids SHOULD use reverse-DNS style namespaces:

- `org.simai.bitrix.*`
- `org.simai.larena.*`
- `org.wordpress.*`
- `com.example.vendor.*`

An extension id MUST NOT use the `sitepack` namespace unless it is part of the SitePack specification.

## Declaring extensions

Packages SHOULD declare extensions in `manifest.extensions`.

Recommended shape:

```json
{
  "extensions": [
    {
      "id": "org.simai.larena.docara",
      "version": "1.0.0",
      "required": false,
      "artifacts": ["larena.docara.pages"],
      "schema": "https://example.org/schemas/larena-docara-pages.schema.json"
    }
  ]
}
```

Fields:

- `id` MUST be a stable extension id.
- `version` SHOULD be SemVer.
- `required` SHOULD indicate whether the package can be meaningfully imported without this extension.
- `artifacts` SHOULD list related catalog artifact ids.
- `schema` MAY point to a JSON Schema for extension artifacts.
- Additional fields MAY describe capabilities, security, destructive operations, docs, or adapter compatibility.

## Importer behavior

An importer MUST:

- preserve unknown extension artifacts when acting as an archive tool;
- warn when an unsupported optional extension is skipped;
- fail, pause, or ask for user confirmation when an unsupported required extension blocks a claimed import profile;
- include extension support results in the import report.

An importer MUST NOT silently apply extension artifacts it does not understand.

## Exporter behavior

An exporter MUST:

- put portable data into core media types where possible;
- put platform-specific data into extension media types;
- declare required extensions when profile completeness depends on them;
- avoid naming profiles after a CMS when an extension is enough.

## Compatibility policy

Extension versions SHOULD follow SemVer.

Importers SHOULD accept compatible patch/minor versions when they can safely ignore unknown fields. Importers MAY reject incompatible major versions or required extensions they cannot apply safely.

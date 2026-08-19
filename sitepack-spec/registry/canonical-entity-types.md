# Canonical Entity Types

SitePack snapshot exporters SHOULD map source data to these portable types while retaining source-specific fields in namespaced extensions:

- `organization`
- `contact`
- `page`
- `menu`
- `news`
- `document`
- `employee`
- `gallery`
- `education-disclosure`
- `file`
- `custom-section`

Logical ids and relations MUST remain stable across compact enrichment and full snapshot generation. Target recipes may map these entities into a preinstalled solution, but SitePack does not promise design parity.

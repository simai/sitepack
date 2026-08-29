# Canonical Entity Types

This registry is the canonical SitePack 1.x website vocabulary. Its
machine-readable contract is
`semantic-profiles/sitepack.website-core-1.0.0.json` and its profile reference is
`sitepack.website-core@1.0.0`.

SitePack snapshot exporters SHOULD map source data to these portable types while retaining source-specific fields in namespaced extensions:

- `organization`
- `contact`
- `page`
- `menu`
- `news`
- `document`
- `employee`
- `gallery`
- `file`
- `custom-section`

Logical ids and relations MUST remain stable across compact enrichment and full snapshot generation. Target recipes may map these entities into a preinstalled solution, but SitePack does not promise design parity.

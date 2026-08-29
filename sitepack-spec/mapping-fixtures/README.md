# Semantic mapping fixtures

These fixtures exercise the SitePack 1.2 mapping contracts without publishing
production CMS matrices in the normative core.

- `recipes/exact.json` maps the portable example profile without changes.
- `recipes/transformed.json` converts HTML to Markdown and maps an enum.
- `recipes/fallback.json` preserves unsupported news as ordinary pages.
- `capabilities/blocked.json` demonstrates missing required data.
- `recipes/unknown-optional.json` and `unknown-required.json` demonstrate the
  forward-compatibility rule for unknown operations.
- `recipes/executable-invalid.json` demonstrates schema rejection of executable
  or expression-like recipe parameters.
- `capabilities/bitrix-demo.json`, `larena-demo.json`, and
  `wordpress-demo.json` use one `portable-identity.json` recipe to demonstrate
  capability-derived compatibility without pairwise CMS matrices.

The recipe author in a valid fixture is descriptive metadata only. Treat a
copy with an author or digest absent from the local trust policy as the
untrusted-recipe scenario: validation and compatibility analysis MAY proceed,
but automatic import MUST NOT. Trust decisions deliberately remain local to
the importer and are not inferred by the reference compatibility compiler.

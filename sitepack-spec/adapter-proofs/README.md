# SitePack adapter proofs

Adapter proofs are small, public checks that keep SitePack Core portable.

They do not implement full importers or exporters. They show that a profile can
be mapped to different platform models without changing the core media types or
turning a CMS-specific concept into a core requirement.

Current proofs:

- [`site-structure`](site-structure.md): mapping of the portable Site Map
  artifact to Docara/Larena-style documentation sites, WordPress-style page and
  menu models, and static-site generators.

Proof rules:

- Portable data stays in SitePack Core media types.
- Platform-specific fields belong to declared extensions.
- Unsupported platform features are reported by the adapter instead of changing
  the profile contract.
- Each proof should point to at least one validated example package.

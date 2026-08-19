# Profile: `full-snapshot`

Purpose: preserve a self-contained site snapshot with all available files embedded in the package.

- MUST include at least one site-map, entity-graph, or asset-index artifact.
- Every asset-index entry MUST use `storage=embedded` with either `path` or `chunks`.
- Asset size and SHA-256 are required and MUST be verified.
- Enrichment MUST create a new package id and MUST NOT overwrite its compact source.
- If an external asset cannot be embedded, the report MUST record it and the result MUST NOT claim `full-snapshot` conformance.

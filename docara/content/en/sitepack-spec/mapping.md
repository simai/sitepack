---
title: "Semantic mapping"
description: "Portable profiles, recipes, plans, and compatibility reports"
---
# Semantic mapping

SitePack 1.2 evaluates cross-platform migration through a canonical layer:

```text
source adapter -> semantic profile -> mapping recipe -> target capabilities
               -> transform plan + compatibility report
```

The standard does not contain a matrix for every CMS pair. A semantic profile
describes portable entities, fields, relations, and files. A recipe describes a
directional declarative mapping. The target adapter publishes its actual local
capabilities.

Recipes cannot contain PHP, JavaScript, SQL, shell commands, arbitrary
expressions, or other executable code. Unknown optional operations are reported
and skipped; an unknown required operation blocks planning.

The compatibility report classifies data as exact, transformed, fallback,
skipped, or blocked and records expected representational, content, behavioral,
or security losses. The report is advisory and never authorizes import.

An archive may contain a raw platform extension for exact same-platform restore
and a portable semantic layer for migration. A package signature proves byte
integrity but does not make an embedded recipe trusted. Target capabilities
always come from the locally installed importer.

See the canonical contract in `sitepack-spec/MAPPING.md` and the schemas:

- `semantic-profile.schema.json`;
- `mapping-recipe.schema.json`;
- `compatibility-report.schema.json`;
- the backward-compatible mapping section of `capabilities.schema.json`;
- the compiled contract in `transform-plan.schema.json`.

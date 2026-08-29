# Semantic mapping and compatibility

The keywords MUST, SHOULD, and MAY are to be interpreted as described in RFC 2119.

SitePack semantic mapping lets adapters evaluate cross-platform migration without
embedding an N-by-N table of CMS pairs in the core standard.

```text
source platform data
  -> semantic profile
  -> declarative mapping recipe
  -> target adapter capabilities
  -> immutable transform plan and compatibility report
```

## Contracts

### Semantic profile

Media type: `application/vnd.sitepack.semantic-profile+json`.

A semantic profile defines stable entity types, fields, value types, formats,
relations, asset bindings, and supported extension families. Profile identity is
the pair `id` and `version`; a combined human-readable reference MAY be written as
`<id>@<version>`.

SitePack publishes the minimal `sitepack.website-core@1.0.0` profile. Domain
vocabularies SHOULD extend it through separately versioned profiles rather than
adding industry fields to SitePack Core.

### Mapping recipe

Media type: `application/vnd.sitepack.mapping-recipe+json`.

A recipe is reusable, directional, and declarative. It maps one semantic profile
to another profile or adapter surface. It MUST NOT contain executable code or an
expression language. Every rule declares whether it is required and MAY classify
the expected loss.

Unknown optional operations MUST be reported and skipped. An unknown required
operation MUST block compilation. A `content.convert` rule MUST name a converter
declared by the target adapter capabilities.

### Adapter capabilities

The optional `mapping` section of
`application/vnd.sitepack.capabilities+json` describes locally installed target
support. Importers MUST obtain target capabilities from their local adapter and
MUST NOT treat capabilities embedded in an untrusted package as target authority.

### Transform plan

Media type: `application/vnd.sitepack.transform-plan+json`.

The transform plan is an immutable, operation-specific compilation result. It
binds exact profile, recipe, and target versions to an input digest. Importers
MUST recompile or reject a plan when any bound input changes.

### Compatibility report

Media type: `application/vnd.sitepack.compatibility-report+json`.

The report inventories evaluated entity types, fields, relations, and asset
bindings, then classifies them as `exact`, `transformed`, `fallback`, `skipped`,
or `blocked`. It records missing required data, unsupported extensions, expected
losses, and the plan digest. A report is advisory and does not authorize import
or destructive behavior.

## Package and registry distribution

Profiles and recipes MAY be catalog artifacts or external registry documents.
External references MUST be pinned by id, version, and digest before use.
Profile references provide an optional `digest` field over canonical JSON for
this pinning; target capabilities repeat that digest when a recipe constrains
it. A registry recipe is pinned by the surrounding registry or importer policy.

An embedded recipe is package data, not trusted code. A detached SitePack
signature proves that embedded bytes were not changed; it does not make the
author or transformation policy trusted. Automatic use requires a locally
trusted author/digest or explicit importer policy.

Archive tools MUST preserve unknown mapping artifacts byte-for-byte. Importers
MUST report unsupported artifacts and MUST NOT silently apply them.

## Raw and portable layers

A package MAY contain both:

- a platform-specific raw extension for lossless same-platform restore;
- portable core artifacts described by a semantic profile for cross-platform
  migration.

The two layers MUST use stable logical ids or explicit relations. An importer
MUST NOT infer portable meaning from raw platform ids.

## Deterministic compilation

A compiler MUST:

1. validate the profile, recipe, and target capabilities;
2. verify profile and adapter constraints;
3. reject unknown required operations and unavailable required converters;
4. canonicalize input JSON by recursively sorting object keys while preserving
   array order;
5. calculate `inputsDigest` over the canonical profile, capabilities, recipe,
   and the catalog artifact IDs with their verified actual digests;
6. emit steps in recipe order and calculate `plan.digest` without the digest field;
7. emit a compatibility report bound to the same digests.

The compiler MUST NOT change package or target data.

Profile version constraints in version 1.2 are intentionally deterministic and
limited to an exact SemVer, `<major>.x`, or `*`.

## Compatibility status

- `compatible`: no blocked, skipped, or fallback subjects.
- `partial`: no blocked subjects, but at least one fallback or skipped subject.
- `incompatible`: at least one blocked subject or a forbidden loss.

Visual parity is never implied by compatibility status. Representational,
content, behavioral, and security losses MUST remain explicit.

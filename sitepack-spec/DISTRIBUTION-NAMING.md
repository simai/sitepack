# Distribution naming

SitePack filenames are human-readable transport metadata. The package manifest,
catalog, and Volume Set descriptor remain the source of truth. Importers must
not infer package semantics from a filename or reject an otherwise valid
package only because it uses another safe filename.

## Recommended base name

Snapshot exporters should build the base name as:

```text
<site>_<YYYYMMDD>_<HHMMSS>_<mode>
```

Rules:

- `site` is the lowercase ASCII DNS host without protocol, port, trailing dot,
  path, query, or fragment;
- internationalized hosts use their ASCII/Punycode representation;
- the timestamp is derived from `manifest.createdAt`, normalized to UTC, and
  formatted without separators;
- `mode` is a lowercase ASCII token; `compact` and `full` are recommended for
  snapshot distributions;
- a filename must not contain credentials, tenant-private identifiers, or a
  signed download URL.

Example:

```text
school.obr.site_20260825_113522_full
```

The timestamp in a filename is informational. `manifest.createdAt` remains the
authoritative creation time.

## Single-file distribution

```text
school.obr.site_20260825_113522_full.sitepack
school.obr.site_20260825_113522_full.sitepack.sig.json
```

The signature sidecar is optional and follows `SIGNATURES.md`.

## Multi-volume distribution

The distribution directory should use the base name. Volume files use a
minimum four-digit, zero-padded index and count:

```text
school.obr.site_20260825_113522_full/
  sitepack.volumes.json
  sitepack.volumes.json.sig.json
  school.obr.site_20260825_113522_full.part-0001-of-0012.sitepack
  school.obr.site_20260825_113522_full.part-0002-of-0012.sitepack
  ...
  school.obr.site_20260825_113522_full.part-0012-of-0012.sitepack
```

If a set contains more than 9,999 volumes, exporters increase the width to the
number of digits in the total count. All parts in one set use the same width.

`sitepack.volumes.json` remains the canonical descriptor filename. Its optional
detached signature is `sitepack.volumes.json.sig.json`. Signing the descriptor
authenticates its list of volume names, sizes, and SHA-256 digests. Importers
must still verify every listed part before extraction.

Object-storage keys are implementation details and may use an operation UUID.
An HTTP download may expose the recommended name through `Content-Disposition`
without renaming the stored object.

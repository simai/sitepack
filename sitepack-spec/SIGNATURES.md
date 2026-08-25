# Detached signatures

SitePack signatures are optional detached JSON sidecars named
`<package>.sig.json`. A signature authenticates the exact final bytes of a
`.sitepack`, encrypted payload or volume descriptor.

For a multi-volume distribution, applications may sign the
`sitepack.volumes.json` descriptor. The signed descriptor binds the exact list
of volume filenames, sizes and SHA-256 digests. Importers must verify the
descriptor signature and then verify every listed volume before extraction.

The sidecar contains:

- Ed25519 algorithm and stable `keyId`;
- package SHA-256 and byte size;
- creation timestamp;
- detached signature over the canonical input defined in `SPEC.md`.

Trust is external. A package, manifest or sidecar cannot make its own key
trusted. Applications resolve `keyId` from a protected trust store, check key
revocation outside the package, then verify digest, size and signature before
opening or importing content.

Verification results are intentionally distinct:

- `valid`: bytes are unchanged and the configured key is trusted;
- `untrusted`: the signature is structurally valid but `keyId` is unknown;
- `invalid`: malformed sidecar, changed bytes, bad key or failed signature.

Signatures provide technical authenticity and integrity. They are not a
qualified legal electronic signature.

## Trust store example

```json
{
  "keys": {
    "backup-production-2026": {
      "algorithm": "Ed25519",
      "publicKey": "BASE64_RAW_32_BYTE_PUBLIC_KEY"
    }
  }
}
```

Private keys and trusted public-key configuration must never be stored inside a
SitePack package.

# Transform Kind Registry

Transform kinds are declarative identifiers. They MUST NOT execute package code
or an arbitrary expression. Mapping recipe compilers support the following
portable operations:

- `entity.map`
- `field.copy`
- `field.rename`
- `field.cast`
- `value.default`
- `enum.map`
- `relation.map`
- `asset.bind`
- `content.convert`
- `fallback.wrap`
- `value.drop`

`content.convert` names a converter that MUST be registered in local adapter
capabilities. The legacy operations below remain valid transform-plan kinds but
do not expand the portable mapping allowlist.

## rewrite.links
Rewrite links inside content and assets.

Example params:
```json
{
  "fromHost": "old.example.com",
  "toHost": "new.example.com",
  "rewriteRelative": true
}
```

## entity.map
Map entity types or attributes.

Example params:
```json
{
  "typeMap": {
    "content.post": "content.article"
  },
  "attributeMap": {
    "content.title": "title"
  }
}
```

## recordset.map
Transform recordsets (rename fields, filters).

Example params:
```json
{
  "recordset": "example_table",
  "rename": {
    "old_field": "new_field"
  }
}
```

## config.map
Transform configuration entries.

Example params:
```json
{
  "namespace": "public",
  "keyMap": {
    "site.title": "app.title"
  }
}
```

## snapshot.emitFiles
Emit snapshot files (HTML/JSON).

Example params:
```json
{
  "outputDir": "snapshot/",
  "emitAssets": true
}
```

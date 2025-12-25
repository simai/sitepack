# SitePack

SitePack is an open standard for packaging website data for backup and migration between systems.

## Projects
- `sitepack-spec/` — specification, schemas, registries, and examples.
- `sitepack-tools-node/` — Node.js reference validator.
- `sitepack-tools-php/` — PHP reference validator.

## Quickstart
Validate examples with the Node tool:
```
cd sitepack-tools-node
npm ci
npm run validate-examples -- ../sitepack-spec/examples
```

Validate one unpacked package:
```
node bin/sitepack-validate <packageRoot>
```

Validate an envelope header:
```
node bin/sitepack-validate envelope <path-to-enc-json>
```

Validate a volume set:
```
node bin/sitepack-validate volumes <path-to-volumes-json>
```

Create a volume set:
```
node bin/sitepack-volumes create <packageRoot> <outDir> --max-part-size 104857600
```

PHP quickstart:
```
cd sitepack-tools-php
composer install
./bin/sitepack-validate --quiet package <packageRoot>
./bin/sitepack-validate envelope <path-to-enc-json>
./bin/sitepack-validate volumes <path-to-volumes-json>
php scripts/validate-examples.php ../sitepack-spec/examples
```

## Repository layout
```
.
├── sitepack-spec/
├── sitepack-tools-node/
└── sitepack-tools-php/
```

## Versioning
The specification and tools follow SemVer. See subproject changelogs for details.

## License
MIT. See `LICENSE` in the repository root and in each subproject.

## Contributing
See `CONTRIBUTING.md`.

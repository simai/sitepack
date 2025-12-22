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
npm install
npm run validate-examples -- ../sitepack-spec/examples
```

Validate one unpacked package:
```
./bin/sitepack-validate <path>
```

Validate an envelope header:
```
./bin/sitepack-validate envelope <path-to-enc-json>
```

PHP quickstart:
```
cd sitepack-tools-php
composer install
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

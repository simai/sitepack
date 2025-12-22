# Contributing

## English-only policy
All text in this monorepo must be English-only. Please avoid non-English content in documentation, examples, messages, and reports.

## Proposing spec changes
- Update the specification in `sitepack-spec/`.
- Update schemas and examples if the change affects structure or required fields.
- Keep the spec and examples consistent.

## Validation before opening a PR
Run the example validations:

Node.js validator:
```
cd sitepack-tools-node
npm install
npm run validate-examples -- ../sitepack-spec/examples
```

PHP validator:
```
cd sitepack-tools-php
composer install
php scripts/validate-examples.php ../sitepack-spec/examples
```

## Coding standards
- Node.js: use clear naming and keep dependencies minimal. ESLint is optional.
- PHP: PSR-12 formatting, `declare(strict_types=1);` in every file, and PHPDoc for all methods with concrete types.

## Design principles
- Portable vs raw data: prefer the portable SitePack layer for interoperability; store raw or platform-specific data in extensions/annotations.
- Optional extensions: Capabilities and Transform Plan are optional and must not be required for package validity.
- Unknown media types: tooling must skip unknown media types without failing and must log the event.

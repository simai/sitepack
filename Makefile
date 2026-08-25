.PHONY: validate-node validate-php validate-distribution check-schema-sync validate-all

check-schema-sync:
	diff -rq sitepack-spec/schemas sitepack-tools-node/schemas
	diff -rq sitepack-spec/schemas sitepack-tools-php/schemas

validate-node:
	cd sitepack-tools-node && npm install && npm run validate-examples -- ../sitepack-spec/examples

validate-php:
	cd sitepack-tools-php && composer install && php scripts/validate-examples.php ../sitepack-spec/examples
	cd sitepack-tools-php && php scripts/validate-invalid-examples.php ../sitepack-spec/examples-invalid

validate-distribution:
	cd sitepack-tools-node && npm run test-distribution-naming
	cd sitepack-tools-node && npm run test-volume-naming
	cd sitepack-tools-php && php scripts/test-distribution-naming.php
	cd sitepack-tools-node && npm run validate-volume-examples -- ../sitepack-spec/examples
	./scripts/test-signature-interop.sh

validate-all: check-schema-sync validate-node validate-php validate-distribution

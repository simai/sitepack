.PHONY: validate-node validate-php validate-volumes validate-signatures validate-mapping check-schema-sync validate-all

check-schema-sync:
	diff -rq sitepack-spec/schemas sitepack-tools-node/schemas
	diff -rq sitepack-spec/schemas sitepack-tools-php/schemas

validate-node:
	cd sitepack-tools-node && npm install && npm run validate-examples -- ../sitepack-spec/examples

validate-php:
	cd sitepack-tools-php && composer install && php scripts/validate-examples.php ../sitepack-spec/examples
	cd sitepack-tools-php && php scripts/validate-invalid-examples.php ../sitepack-spec/examples-invalid

validate-volumes:
	cd sitepack-tools-node && npm run validate-volume-examples -- ../sitepack-spec/examples

validate-signatures:
	./scripts/test-signature-interop.sh

validate-mapping:
	./scripts/test-mapping-interop.sh

validate-all: check-schema-sync validate-node validate-php validate-volumes validate-signatures validate-mapping

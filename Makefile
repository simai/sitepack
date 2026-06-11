.PHONY: validate-node validate-php check-schema-sync validate-all

check-schema-sync:
	diff -rq sitepack-spec/schemas sitepack-tools-node/schemas
	diff -rq sitepack-spec/schemas sitepack-tools-php/schemas

validate-node:
	cd sitepack-tools-node && npm install && npm run validate-examples -- ../sitepack-spec/examples

validate-php:
	cd sitepack-tools-php && composer install && php scripts/validate-examples.php ../sitepack-spec/examples

validate-all: check-schema-sync validate-node validate-php

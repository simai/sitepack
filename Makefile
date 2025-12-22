.PHONY: validate-node validate-php

validate-node:
	cd sitepack-tools-node && npm install && npm run validate-examples -- ../sitepack-spec/examples

validate-php:
	cd sitepack-tools-php && composer install && php scripts/validate-examples.php ../sitepack-spec/examples

.PHONY: build check fix lint package test test-js

build:
	npm run build

fix:
	composer fix
	npm run format

lint:
	composer phpcs

test:
	composer test

test-js:
	npm run test:js

check:
	composer check
	npm run check

package:
	npm run package

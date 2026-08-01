.PHONY: build check check-all check-docker clean coverage down fix help lint package plugin-check start start-if-not-running test test-integration test-js up

DOMAIN := wp-autofirma
IN_CONTAINER := wp-content/plugins/$(DOMAIN)

help: ## Muestra esta ayuda
	@echo "Objetivos disponibles:"
	@grep -E '^[a-z][a-z0-9_-]*:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

check-docker:
	@docker version > /dev/null 2>&1 || { \
		echo "Docker no está en marcha. Arráncalo para usar este objetivo."; exit 1; }

up: check-docker ## Levanta el entorno de WordPress (puertos 8892 y 8893)
	npx wp-env start
	@echo "Entra en http://localhost:8892/wp-admin/ (admin / password)."

start: up ## Alias de up

down: check-docker ## Para el entorno de WordPress
	npx wp-env stop

clean: check-docker ## Destruye el entorno y sus datos
	npx wp-env destroy

# Arranca el entorno solo si no responde ya, para no repetir el coste en cada
# objetivo que lo necesita.
start-if-not-running: check-docker
	@if [ "$$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8892)" = "000" ]; then \
		echo "El entorno no responde. Arrancando..."; npx wp-env start; \
	fi

build: ## Genera el bundle y copia AutoScript a build/
	npm run build

lint: ## Revisa PHP con PHPCS
	composer phpcs

fix: ## Aplica las correcciones automáticas de PHP y JavaScript
	composer fix
	npm run format

test: ## Ejecuta las pruebas de PHP
	composer test

test-js: ## Ejecuta las pruebas de JavaScript
	npm run test:js

# Las de integración necesitan WordPress de verdad, así que corren dentro del
# contenedor `tests-cli` de wp-env: base de datos, hooks, usuarios y API REST.
test-integration: start-if-not-running ## Ejecuta las pruebas de integración en wp-env
	npx wp-env run tests-cli --env-cwd=$(IN_CONTAINER) \
		./vendor/bin/phpunit --configuration=phpunit-integration.xml.dist --testdox

# Xdebug en modo cobertura solo se activa aquí: instrumentar el entorno
# habitual ralentizaría cada petición del día a día.
coverage: check-docker ## Mide la cobertura de PHP, unitaria y de integración
	composer coverage
	npx wp-env start --xdebug=coverage
	npx wp-env run tests-cli --env-cwd=$(IN_CONTAINER) \
		./vendor/bin/phpunit --configuration=phpunit-integration.xml.dist \
		--coverage-clover=coverage-integration.xml

plugin-check: start-if-not-running package ## Pasa el Plugin Check sobre el ZIP distribuible
	npx wp-env run cli wp plugin install plugin-check --activate --color
	@# Se comprueba el paquete que de verdad se instala, no el árbol de trabajo:
	@# los ficheros de desarrollo no viajan en el ZIP y revisarlos daría errores
	@# por cosas que nadie recibe nunca.
	npx wp-env run cli sh -c 'set -e; \
		cd wp-content/plugins; \
		rm -rf $(DOMAIN)-dist; \
		unzip -q $(DOMAIN)/dist/$(DOMAIN)-*.zip -d .pc-tmp; \
		mv .pc-tmp/$(DOMAIN) $(DOMAIN)-dist; \
		rm -rf .pc-tmp'
	-npx wp-env run cli wp plugin check $(DOMAIN)-dist --slug=$(DOMAIN) --ignore-warnings --color
	@npx wp-env run cli sh -c 'rm -rf wp-content/plugins/$(DOMAIN)-dist' > /dev/null

check: ## Ejecuta formato, linter, pruebas y construcción
	composer check
	npm run check

check-all: check test-integration ## Lo anterior más las pruebas de integración

package: ## Crea el ZIP distribuible en dist/
	npm run package

.DEFAULT_GOAL := help

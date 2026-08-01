.PHONY: build check check-docker check-untranslated clean down fix help lint mo package plugin-check po pot start start-if-not-running test test-js up

DOMAIN := wp-autofirma
POT    := languages/$(DOMAIN).pot
# `wp` se ejecuta con el directorio de trabajo en la raíz de WordPress, no en el
# plugin: sin esta ruta escanearía la instalación entera y escribiría el
# catálogo dentro del contenedor.
PLUGIN_IN_CONTAINER := wp-content/plugins/$(DOMAIN)
# Lo que no forma parte del plugin distribuido y no debe entrar en el catálogo.
I18N_EXCLUDE := vendor,node_modules,tests,build,dist,docs,.agents,.claude,.github

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

pot: start-if-not-running ## Regenera el catálogo de cadenas (.pot)
	@# --skip-js: las cadenas visibles se definen en PHP y llegan al navegador
	@# por wp_localize_script, así que no hay nada traducible en JavaScript. El
	@# analizador de JS, además, agota la memoria con los bundles de build/.
	npx wp-env run cli wp i18n make-pot $(PLUGIN_IN_CONTAINER) $(PLUGIN_IN_CONTAINER)/$(POT) \
		--domain=$(DOMAIN) --exclude=$(I18N_EXCLUDE) --skip-js
	@# La fecha de creación cambia en cada ejecución y ensuciaría el diff.
	@sed -i.bak '/POT-Creation-Date:/d' $(POT) && rm $(POT).bak

po: ## Actualiza los .po existentes con las cadenas nuevas del .pot
	@for po in languages/*.po; do \
		[ -e "$$po" ] || { echo "No hay ningún .po todavía."; exit 0; }; \
		echo "Actualizando $$po"; \
		msgmerge --quiet --update --backup=none "$$po" $(POT); \
	done

mo: ## Compila los .po en .mo (no se versionan: se generan al empaquetar)
	@for po in languages/*.po; do \
		[ -e "$$po" ] || { echo "No hay ningún .po que compilar."; exit 0; }; \
		echo "Compilando $$po"; \
		msgfmt "$$po" -o "$${po%.po}.mo"; \
	done

check-untranslated: ## Falla si algún .po tiene cadenas sin traducir
	@for po in languages/*.po; do \
		[ -e "$$po" ] || exit 0; \
		if [ "$$(msgattrib --untranslated "$$po" | wc -l)" -gt 0 ]; then \
			echo "Hay cadenas sin traducir en $$po"; \
			msgattrib --untranslated "$$po"; \
			exit 1; \
		fi; \
	done
	@echo "Todas las cadenas están traducidas."

check: ## Ejecuta formato, linter, pruebas y construcción
	composer check
	npm run check

package: ## Crea el ZIP distribuible en dist/
	npm run package

.DEFAULT_GOAL := help

# Desarrollo

```bash
composer install
npm install
make check
```

Para levantar WordPress:

```bash
npx @wordpress/env start
```

## Dependencia JavaScript

`assets/js/admin.js` importa `@erseco/autofirma-client`. `npm run build` genera
`build/admin.js`; el bundle se versiona porque Playground instala directamente
la rama `main`.

## Paquete

```bash
npm run package
```

El script comprueba la versión y genera el ZIP mediante `git archive` y las
reglas `export-ignore` de `.gitattributes`.

## Pruebas de integración

Las pruebas unitarias corren sin WordPress y cubren la lógica pura. Todo lo que
depende del entorno —capacidades, visibilidad de adjuntos, rutas REST, hooks de
administración— se comprueba en la suite de integración, que se ejecuta dentro
del contenedor `tests-cli` de wp-env con la suite oficial de WordPress:

```bash
make test-integration
```

Se puede acotar a un fichero o a una prueba concreta:

```bash
npx wp-env run tests-cli --env-cwd=wp-content/plugins/wp-autofirma \
    ./vendor/bin/phpunit --configuration=phpunit-integration.xml.dist \
    --filter ShortcodesTest
```

Para la cobertura hace falta instrumentación, y los contenedores no la traen
activada. `make coverage` arranca wp-env con `--xdebug=coverage` y deja dos
informes: `coverage.xml` de la suite unitaria y `coverage-integration.xml` de
la de integración. Codecov combina ambos.

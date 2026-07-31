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

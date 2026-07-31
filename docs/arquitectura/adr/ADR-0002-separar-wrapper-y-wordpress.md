---
id: ADR-0002
titulo: "Separar AutoScript de la integración WordPress"
estado: Aceptado
fecha: 2026-07-31
relacionados:
  issues: []
  prs: []
  sdds: [SDD-0001]
  adrs: []
sustituye: []
sustituido_por: []
asistencia_ia:
  herramienta: "Codex"
  modelo: "GPT-5"
---

# ADR-0002: Separar AutoScript de la integración WordPress

## Contexto

La adaptación de AutoScript es útil fuera de WordPress. El plugin debe centrarse
en permisos, medios, REST y persistencia.

## Decisión

El JavaScript del plugin depende de `@erseco/autofirma-client` y no implementa
la API callback de AutoScript. El bundle de release incorpora el wrapper, pero
no el `autoscript.js` oficial.

## Consecuencias

- Un único adaptador mantiene tipado y compatibilidad.
- El plugin conserva una responsabilidad concreta.
- La build depende de la librería y debe fijar una revisión reproducible.

## Validación

El build resuelve la importación y CI comprueba JavaScript y bundle.

## Referencias

- <https://github.com/erseco/autofirma-client>
- `assets/js/admin.js`
- `package.json`

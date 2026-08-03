---
id: SDD-0004
titulo: "Empaquetado con wp dist-archive y librería incluida"
estado: Borrador
fecha: 2026-08-03
adrs: [ADR-0005]
asistencia_ia:
  herramienta: "Claude Code"
  modelo: "Opus 5"
---

# SDD-0004: Empaquetado con wp dist-archive y librería incluida

## Resumen

El paquete se construía con `git archive` sobre `HEAD`, un directorio temporal y
un `composer install --no-dev` dentro de él, con las exclusiones en
`.gitattributes`. Este diseño lo sustituye por `wp dist-archive` leyendo
`.distignore`, y saca la librería del servidor intermedio de `vendor/` para
incluirla versionada en `includes/vendor/`, que es lo que ya hacen `wp-decker` y
`wp-documentate` con sus dependencias de ejecución.

El objetivo es que los cuatro plugins hermanos empaqueten igual. El diseño
completo está en el SDD-0002 de `wp-exelearning`.

## Objetivos

- Una sola lista de exclusiones, `.distignore`, y que sea la que de verdad se
  lee. Hasta ahora el fichero existía y no lo leía nadie.
- El mismo comando de empaquetado que los otros tres repositorios.
- Que el paquete deje de arrastrar `codecov.yml` y `phpunit-integration.xml.dist`.
- Que la librería de ejecución viaje sin depender de que Composer se ejecute en
  el destino, cosa que no ocurre nunca en una instalación de WordPress.

## Fuera de alcance

- El flujo de versionado: la versión sigue saliendo de `package.json` y el
  workflow de publicación sigue comprobando que coincide con la cabecera del
  plugin y con `Stable tag`.
- Publicar el plugin en el directorio de WordPress.org.

## Diseño

`scripts/package.sh` queda en tres pasos: comprobar que las versiones coinciden,
comprobar que `includes/vendor/autofirma-intermediate-server/src` existe, y
llamar a `wp dist-archive . dist/wp-autofirma-<version>.zip
--plugin-dirname=wp-autofirma --force`. Desaparecen el directorio temporal, el
`git show HEAD:composer.json` y el `composer install` en él.

La librería `erseco/autofirma-intermediate-server` se copia a
`includes/vendor/autofirma-intermediate-server/` mediante el script
`copy-runtime-dependencies` de `composer.json`, enganchado a `post-install-cmd`
y `post-update-cmd`. Ese árbol sí se versiona, y por eso la regla `vendor/` de
`.gitignore` pasa a estar anclada como `/vendor/`.

`Bundled_Autoloader` (`includes/class-bundled-autoloader.php`) registra un
cargador PSR-4 para el prefijo `Erseco\AutoFirma\IntermediateServer\` y se
desactiva solo si Composer ya declaró las clases, que es el caso en el
repositorio. `wp-autofirma.php` lo carga justo después de intentar el autoload
de Composer, de modo que el orden de precedencia no cambia.

`.distignore` pasa a ser la lista real, con todas las reglas de raíz ancladas
con barra inicial. El emparejado de `dist-archive` no distingue mayúsculas y las
reglas sin anclar casan a cualquier profundidad: un `vendor` suelto se llevaría
por delante `includes/vendor/`. `.gitattributes` se queda en la normalización de
finales de línea y seis reglas, y su cabecera dice que no alimenta el paquete.

## Seguridad y privacidad

No cambia ninguna frontera de confianza: el cambio quita ficheros del paquete y
mueve código que ya se distribuía. Dos consecuencias que sí conviene anotar:

- El paquete pasa a construirse desde el árbol de trabajo y no desde `HEAD`, así
  que una construcción local con cambios sin confirmar los incluiría. En el
  workflow de publicación el checkout está limpio y ya se comprueba
  `git diff --exit-code -- build`.
- La librería incluida deja de resolverse en cada publicación: lo que se
  distribuye es la copia revisada en el repositorio. Para una dependencia fijada
  en `dev-main` esto es más reproducible que lo anterior, no menos.

## Pruebas

`composer phpcs` y `composer test` siguen en verde. `includes/vendor/` queda
excluido de PHPCS porque es código de terceros vendorizado tal cual.

La comprobación propia del cambio es extraer el ZIP y cargar las clases de la
librería sin `vendor/` presente, que es exactamente la situación de una
instalación real, y comparar la lista de ficheros del paquete con la anterior.

## Despliegue

Un único PR. El workflow de publicación no cambia: ya ejecuta `composer install`
antes de `npm run package`, y ahora ese paso es además el que rellena
`includes/vendor/`.

## Riesgos

- **`--force` no vacía el ZIP anterior.** La versión 3.1 de `dist-archive`
  delega en el binario `zip`, que añade a un archivo existente. Un `npm run
  package` repetido conservaría ficheros que una regla nueva ya excluye. El
  script borra el destino antes de construir.
- **Reglas sin anclar.** Descritas arriba; mitigadas anclando todas las de raíz
  y comparando la lista de ficheros del paquete.

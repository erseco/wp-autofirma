---
id: ADR-0005
titulo: "Incluir la librería del servidor intermedio en lugar de vendor/"
estado: Propuesto
fecha: 2026-08-03
relacionados:
  issues: []
  prs: []
  sdds: [SDD-0004]
  adrs: [ADR-0004]
sustituye: []
sustituido_por: []
asistencia_ia:
  herramienta: "Claude Code"
  modelo: "Opus 5"
---

# ADR-0005: Incluir la librería del servidor intermedio en lugar de vendor/

## Contexto

El plugin necesita `erseco/autofirma-intermediate-server` en ejecución
(ADR-0004), y quien instala un plugin de WordPress no ejecuta Composer. Hasta
ahora eso se resolvía en el momento de empaquetar: `scripts/package.sh` extraía
`HEAD` a un directorio temporal, recuperaba `composer.json` y `composer.lock` de
ese mismo commit, ejecutaba `composer install --no-dev` dentro y borraba después
los manifiestos.

Funcionaba, pero dejaba a este repositorio con un mecanismo de empaquetado
propio, distinto del de los otros tres plugins hermanos, y hacía que el
contenido de `vendor/` se resolviera de nuevo en cada publicación pese a que la
dependencia está fijada en `dev-main`.

## Problema

Cómo lleva el paquete su dependencia de ejecución sin necesitar Composer en el
destino ni un procedimiento de empaquetado exclusivo de este repositorio.

## Opciones consideradas

1. **Seguir instalando en un temporal al empaquetar.** Es lo que había. Obliga a
   mantener un script propio y a que el paquete se construya desde `HEAD`, no
   desde el árbol de trabajo, lo que impide compartir el comando con los otros
   repositorios.
2. **Publicar `vendor/` completo.** Añade el cargador de Composer y los
   metadatos de instalación a un paquete que solo necesita once ficheros de
   código, y arrastra al ZIP cualquier dependencia que se añada más adelante sin
   pensarlo.
3. **Incluir la librería versionada bajo `includes/vendor/`.** Es lo que ya
   hacen `wp-decker` (`admin/vendor/mime-mail-parser`) y `wp-documentate`
   (`includes/vendor/autofirma-intermediate-server`, la misma librería que aquí)
   mediante un script de Composer enganchado a `post-install-cmd`.

## Decisión

La opción 3. `copy-runtime-dependencies` copia `src/` y `LICENSE` de la librería
a `includes/vendor/autofirma-intermediate-server/`, ese árbol se versiona, y
`Bundled_Autoloader` lo carga cuando no hay autoload de Composer. La regla
`vendor/` de `.gitignore` queda anclada a `/vendor/` para que la copia incluida
sí se pueda confirmar.

## Consecuencias

### A favor

- El empaquetado pasa a ser el mismo comando que en los otros tres repositorios
  y `scripts/package.sh` se queda en una comprobación de versiones más la
  llamada a `wp dist-archive`.
- Lo que se distribuye es la copia revisada en el repositorio, no lo que Composer
  resuelva ese día de una rama `dev-main`.
- El paquete baja de 156 KB a 120 KB: desaparecen el cargador de Composer y sus
  metadatos.

### En contra

- Actualizar la librería exige ejecutar `composer update` y confirmar el
  resultado, en lugar de que el paquete lo recoja solo. A cambio el cambio queda
  visible en el diff, que es donde debe verse.
- Hay código de terceros en el árbol. Queda excluido de PHPCS, como corresponde
  a algo vendorizado tal cual.

### Neutro

- `Intermediate_Controller::is_available()` sigue siendo la comprobación que
  decide si el servidor intermedio se anuncia; el plugin sigue firmando sin la
  librería.

## Comprobación

Extraer el ZIP y cargar las clases sin `vendor/` presente:

```bash
npm run package
unzip -q dist/wp-autofirma-*.zip -d /tmp/pkg
php -r 'define("WP_AUTOFIRMA_PATH", "/tmp/pkg/wp-autofirma/");
require WP_AUTOFIRMA_PATH."includes/class-bundled-autoloader.php";
Erseco\WPAutoFirma\Bundled_Autoloader::register();
var_dump(class_exists("Erseco\AutoFirma\IntermediateServer\IntermediateServer"));'
```

## Referencias

- [SDD-0004](../sdd/SDD-0004-empaquetado-con-dist-archive.md)
- [ADR-0004](ADR-0004-servidor-intermedio.md)
- `wp-documentate`, `includes/autofirma/class-documentate-autofirma-bundled-autoloader.php`
  — el mismo patrón con esta misma librería.

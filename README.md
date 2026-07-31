# WP AutoFirma

Plugin de WordPress para firmar PDF de la biblioteca de medios mediante
AutoScript y AutoFirma, guardando el resultado como un adjunto nuevo.

> [!WARNING]
> Proyecto independiente y no oficial. El plugin no incluye AutoFirma,
> AutoScript ni un servicio de validación de firmas.

## Estado

Versión inicial `0.1.0`. Implementa el flujo PAdES para PDF:

1. Una persona con acceso elige un PDF de la biblioteca.
2. WordPress entrega el fichero por una ruta REST autenticada.
3. `@erseco/autofirma-client` invoca AutoScript en el navegador.
4. AutoFirma devuelve el PDF firmado.
5. WordPress crea un adjunto nuevo y conserva el original.

## Probar en WordPress Playground

[Abrir la demostración](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/erseco/wp-autofirma/main/blueprint.json)

Playground usa un modo de simulación claramente indicado. Permite comprobar la
UI, permisos, REST y creación del adjunto, pero **no produce una firma
electrónica** porque el entorno web no incluye la aplicación de escritorio ni el
AutoScript oficial.

## Instalación

1. Descarga el ZIP de la última release.
2. Instálalo desde Plugins → Añadir plugin → Subir plugin.
3. Obtén `autoscript.js` desde el área oficial del Cliente @firma y sírvelo desde
   tu infraestructura.
4. Define su URL:

```php
define(
    'WP_AUTOFIRMA_AUTOSCRIPT_URL',
    'https://example.org/vendor/autoscript-1.9.js'
);
```

También se puede usar el filtro:

```php
add_filter(
    'wp_autofirma_autoscript_url',
    static function () {
        return 'https://example.org/vendor/autoscript-1.9.js';
    }
);
```

## Uso

Abre Medios → Biblioteca, usa la vista de lista y pulsa «Firmar con AutoFirma»
en un PDF. El plugin no sobrescribe el original.

## Seguridad y privacidad

- Las rutas REST exigen sesión, nonce y capacidades.
- Solo se admiten PDF y hay límites de tamaño configurables.
- El plugin no añade telemetría.
- El documento se procesa en el navegador y en AutoFirma, pero el resultado se
  envía a WordPress para guardarlo.
- La biblioteca de medios estándar suele ser pública: conocer la URL puede
  permitir descargar el fichero sin pasar por WordPress.
- Guardar una firma no equivale a validarla. Para decisiones jurídicas hay que
  validar documento, firma, certificado, cadena de confianza y revocación en un
  servicio de confianza.

Consulta la [documentación de seguridad](docs/seguridad.md) antes de usarlo con
expedientes o datos personales.

## Desarrollo

```bash
composer install
npm install
make check
```

La compilación incluye `@erseco/autofirma-client` en `build/admin.js`. El
artefacto compilado se conserva en el repositorio para que Playground pueda
cargar `main`; la CI comprueba que no quede desactualizado.

## Releases

Los tags `v0.1.x` ejecutan tests PHP y JavaScript, PHPCS, generan
`wp-autofirma-0.1.x.zip` y crean una GitHub Release con el paquete.

## Documentación

La documentación Zensical se publica en
<https://erseco.github.io/wp-autofirma/>.

## Licencia

GPL-2.0-or-later. AutoFirma y AutoScript mantienen sus propias licencias y
marcas.

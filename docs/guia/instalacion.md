# Instalación

Instala el ZIP de una release. No hace falta configurar nada más: el plugin
incluye `autoscript.js` en `build/autoscript.js`, aportado por el paquete
`@erseco/autofirma-client`, que lo fija a un tag concreto del repositorio
oficial y verifica su `sha256` antes de empaquetarlo.

Si tu organización prefiere servir su propia copia, define la constante y tendrá
prioridad sobre la incluida:

```php
define(
    'WP_AUTOFIRMA_AUTOSCRIPT_URL',
    'https://example.org/vendor/autoscript-1.9.js'
);
```

En ese caso no enlaces una URL de terceros sin revisar disponibilidad,
integridad, licencia y política de actualización.

## Requisitos

- WordPress 6.5 o posterior.
- PHP 7.4 o posterior.
- AutoFirma 1.9 instalada en el equipo cliente.
- AutoScript 1.9 disponible en la página de administración.
- HTTPS en el sitio real.

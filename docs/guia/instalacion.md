# Instalación

Instala el ZIP de una release y define la ubicación del AutoScript oficial:

```php
define(
    'WP_AUTOFIRMA_AUTOSCRIPT_URL',
    'https://example.org/vendor/autoscript-1.9.js'
);
```

No enlaces directamente una URL de terceros sin revisar disponibilidad,
integridad, licencia y política de actualización. Lo normal es servir una copia
controlada desde la misma organización.

## Requisitos

- WordPress 6.5 o posterior.
- PHP 7.4 o posterior.
- AutoFirma 1.9 instalada en el equipo cliente.
- AutoScript 1.9 disponible en la página de administración.
- HTTPS en el sitio real.

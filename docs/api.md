# API REST y hooks

## REST

### `GET /wp-json/wp-autofirma/v1/documents/{id}`

Devuelve el PDF en Base64, nombre, tamaño, MIME y SHA-256. Requiere poder leer el
adjunto.

### `POST /wp-json/wp-autofirma/v1/signatures`

```json
{
  "originalAttachmentId": 123,
  "filename": "resolucion-firmado.pdf",
  "signature": "JVBERi0x..."
}
```

Requiere `upload_files` y acceso al original.

## Acción

```php
add_action(
    'wp_autofirma_signed',
    static function ( $signed_id, $original_id ) {
        // Integración propia.
    },
    10,
    2
);
```

## Filtros

- `wp_autofirma_autoscript_url`
- `wp_autofirma_max_document_size`
- `wp_autofirma_max_signed_size`

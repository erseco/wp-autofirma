# Seguridad y privacidad

## Controles existentes

- Capacidad `read_post` para leer el original.
- Capacidades `read_post` y `upload_files` para guardar el resultado.
- Autenticación y nonce REST.
- Base64 estricto.
- Solo MIME PDF en la primera versión.
- Límites configurables de tamaño.
- Original inmutable y adjunto de resultado separado.

## Almacenamiento

El plugin **sí almacena** el PDF firmado en WordPress. Registra:

- ID del adjunto original.
- ID de la cuenta que completó el flujo.
- fecha UTC;
- SHA-256 del resultado.

No almacena claves privadas. Tampoco envía documentos a un servidor mantenido
por este proyecto.

## Biblioteca pública

La biblioteca estándar no es un almacén privado. Nginx o Apache suelen servir
directamente `wp-content/uploads`; una URL conocida puede eludir las capacidades
de WordPress. Para expedientes o datos personales usa almacenamiento privado y
una descarga controlada por autorización.

## Validación

El navegador no es una frontera de confianza. Antes de aceptar efectos jurídicos
debes validar en servidor:

- integridad y formato de la firma;
- correspondencia con el original;
- certificado y cadena de confianza;
- vigencia y revocación;
- política de firma aplicable.

# Instrucciones para agentes

## Finalidad

WP AutoFirma integra la biblioteca de medios con
`@erseco/autofirma-client`. WordPress gestiona acceso y persistencia; la
librería adapta AutoScript; AutoFirma realiza la operación local.

## Idioma y estándares

- Código, clases, métodos, variables y contratos en inglés.
- Comentarios, docblocks, README, documentación y textos de interfaz en español.
- PHP con cuatro espacios, sin tabuladores, y WordPress Coding Standards según
  `.phpcs.xml.dist`.
- JavaScript, JSON y YAML con dos espacios.
- Escapa toda salida, sanea entradas y aplica `wp_unslash()` antes de sanear
  superglobales.
- Usa APIs de WordPress para scripts, estilos, uploads, REST, capacidades y
  nonces.
- Las cadenas visibles deben ser traducibles con el dominio `wp-autofirma`.

## Límites de arquitectura

- `admin/`: UI de biblioteca y carga de recursos.
- `includes/class-rest-controller.php`: frontera HTTP y permisos.
- `includes/class-document-service.php`: lectura del original.
- `includes/class-signature-repository.php`: creación del adjunto firmado.
- `includes/class-signature-data.php`: transformaciones puras.
- `assets/js/`: orquestación del navegador.
- `@erseco/autofirma-client`: única capa que adapta AutoScript.

No dupliques el wrapper en el plugin. No sobrescribas el adjunto original. No
confíes en metadatos de certificado enviados por JavaScript.

## Seguridad

- Mantén las comprobaciones `read_post` y `upload_files`.
- Toda mutación REST requiere nonce de WordPress.
- Conserva límites de tamaño y Base64 estricto.
- No afirmes que la biblioteca de medios es privada.
- El modo `WP_AUTOFIRMA_DEMO_MODE` solo se usa en Playground y nunca genera una
  firma real.
- Los cambios de almacenamiento, permisos o API REST necesitan SDD y ADR.

## Pruebas

Antes de cualquier publicación ejecuta:

```bash
composer install
npm install
make check
git diff --exit-code -- build
```

- PHPUnit cubre lógica PHP pura; añade integración con WordPress cuando entren
  contratos que no puedan probarse sin el entorno.
- Vitest cubre utilidades JavaScript.
- PHPCS debe terminar sin errores.
- El build versionado debe coincidir con las fuentes.

## Releases

- `package.json`, la cabecera de `wp-autofirma.php` y `readme.txt` deben tener la
  misma versión.
- Los tags tienen forma `vX.Y.Z`.
- `release.yml` valida, empaqueta y crea la release.
- El ZIP no incluye tests, fuentes de documentación ni dependencias de
  desarrollo.

## Documentación de arquitectura

Consulta `docs/arquitectura/adr/records.md` y
`docs/arquitectura/sdd/records.md`.

- ADR para decisiones duraderas; IDs correlativos y registros aceptados
  históricos.
- SDD para cambios amplios, de seguridad, REST, almacenamiento o distribución.
- Documentación y registros en español.

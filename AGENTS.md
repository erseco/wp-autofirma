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
- `includes/class-signature-detector.php`: detección estructural de firmas, sin
  WordPress y sin validar nada.
- `includes/class-signature-index.php`: caché en post meta de esa detección.
- `includes/class-signature-presenter.php`: cómo se describe una firma.
- `includes/class-shortcodes.php`: publicación en el frontal, con permisos.
- `includes/class-intermediate-controller.php`: rutas del servidor intermedio y
  emisión de los tokens que las autorizan.
- `includes/class-transient-store.php`: almacenamiento en tránsito sobre
  transients.
- `admin/class-media-library.php`: columna y ficha de la biblioteca.
- `assets/js/`: orquestación del navegador.
- `@erseco/autofirma-client`: única capa que adapta AutoScript.

No dupliques el wrapper en el plugin. No sobrescribas el adjunto original. No
confíes en metadatos de certificado enviados por JavaScript.

Detectar una firma no es validarla. No presentes nunca lo detectado como firma
verificada: se dice «firmante declarado» y «fecha declarada», y la advertencia
acompaña a todo lo que se muestre.

## Seguridad

- Mantén las comprobaciones `read_post` y `upload_files`.
- Toda mutación REST requiere nonce de WordPress.
- Conserva límites de tamaño y Base64 estricto.
- Las rutas del servidor intermedio son públicas por necesidad: AutoFirma no
  lleva la sesión de WordPress. Lo que autoriza es el token de la ruta. No
  aceptes ni entregues datos sin sesión válida, y no reimplementes el protocolo:
  vive en `erseco/autofirma-intermediate-server`.
- No afirmes que la biblioteca de medios es privada.
- La demostración de Playground firma de verdad con la AutoFirma de quien la
  usa. No introduzcas simulaciones que devuelvan el documento sin firmar: un
  resultado que parece firmado sin serlo es peor que un error.
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

## Skills

Los procedimientos recurrentes viven como skills en `.agents/skills/`, la ruta
que leen Codex y Grok Build. Claude Code y GitHub Copilot buscan en
`.claude/skills/` y `.github/skills/`, que contienen **enlaces simbólicos** a
esos mismos directorios, no copias. Al añadir una skill, créala en
`.agents/skills/` y enlázala desde las otras dos; nunca dupliques el `SKILL.md`.

Consulta la que corresponda antes de tocar hooks y UI de administración, la API
REST, el `readme.txt` del directorio de plugins o el Blueprint de Playground.

Las skills actuales son **de terceros** y se copian tal cual, por lo que están
excluidas de Prettier: reformatearlas divergiría de su origen y complicaría
actualizarlas.

| Skill                                                                                 | Origen                                     |
| ------------------------------------------------------------------------------------- | ------------------------------------------ |
| `wp-plugin-development`, `wp-rest-api`, `wp-plugin-directory-guidelines`, `blueprint` | `WordPress/agent-skills`, GPL-2.0-or-later |
| `security-audit`                                                                      | `ateeducacion/wp-decker`                   |

## Documentación de arquitectura

Consulta `docs/arquitectura/adr/records.md` y
`docs/arquitectura/sdd/records.md`.

- ADR para decisiones duraderas; IDs correlativos y registros aceptados
  históricos.
- SDD para cambios amplios, de seguridad, REST, almacenamiento o distribución.
- Documentación y registros en español.

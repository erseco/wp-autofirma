---
id: SDD-0002
titulo: "Señalización de firmas y shortcodes"
estado: Implementado
fecha: 2026-08-01
adrs: [ADR-0003]
asistencia_ia:
  herramienta: "Claude Code"
  modelo: "Opus 5"
---

# SDD-0002: Señalización de firmas y shortcodes

## Resumen

Marcar en la biblioteca de medios qué adjuntos llevan firma digital, describir
esa firma en la ficha del adjunto y permitir publicar la misma información en el
contenido del sitio mediante shortcodes.

## Objetivos

- Distinguir de un vistazo lo firmado en la vista de lista.
- Describir la firma donde ya se mira el adjunto, sin pantalla nueva.
- Publicar el estado en el frontal sin filtrar datos personales.
- No pagar el coste de leer ficheros más de una vez por adjunto.
- Dejar claro, siempre, que no se ha validado nada.

## Fuera de alcance

Validación criptográfica, cadena de confianza, revocación, sellos de tiempo,
marcado en la vista de cuadrícula —que no admite columnas— y reescaneo masivo de
bibliotecas existentes.

## Diseño

Cinco piezas, cada una con una responsabilidad:

| Componente            | Responsabilidad                                                                    |
| --------------------- | ---------------------------------------------------------------------------------- |
| `Signature_Detector`  | Reconoce la estructura en los bytes. Puro, sin WordPress.                          |
| `Signature_Index`     | Cachea el resultado en post meta y decide qué merece leerse.                       |
| `Signature_Presenter` | Convierte el resultado en texto e icono, igual en los tres sitios donde se enseña. |
| `Shortcodes`          | Publica el estado en el frontal, comprobando antes quién mira.                     |
| `Media_Library`       | Añade la columna de la lista y los datos de la ficha.                              |

`Signature_Index` no lee cualquier fichero: descarta por tipo MIME y extensión
lo que nunca llevará firma reconocible, de modo que un vídeo no provoca una
lectura inútil.

## Seguridad y privacidad

El nombre de quien firma es un dato personal, y los shortcodes se pintan donde
puede mirar cualquiera. Ninguno enseña nada sin comprobar antes que quien mira
puede ver ese adjunto.

Esa comprobación no puede ser solo `current_user_can( 'read_post' )`. Esa
capacidad acaba mapeando a `read`, y quien visita el sitio sin haber entrado no
tiene ninguna capacidad: un adjunto perfectamente público daría negativo. Se usa
`is_post_publicly_viewable()`, que es la comprobación del propio núcleo y
resuelve bien los adjuntos —público el que no cuelga de nada, no público el que
cuelga de un borrador—, y se mantiene la capacidad para quien sí ha entrado y
puede ver material que no es público. El filtro
`wp_autofirma_can_read_signature` permite endurecerlo en instalaciones que
sirven `uploads` tras autenticación.

El firmante se extrae del certificado embebido en el fichero, nunca de lo que
declare el navegador, en coherencia con la regla que ya seguía el plugin.

Toda presentación incluye la advertencia de que la firma no se ha validado.

## Pruebas

- `tests/php/SignatureDetectorTest.php`: 17 pruebas sobre los formatos, los
  falsos positivos y los límites de lectura.
- Verificación manual en `wp-env` sobre WordPress real: escaneo automático al
  subir, camino perezoso para adjuntos anteriores, registro de la columna,
  campos de la ficha y los tres shortcodes como visitante anónimo.
- Los ficheros de prueba se generan con `tests/php/fixtures/generate.sh` a
  partir de un certificado de usar y tirar: en el repositorio no entra ninguna
  firma real, porque llevan nombre y DNI de personas.

## Riesgos

Que alguien lea el ✅ como «firma válida». Se mitiga con el texto de la
advertencia y con el vocabulario: se habla de «firmante declarado» y de «fecha
declarada», nunca de firma verificada.

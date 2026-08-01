# Detección de firmas

El plugin marca en la biblioteca de medios qué documentos llevan firma digital
y describe lo que esa firma declara.

## Qué hace y qué no hace

Comprueba que el fichero **contiene** una estructura de firma y lee lo que esa
estructura dice de sí misma.

No valida. En concreto, **no** comprueba:

- que el documento no se haya modificado después de firmarlo,
- que el certificado sea de fiar o siga vigente,
- que no esté revocado,
- que la fecha sea cierta.

La consecuencia importante: un documento manipulado tras firmarlo **sigue
apareciendo como firmado**. La marca responde a «¿está firmado?», no a «¿es
válida la firma?».

Para validar de verdad hay que usar un servicio especializado. En España,
[VALIDe](https://valide.redsara.es/) permite comprobar un documento firmado.

Por eso, además, lo que se muestra se llama **firmante declarado** y **fecha
declarada**: son lo que el documento afirma, no algo verificado. La fecha, en
particular, la pone el reloj de quien firmó, no una autoridad de sellado.

## Dónde se ve

**En la vista de lista** de la biblioteca (`Medios → Biblioteca`, icono de
lista) aparece una columna «Firma» con un ✅ en los documentos firmados y un
guion en los demás.

La vista de cuadrícula no admite columnas, así que ahí no hay marca.

**En la ficha del adjunto** —tanto en la ventana de detalles como en la
pantalla de edición— aparece un bloque «Firma digital» con el formato, el
firmante, la caducidad del certificado y la fecha declarada.

**En el contenido del sitio**, mediante [shortcodes](shortcodes.md).

## Formatos que reconoce

| Formato                        | Se reconoce por                                         |
| ------------------------------ | ------------------------------------------------------- |
| PAdES (PDF firmado)            | El `/ByteRange` de cada firma incorporada               |
| CAdES suelto (`.csig`, `.p7s`) | La estructura PKCS#7 signedData, en DER o en Base64     |
| XAdES (`.xsig`, XML firmado)   | El espacio de nombres XMLDSig, y el de ETSI si es XAdES |
| OpenDocument                   | La entrada `META-INF/documentsignatures.xml`            |
| Office Open XML                | Las entradas `_xmlsignatures/`                          |

En los PDF distingue además el perfil: `ETSI.CAdES.detached`, que es el que
emite AutoFirma, del `adbe.pkcs7.detached` clásico.

El firmante se saca del certificado que viaja **dentro del fichero**. De la
cadena que acompaña a la firma se descartan las autoridades y se conserva el
certificado de quien firma.

## Cuándo se examina cada fichero

Leer un fichero cuesta poco, pero hacerlo en cada pintado de la lista sería una
lectura de disco por fila y por visita. El resultado se calcula una vez y se
guarda en post meta:

- **Al subir el adjunto**, de forma automática.
- **La primera vez que se mira**, para los que ya estaban antes de instalar el
  plugin.

Medido sobre WordPress real: 3,42 ms la primera consulta y 0,09 ms las
siguientes. No hay reescaneo masivo de la biblioteca; cada documento se examina
cuando le toca.

Solo se leen los ficheros que pueden llevar una firma reconocible. Un vídeo o
una imagen no provocan ninguna lectura.

Si el algoritmo de detección mejora, `Signature_Detector::VERSION` sube y los
resultados guardados se recalculan solos.

## Datos guardados

| Meta                      | Contenido                                                |
| ------------------------- | -------------------------------------------------------- |
| `_wp_autofirma_is_signed` | `1` o `0`. Consultable con `meta_query`.                 |
| `_wp_autofirma_signature` | El detalle completo: formato, firmantes, fecha, versión. |

Son dos porque dentro de un array serializado no se puede buscar.

## Filtros

```php
// Retirar la columna y el bloque de la ficha.
add_filter( 'wp_autofirma_show_signature_status', '__return_false' );

// Cambiar cuánto se lee de golpe de un fichero. Por encima de este tamaño solo
// se leen una ventana inicial y otra final, y el firmante puede no extraerse.
add_filter(
    'wp_autofirma_max_scan_size',
    static function () {
        return 32 * MB_IN_BYTES;
    }
);

// Decidir quién ve el estado de firma en el frontal. Útil si `uploads` se sirve
// tras una autenticación: ser públicamente visible en WordPress no implica que
// el fichero lo sea.
add_filter(
    'wp_autofirma_can_read_signature',
    static function ( $allowed, $attachment_id ) {
        return is_user_logged_in() && $allowed;
    },
    10,
    2
);
```

## Volver a examinar un adjunto

```php
( new Erseco\WPAutoFirma\Signature_Index() )->scan( $attachment_id );
```

O, desde WP-CLI, para toda la biblioteca:

```bash
wp eval '
$index = new Erseco\WPAutoFirma\Signature_Index();
foreach ( get_posts( array( "post_type" => "attachment", "posts_per_page" => -1, "fields" => "ids" ) ) as $id ) {
    $index->scan( $id );
}'
```

# Shortcodes

Publican en el contenido del sitio lo que el plugin sabe de la firma de un
adjunto. Se basan en la [detección de firmas](firmas-detectadas.md), con sus
mismos límites: dicen si un documento **está firmado**, no si la firma es
válida.

## Quién ve qué

Los shortcodes se pintan en el frontal, donde puede mirar cualquiera, y el
nombre de quien firma es un dato personal.

Por eso ninguno enseña nada sin comprobar antes que quien mira puede ver ese
adjunto. Si no puede, el shortcode **no pinta nada**: ni un aviso ni un hueco,
que delatarían la existencia del documento. Lo mismo si el identificador no
existe o no es un adjunto.

Un adjunto se considera visible si WordPress lo considera públicamente visible
—los que no cuelgan de nada lo son; los que cuelgan de un borrador, no— o si
quien mira tiene permiso para leerlo. El filtro
`wp_autofirma_can_read_signature` permite endurecerlo.

## `[autofirma_signature_status]`

Dice si un documento está firmado, en una línea.

| Atributo   | Por omisión            | Para qué                        |
| ---------- | ---------------------- | ------------------------------- |
| `id`       | El adjunto en curso    | Identificador del adjunto.      |
| `signed`   | «Firmado digitalmente» | Texto propio cuando hay firma.  |
| `unsigned` | «Sin firma digital»    | Texto propio cuando no la hay.  |
| `icon`     | `yes`                  | `no` para prescindir del icono. |

```text
[autofirma_signature_status id="42"]
[autofirma_signature_status id="42" signed="Documento firmado ante notario" unsigned="Pendiente de firma"]
[autofirma_signature_status id="42" icon="no"]
```

## `[autofirma_signature_info]`

Describe la firma: formato, firmante declarado, caducidad del certificado y
fecha declarada.

| Atributo     | Por omisión         | Para qué                                          |
| ------------ | ------------------- | ------------------------------------------------- |
| `id`         | El adjunto en curso | Identificador del adjunto.                        |
| `disclaimer` | `yes`               | `no` para omitir la advertencia de no validación. |

```text
[autofirma_signature_info id="42"]
```

Sale una lista de definición (`<dl class="wp-autofirma-info">`) que se puede
dar estilo desde el tema.

Quitar la advertencia con `disclaimer="no"` solo tiene sentido si el aviso ya
está en otro sitio de la página. Sin él, es fácil que quien lea la ficha crea
que alguien ha comprobado la firma.

## `[autofirma_signed_documents]`

Lista los adjuntos con firma detectada, del más reciente al más antiguo. De la
lista se retira lo que quien mira no puede ver.

| Atributo | Por omisión | Para qué                                |
| -------- | ----------- | --------------------------------------- |
| `count`  | `10`        | Cuántos listar, hasta un máximo de 100. |

```text
[autofirma_signed_documents count="5"]
```

## Usarlos desde PHP

```php
echo do_shortcode( '[autofirma_signature_status id="42"]' );
```

O directamente, sin pasar por el shortcode:

```php
$index  = new Erseco\WPAutoFirma\Signature_Index();
$status = $index->status( 42 );

if ( $status['signed'] ) {
    printf(
        'Firmado en formato %s por %s.',
        esc_html( $status['format'] ),
        esc_html( $status['signers'][0]['name'] ?? 'desconocido' )
    );
}
```

`status()` devuelve `signed`, `known`, `format`, `profile`, `signatures`,
`signers` y `signed_at`. Cada firmante trae `name`, `issuer`, `serial`,
`valid_from` y `valid_to`.

## Consultar documentos firmados

La marca es consultable, así que se puede filtrar con `meta_query`:

```php
$firmados = new WP_Query(
    array(
        'post_type'  => 'attachment',
        'post_status' => 'inherit',
        'meta_key'   => '_wp_autofirma_is_signed',
        'meta_value' => '1',
    )
);
```

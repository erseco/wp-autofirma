<?php
/**
 * Presentación del estado de firma.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

/**
 * Convierte lo que devuelve el detector en algo que se pueda enseñar.
 *
 * Vive aparte porque lo comparten la columna de la biblioteca, la ficha del
 * adjunto y los shortcodes: la misma firma debe describirse igual en los tres
 * sitios.
 */
final class Signature_Presenter {

    /**
     * Devuelve la advertencia que acompaña a todo lo que se muestre.
     *
     * @return string
     */
    public static function disclaimer() {
        return __(
            'Se ha comprobado que el documento contiene una firma digital y se muestra lo que esa firma declara. No se ha validado: no se ha verificado la integridad del documento ni la validez del certificado.',
            'wp-autofirma'
        );
    }

    /**
     * Resume el estado en una frase.
     *
     * @param array $status Resultado del detector.
     * @return string
     */
    public static function summary( array $status ) {
        if ( empty( $status['known'] ) ) {
            return __( 'Formato no analizable', 'wp-autofirma' );
        }

        if ( empty( $status['signed'] ) ) {
            return __( 'Sin firma digital', 'wp-autofirma' );
        }

        if ( (int) $status['signatures'] > 1 ) {
            return sprintf(
                /* translators: %d: número de firmas encontradas. */
                __( 'Firmado digitalmente (%d firmas)', 'wp-autofirma' ),
                (int) $status['signatures']
            );
        }

        return __( 'Firmado digitalmente', 'wp-autofirma' );
    }

    /**
     * Devuelve el icono que representa el estado.
     *
     * El icono es decorativo, así que va acompañado de un texto para lectores
     * de pantalla. Cuando el icono ya lleva al lado un texto visible que dice
     * lo mismo, ese acompañamiento sobra: se anunciaría dos veces.
     *
     * @param array $status   Resultado del detector.
     * @param bool  $labelled Si se añade el texto para lectores de pantalla.
     * @return string HTML seguro.
     */
    public static function icon( array $status, $labelled = true ) {
        $glyph = empty( $status['signed'] )
            ? '<span class="wp-autofirma-mark wp-autofirma-mark--plain" aria-hidden="true">—</span>'
            : '<span class="wp-autofirma-mark wp-autofirma-mark--signed dashicons dashicons-yes-alt" aria-hidden="true"></span>';

        if ( ! $labelled ) {
            return $glyph;
        }

        return $glyph . sprintf(
            '<span class="screen-reader-text">%s</span>',
            esc_html( self::summary( $status ) )
        );
    }

    /**
     * Descompone el estado en pares de etiqueta y valor.
     *
     * @param array $status Resultado del detector.
     * @return array<int, array{label: string, value: string}>
     */
    public static function rows( array $status ) {
        $rows = array(
            array(
                'label' => __( 'Estado', 'wp-autofirma' ),
                'value' => self::summary( $status ),
            ),
        );

        if ( empty( $status['signed'] ) ) {
            return $rows;
        }

        $format = (string) $status['format'];

        if ( '' !== (string) $status['profile'] ) {
            $format = (string) $status['profile'];
        }

        if ( '' !== $format ) {
            $rows[] = array(
                'label' => __( 'Formato', 'wp-autofirma' ),
                'value' => $format,
            );
        }

        foreach ( $status['signers'] as $signer ) {
            if ( '' === (string) $signer['name'] ) {
                continue;
            }

            $rows[] = array(
                'label' => __( 'Firmante declarado', 'wp-autofirma' ),
                'value' => '' !== (string) $signer['issuer']
                    ? sprintf(
                        /* translators: 1: titular del certificado; 2: autoridad que lo emitió. */
                        __( '%1$s (emitido por %2$s)', 'wp-autofirma' ),
                        $signer['name'],
                        $signer['issuer']
                    )
                    : $signer['name'],
            );

            if ( $signer['valid_to'] > 0 ) {
                $rows[] = array(
                    'label' => __( 'El certificado caduca', 'wp-autofirma' ),
                    'value' => date_i18n( (string) get_option( 'date_format' ), (int) $signer['valid_to'] ),
                );
            }
        }

        $signed_at = self::timestamp( (string) $status['signed_at'] );

        if ( 0 !== $signed_at ) {
            $rows[] = array(
                'label' => __( 'Fecha declarada de firma', 'wp-autofirma' ),
                'value' => date_i18n(
                    (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ),
                    $signed_at
                ),
            );
        }

        return $rows;
    }

    /**
     * Traduce la fecha que declara la firma a marca de tiempo.
     *
     * La fecha la pone el reloj de quien firma, no una autoridad de sellado, de
     * ahí que se presente como «declarada».
     *
     * @param string $raw Fecha tal cual aparece en el documento.
     * @return int Marca de tiempo, o cero si no se entiende.
     */
    private static function timestamp( $raw ) {
        if ( '' === $raw ) {
            return 0;
        }

        $parsed = strtotime( $raw );

        return false === $parsed ? 0 : (int) $parsed;
    }
}

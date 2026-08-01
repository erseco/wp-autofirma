<?php
/**
 * Señalización de firmas en la biblioteca de medios.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use WP_Post;

/**
 * Marca los adjuntos firmados y describe su firma en la ficha.
 */
final class Media_Library {

    /**
     * Índice de firmas.
     *
     * @var Signature_Index
     */
    private $index;

    /**
     * Construye la integración.
     *
     * @param Signature_Index $index Índice de firmas.
     */
    public function __construct( Signature_Index $index ) {
        $this->index = $index;
    }

    /**
     * Añade la columna de firma a la vista de lista.
     *
     * @param array<string, string> $columns Columnas existentes.
     * @return array<string, string>
     */
    public function add_column( $columns ) {
        if ( ! $this->is_enabled() ) {
            return $columns;
        }

        $columns['wp_autofirma_signature'] = __( 'Firma', 'wp-autofirma' );

        return $columns;
    }

    /**
     * Pinta el contenido de la columna.
     *
     * @param string $column        Columna que se pinta.
     * @param int    $attachment_id ID del adjunto.
     * @return void
     */
    public function render_column( $column, $attachment_id ) {
        if ( 'wp_autofirma_signature' !== $column ) {
            return;
        }

        $status = $this->index->status( $attachment_id );

        printf(
            '<span title="%1$s">%2$s</span>',
            esc_attr( Signature_Presenter::summary( $status ) ),
            wp_kses_post( Signature_Presenter::icon( $status ) )
        );
    }

    /**
     * Añade los datos de la firma a la ficha del adjunto.
     *
     * Aparece tanto en la ventana de detalles de la vista de cuadrícula como en
     * la pantalla de edición del adjunto, que es donde la vista de cuadrícula
     * puede enseñar algo: esa vista no admite columnas.
     *
     * @param array<string, array> $fields Campos existentes.
     * @param WP_Post              $post   Adjunto.
     * @return array<string, array>
     */
    public function add_attachment_fields( $fields, $post ) {
        if ( ! $this->is_enabled() ) {
            return $fields;
        }

        $status = $this->index->status( $post->ID );

        if ( empty( $status['known'] ) ) {
            return $fields;
        }

        $lines = array();

        foreach ( Signature_Presenter::rows( $status ) as $row ) {
            $lines[] = sprintf(
                '<strong>%1$s:</strong> %2$s',
                esc_html( $row['label'] ),
                esc_html( $row['value'] )
            );
        }

        $html = implode( '<br />', $lines );

        if ( ! empty( $status['signed'] ) ) {
            $html .= sprintf(
                '<br /><em class="description">%s</em>',
                esc_html( Signature_Presenter::disclaimer() )
            );
        }

        $fields['wp_autofirma_signature'] = array(
            'label' => __( 'Firma digital', 'wp-autofirma' ),
            'input' => 'html',
            'html'  => $html,
        );

        return $fields;
    }

    /**
     * Añade el estilo mínimo de la columna.
     *
     * @param string $hook_suffix Pantalla actual.
     * @return void
     */
    public function enqueue_styles( $hook_suffix ) {
        if ( 'upload.php' !== $hook_suffix || ! $this->is_enabled() ) {
            return;
        }

        wp_register_style( 'wp-autofirma-media-library', false, array( 'dashicons' ), WP_AUTOFIRMA_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- La versión se pasa en el cuarto argumento.
        wp_enqueue_style( 'wp-autofirma-media-library' );
        wp_add_inline_style(
            'wp-autofirma-media-library',
            '.column-wp_autofirma_signature{width:5.5em;text-align:center}'
            . '.wp-autofirma-mark--signed{color:#008a20}'
            . '.wp-autofirma-mark--plain{color:#8c8f94}'
        );
    }

    /**
     * Indica si la señalización está activa.
     *
     * @return bool
     */
    private function is_enabled() {
        /**
         * Permite retirar la señalización de firmas de la biblioteca.
         *
         * @param bool $enabled Si se muestra la columna y la ficha.
         */
        return (bool) apply_filters( 'wp_autofirma_show_signature_status', true );
    }
}

<?php
/**
 * Persistencia de documentos firmados.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use RuntimeException;

/**
 * Crea un adjunto nuevo sin sobrescribir el documento original.
 */
final class Signature_Repository {

    /**
     * Guarda el PDF firmado en la biblioteca de medios.
     *
     * @param int    $original_id ID del documento original.
     * @param string $filename    Nombre solicitado.
     * @param string $encoded     PDF firmado en Base64.
     * @return int
     *
     * @throws RuntimeException Cuando no se puede guardar el fichero.
     */
    public function save( $original_id, $filename, $encoded ) {
        $contents = Signature_Data::decode( $encoded );
        $max_size = (int) apply_filters(
            'wp_autofirma_max_signed_size',
            15 * MB_IN_BYTES
        );

        if ( strlen( $contents ) > $max_size ) {
            throw new RuntimeException( 'El documento firmado supera el tamaño permitido.' );
        }

        $safe_name = sanitize_file_name(
            Signature_Data::signed_filename( $filename )
        );
        $upload    = wp_upload_bits( $safe_name, null, $contents );

        if ( ! empty( $upload['error'] ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensaje de excepción, no salida: el controlador REST lo serializa como JSON.
            throw new RuntimeException( (string) $upload['error'] );
        }

        // El cuarto argumento pide el error como `WP_Error`. Sin él, un fallo se
        // devuelve como cero, la comprobación de abajo no lo ve y el adjunto
        // inexistente se anuncia como firma guardada: peor que un error.
        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => 'application/pdf',
                'post_title'     => sanitize_text_field(
                    pathinfo( $safe_name, PATHINFO_FILENAME )
                ),
                'post_status'    => 'inherit',
            ),
            $upload['file'],
            0,
            true
        );

        if ( is_wp_error( $attachment_id ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensaje de excepción, no salida: el controlador REST lo serializa como JSON.
            throw new RuntimeException( $attachment_id->get_error_message() );
        }

        update_post_meta(
            $attachment_id,
            '_wp_autofirma_original_attachment_id',
            $original_id
        );
        update_post_meta(
            $attachment_id,
            '_wp_autofirma_signed_by',
            get_current_user_id()
        );
        update_post_meta(
            $attachment_id,
            '_wp_autofirma_signed_at',
            current_time( 'mysql', true )
        );
        update_post_meta(
            $attachment_id,
            '_wp_autofirma_document_sha256',
            Signature_Data::hash( $contents )
        );

        /**
         * Se ejecuta después de guardar un documento firmado.
         *
         * @param int $attachment_id ID del documento firmado.
         * @param int $original_id   ID del documento original.
         */
        do_action( 'wp_autofirma_signed', $attachment_id, $original_id );

        return $attachment_id;
    }
}

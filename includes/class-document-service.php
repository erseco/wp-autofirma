<?php
/**
 * Lectura controlada de documentos de la biblioteca.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use RuntimeException;

/**
 * Prepara documentos para el cliente AutoFirma.
 */
final class Document_Service {

	/**
	 * Devuelve un documento PDF codificado para la API REST.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return array<string, int|string>
	 *
	 * @throws RuntimeException Cuando el adjunto no puede procesarse.
	 */
	public function get_document( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			throw new RuntimeException( 'No se puede leer el documento solicitado.' );
		}

		if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			throw new RuntimeException( 'La primera versión solo admite documentos PDF.' );
		}

		$size     = filesize( $path );
		$max_size = (int) apply_filters(
			'wp_autofirma_max_document_size',
			10 * MB_IN_BYTES
		);

		if ( false === $size || $size > $max_size ) {
			throw new RuntimeException( 'El documento supera el tamaño permitido.' );
		}

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Ruta local del adjunto obtenida de WordPress, no una URL remota.
		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			throw new RuntimeException( 'No se pudo cargar el documento.' );
		}

		return array(
			'attachmentId' => $attachment_id,
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- El transporte de AutoScript es Base64; no se ofusca código.
			'data'         => base64_encode( $contents ),
			'filename'     => wp_basename( $path ),
			'mimeType'     => 'application/pdf',
			'size'         => $size,
			'sha256'       => Signature_Data::hash( $contents ),
		);
	}
}

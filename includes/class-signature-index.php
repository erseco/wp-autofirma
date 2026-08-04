<?php
/**
 * Caché del estado de firma de cada adjunto.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

/**
 * Guarda en post meta lo que averigua el detector.
 *
 * Examinar un fichero cuesta poco, pero hacerlo en cada pintado de la
 * biblioteca sería un disparate: son lecturas de disco por cada fila y por cada
 * visita. El resultado se calcula una vez, al subir el adjunto o la primera vez
 * que alguien lo mira, y a partir de ahí se sirve de la base de datos.
 */
final class Signature_Index {

	/**
	 * Meta consultable: indica si el adjunto tiene firma.
	 *
	 * Va aparte del detalle porque `meta_query` no sabe mirar dentro de un
	 * array serializado.
	 *
	 * @var string
	 */
	const META_FLAG = '_wp_autofirma_is_signed';

	/**
	 * Meta con el detalle completo de la detección.
	 *
	 * @var string
	 */
	const META_DETAIL = '_wp_autofirma_signature';

	/**
	 * Tipos MIME que merece la pena examinar.
	 *
	 * @var array
	 */
	const MIME_TYPES = array(
		'application/pdf',
		'application/xml',
		'text/xml',
		'application/pkcs7-signature',
		'application/pkcs7-mime',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.oasis.opendocument.presentation',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	);

	/**
	 * Extensiones de firma suelta.
	 *
	 * @var array
	 */
	const EXTENSIONS = array( 'csig', 'xsig', 'p7s', 'p7m', 'pk7' );

	/**
	 * Devuelve el estado de firma de un adjunto.
	 *
	 * @param int  $attachment_id ID del adjunto.
	 * @param bool $refresh       Fuerza un nuevo examen.
	 * @return array
	 */
	public function status( $attachment_id, $refresh = false ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return Signature_Detector::UNKNOWN;
		}

		if ( ! $refresh ) {
			$cached = get_post_meta( $attachment_id, self::META_DETAIL, true );

			if ( is_array( $cached ) && isset( $cached['version'] ) && Signature_Detector::VERSION === (int) $cached['version'] ) {
				unset( $cached['version'] );

				return $cached;
			}
		}

		return $this->scan( $attachment_id );
	}

	/**
	 * Examina el fichero y guarda el resultado.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return array
	 */
	public function scan( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$result        = Signature_Detector::UNKNOWN;

		if ( $this->is_candidate( $attachment_id ) ) {
			$path = get_attached_file( $attachment_id );

			if ( is_string( $path ) && '' !== $path ) {
				/**
				 * Tamaño máximo que se lee de golpe al examinar un fichero.
				 *
				 * Por encima solo se leen una ventana inicial y otra final.
				 *
				 * @param int $bytes Tamaño en bytes.
				 */
				$max_bytes = (int) apply_filters( 'wp_autofirma_max_scan_size', 16 * MB_IN_BYTES );
				$result    = Signature_Detector::inspect( $path, $max_bytes );
			}
		}

		$stored            = $result;
		$stored['version'] = Signature_Detector::VERSION;

		update_post_meta( $attachment_id, self::META_DETAIL, $stored );
		update_post_meta( $attachment_id, self::META_FLAG, $result['signed'] ? '1' : '0' );

		return $result;
	}

	/**
	 * Examina el adjunto recién subido.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return void
	 */
	public function scan_new_attachment( $attachment_id ) {
		$this->scan( $attachment_id );
	}

	/**
	 * Indica si el adjunto puede contener una firma.
	 *
	 * Evita leer del disco vídeos e imágenes, que nunca la llevan en un formato
	 * que aquí se reconozca.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return bool
	 */
	private function is_candidate( $attachment_id ) {
		$mime = (string) get_post_mime_type( $attachment_id );

		if ( in_array( $mime, self::MIME_TYPES, true ) ) {
			return true;
		}

		$path      = (string) get_attached_file( $attachment_id );
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $extension, self::EXTENSIONS, true );
	}
}

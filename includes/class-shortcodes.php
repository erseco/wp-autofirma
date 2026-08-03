<?php
/**
 * Shortcodes de consulta de firmas.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use WP_Query;

/**
 * Publica el estado de firma en el contenido del sitio.
 *
 * Estos shortcodes se pintan en el frontal, donde el visitante puede ser
 * cualquiera. Por eso ninguno enseña nada sin comprobar antes que quien mira
 * puede leer ese adjunto: el nombre de quien firma es un dato personal, y un
 * shortcode apuntando a un identificador ajeno no debe convertirse en una vía
 * para sacarlo.
 */
final class Shortcodes {

	/**
	 * Índice de firmas.
	 *
	 * @var Signature_Index
	 */
	private $index;

	/**
	 * Construye los shortcodes.
	 *
	 * @param Signature_Index $index Índice de firmas.
	 */
	public function __construct( Signature_Index $index ) {
		$this->index = $index;
	}

	/**
	 * Registra los shortcodes.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'autofirma_signature_status', array( $this, 'render_status' ) );
		add_shortcode( 'autofirma_signature_info', array( $this, 'render_info' ) );
		add_shortcode( 'autofirma_signed_documents', array( $this, 'render_list' ) );
	}

	/**
	 * Pinta si un adjunto está firmado.
	 *
	 * Atributos: `id`, `signed`, `unsigned` e `icon`.
	 *
	 * @param array<string, string>|string $attributes Atributos del shortcode.
	 * @return string
	 */
	public function render_status( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'id'       => 0,
				'signed'   => '',
				'unsigned' => '',
				'icon'     => 'yes',
			),
			$attributes,
			'autofirma_signature_status'
		);

		$attachment_id = $this->resolve_id( $attributes['id'] );

		if ( 0 === $attachment_id ) {
			return '';
		}

		$status = $this->index->status( $attachment_id );
		$custom = ! empty( $status['signed'] ) ? $attributes['signed'] : $attributes['unsigned'];
		$text   = '' !== $custom ? $custom : Signature_Presenter::summary( $status );
		$icon   = 'no' === $attributes['icon'] ? '' : Signature_Presenter::icon( $status, false ) . ' ';

		return sprintf(
			'<span class="wp-autofirma-status">%1$s%2$s</span>',
			wp_kses_post( $icon ),
			esc_html( $text )
		);
	}

	/**
	 * Pinta el detalle de la firma de un adjunto.
	 *
	 * Atributos: `id` y `disclaimer`.
	 *
	 * @param array<string, string>|string $attributes Atributos del shortcode.
	 * @return string
	 */
	public function render_info( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'id'         => 0,
				'disclaimer' => 'yes',
			),
			$attributes,
			'autofirma_signature_info'
		);

		$attachment_id = $this->resolve_id( $attributes['id'] );

		if ( 0 === $attachment_id ) {
			return '';
		}

		$status = $this->index->status( $attachment_id );
		$output = '<dl class="wp-autofirma-info">';

		foreach ( Signature_Presenter::rows( $status ) as $row ) {
			$output .= sprintf(
				'<dt>%1$s</dt><dd>%2$s</dd>',
				esc_html( $row['label'] ),
				esc_html( $row['value'] )
			);
		}

		$output .= '</dl>';

		if ( ! empty( $status['signed'] ) && 'no' !== $attributes['disclaimer'] ) {
			$output .= sprintf(
				'<p class="wp-autofirma-disclaimer"><small>%s</small></p>',
				esc_html( Signature_Presenter::disclaimer() )
			);
		}

		return $output;
	}

	/**
	 * Lista los adjuntos con firma detectada.
	 *
	 * Atributos: `count`.
	 *
	 * @param array<string, string>|string $attributes Atributos del shortcode.
	 * @return string
	 */
	public function render_list( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'count' => 10,
			),
			$attributes,
			'autofirma_signed_documents'
		);

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => min( 100, max( 1, (int) $attributes['count'] ) ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// La consulta usa la meta plana, no el detalle serializado:
				// dentro de un array serializado no se puede buscar.
				'meta_key'               => Signature_Index::META_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Es la única forma de filtrar por firma; la meta se escribe una vez y WordPress la indexa.
				'meta_value'             => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Ídem.
			)
		);

		$items = array();

		foreach ( $query->posts as $attachment ) {
			if ( ! $this->can_read( $attachment->ID ) ) {
				continue;
			}

			$items[] = sprintf(
				'<li><a href="%1$s">%2$s</a> <span class="wp-autofirma-status">%3$s</span></li>',
				esc_url( (string) get_permalink( $attachment->ID ) ),
				esc_html( get_the_title( $attachment->ID ) ),
				wp_kses_post( Signature_Presenter::icon( $this->index->status( $attachment->ID ) ) )
			);
		}

		if ( array() === $items ) {
			return '';
		}

		return '<ul class="wp-autofirma-documents">' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Determina sobre qué adjunto trabaja el shortcode.
	 *
	 * Devuelve cero cuando no hay adjunto o cuando quien mira no puede leerlo,
	 * de modo que el shortcode no pinta nada en lugar de filtrar datos.
	 *
	 * @param mixed $requested Identificador pedido en el atributo.
	 * @return int
	 */
	private function resolve_id( $requested ) {
		$attachment_id = (int) $requested;

		if ( $attachment_id <= 0 ) {
			$attachment_id = (int) get_the_ID();
		}

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return 0;
		}

		return $this->can_read( $attachment_id ) ? $attachment_id : 0;
	}

	/**
	 * Comprueba si quien mira puede ver los datos del adjunto.
	 *
	 * No basta con `read_post`. Esa capacidad acaba mapeando a `read`, y quien
	 * visita el sitio sin haber entrado no tiene ninguna capacidad: un adjunto
	 * perfectamente público daría negativo y el shortcode no pintaría nada.
	 * `is_post_publicly_viewable()` es la comprobación que usa el propio núcleo
	 * para esto y resuelve bien los adjuntos: los que no cuelgan de nada son
	 * públicos, y los que cuelgan de un borrador no lo son. La capacidad se
	 * sigue consultando para quien sí ha entrado y puede ver material que no es
	 * público.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return bool
	 */
	private function can_read( $attachment_id ) {
		$allowed = is_post_publicly_viewable( $attachment_id )
			|| current_user_can( 'read_post', $attachment_id );

		/**
		 * Permite decidir quién ve el estado de firma de un adjunto.
		 *
		 * Útil en instalaciones que sirven `uploads` tras una autenticación,
		 * donde ser públicamente visible en WordPress no implica que el fichero
		 * lo sea.
		 *
		 * @param bool $allowed       Si se muestran los datos.
		 * @param int  $attachment_id ID del adjunto.
		 */
		return (bool) apply_filters(
			'wp_autofirma_can_read_signature',
			$allowed,
			$attachment_id
		);
	}
}

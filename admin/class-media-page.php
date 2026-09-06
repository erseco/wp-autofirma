<?php
/**
 * Integración con la biblioteca de medios.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use WP_Post;

/**
 * Añade la pantalla de firma y su acción en los PDF.
 */
final class Media_Page {

	/**
	 * Sufijo de la pantalla registrada.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Registra la página bajo el menú Medios.
	 *
	 * @return void
	 */
	public function register_page() {
		$this->hook_suffix = (string) add_submenu_page(
			'upload.php',
			__( 'Firmar con AutoFirma', 'wp-autofirma' ),
			__( 'AutoFirma', 'wp-autofirma' ),
			'upload_files',
			'wp-autofirma-sign',
			array( $this, 'render_page' )
		);

		// La pantalla necesita una selección de PDF, así que se
		// registra para que exista y respete la capacidad, pero se retira del
		// menú: se llega desde la acción «Firmar con AutoFirma» del propio
		// adjunto. Un elemento de menú suelto llevaría a una página sin
		// documento que hacer nada con él.
		remove_submenu_page( 'upload.php', 'wp-autofirma-sign' );

		// Al ocultar el submenú, WordPress pierde la entrada de la que obtiene
		// el título. Se fija antes de admin-header.php, que llama a strip_tags().
		add_action(
			'load-' . $this->hook_suffix,
			static function () {
				global $title;
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress obtiene de este global el título de las pantallas de administración ocultas.
				$title = __( 'Firmar con AutoFirma', 'wp-autofirma' );
			}
		);
	}

	/**
	 * Añade la acción de firma a cada PDF.
	 *
	 * @param array<string, string> $actions Acciones existentes.
	 * @param WP_Post               $post    Adjunto.
	 * @return array<string, string>
	 */
	public function add_media_action( $actions, $post ) {
		if ( 'application/pdf' !== $post->post_mime_type ) {
			return $actions;
		}

		$url = add_query_arg(
			array(
				'page'          => 'wp-autofirma-sign',
				'attachment_id' => $post->ID,
			),
			admin_url( 'upload.php' )
		);

		$actions['wp_autofirma_sign'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Firmar con AutoFirma', 'wp-autofirma' )
		);

		return $actions;
	}

	/**
	 * Añade la firma a las acciones múltiples de la vista de lista.
	 *
	 * @param array<string, string> $actions Acciones existentes.
	 * @return array<string, string>
	 */
	public function add_bulk_action( $actions ) {
		if ( current_user_can( 'upload_files' ) ) {
			$actions['wp_autofirma_sign'] = __( 'Firmar PDF con AutoFirma', 'wp-autofirma' );
		}
		return $actions;
	}

	/**
	 * Abre la pantalla de confirmación; no firma ni modifica adjuntos.
	 *
	 * @param string $redirect URL de vuelta.
	 * @param string $action Acción elegida.
	 * @param int[]  $ids Adjuntos seleccionados.
	 * @return string
	 */
	public function handle_bulk_action( $redirect, $action, $ids ) {
		if ( 'wp_autofirma_sign' !== $action || ! current_user_can( 'upload_files' ) ) {
			return $redirect;
		}
		return add_query_arg(
			array(
				'page'           => 'wp-autofirma-sign',
				'attachment_ids' => implode( ',', wp_parse_id_list( $ids ) ),
			),
			admin_url( 'upload.php' )
		);
	}

	/**
	 * Carga recursos únicamente en la pantalla del plugin.
	 *
	 * @param string $hook_suffix Pantalla actual.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		$attachment_ids = $this->get_attachment_ids();
		$autoscript_url = $this->get_autoscript_url();

		// AutoScript viaja dentro del plugin: `@erseco/autofirma-client` lo
		// incluye ya verificado por sha256 y la construcción lo copia a
		// `build/`. El sitio puede seguir sirviendo su propia copia mediante la
		// constante o el filtro, que tienen prioridad.
		if ( '' === $autoscript_url ) {
			$autoscript_url = WP_AUTOFIRMA_URL . 'build/autoscript.js';
		}

		// No es un módulo: es un script clásico que declara su objeto global,
		// así que se encola aparte y el bundle depende de él.
		wp_enqueue_script(
			'wp-autofirma-autoscript',
			$autoscript_url,
			array(),
			WP_AUTOFIRMA_VERSION,
			true
		);
		$dependencies = array( 'wp-autofirma-autoscript' );

		wp_enqueue_style(
			'wp-autofirma-admin',
			WP_AUTOFIRMA_URL . 'assets/css/admin.css',
			array(),
			WP_AUTOFIRMA_VERSION
		);
		wp_enqueue_script(
			'wp-autofirma-admin',
			WP_AUTOFIRMA_URL . 'build/admin.js',
			$dependencies,
			WP_AUTOFIRMA_VERSION,
			true
		);
		wp_localize_script(
			'wp-autofirma-admin',
			'wpAutoFirmaSettings',
			array(
				'attachmentIds' => $attachment_ids,
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'restUrl'       => esc_url_raw( rest_url( 'wp-autofirma/v1' ) ),
				// En móvil no hay WebSocket local con AutoFirma, así que sin
				// servidor intermedio la firma no puede completarse.
				'intermediate'  => Intermediate_Controller::is_available(),
				'strings'       => array(
					'batchCompleted'      => __( 'Todos los PDF firmados se han guardado como adjuntos nuevos.', 'wp-autofirma' ),
					'batchPartial'        => __( 'Hay documentos sin firmar o sin guardar. Revisa el resultado de cada PDF; puedes descargar los firmados.', 'wp-autofirma' ),
					'notSigned'           => __( 'No firmado:', 'wp-autofirma' ),
					'notSaved'            => __( 'No se pudo guardar en WordPress:', 'wp-autofirma' ),
					'cancelled'           => __( 'La operación se ha cancelado.', 'wp-autofirma' ),
					'incompleteWatermark' => __( 'Para el sello visible hacen falta las cuatro coordenadas.', 'wp-autofirma' ),
					'emptyWatermark'      => __( 'El sello visible necesita un texto.', 'wp-autofirma' ),
					'completed'           => __( 'El documento firmado se ha guardado como un adjunto nuevo.', 'wp-autofirma' ),
					'download'            => __( 'Descargar el PDF firmado', 'wp-autofirma' ),
					'edit'                => __( 'Abrir el adjunto en WordPress', 'wp-autofirma' ),
					'loading'             => __( 'Cargando los documentos…', 'wp-autofirma' ),
					'saving'              => __( 'Guardando los documentos firmados…', 'wp-autofirma' ),
					'signing'             => __( 'Esperando a AutoFirma…', 'wp-autofirma' ),
					'unknownError'        => __( 'No se pudo completar la firma.', 'wp-autofirma' ),
				),
			)
		);
	}

	/**
	 * Muestra la pantalla de firma.
	 *
	 * @return void
	 */
	public function render_page() {
		$attachments = array_map( 'get_post', $this->get_attachment_ids() );
		$valid       = ! empty( $attachments ) && current_user_can( 'upload_files' );
		foreach ( $attachments as $attachment ) {
			if ( ! $attachment || 'attachment' !== $attachment->post_type || 'application/pdf' !== $attachment->post_mime_type || ! current_user_can( 'read_post', $attachment->ID ) ) {
				$valid = false;
			}
		}
		?>
		<div class="wrap wp-autofirma">
			<h1><?php esc_html_e( 'Firmar con AutoFirma', 'wp-autofirma' ); ?></h1>

			<?php if ( ! $valid ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php esc_html_e( 'Selecciona un PDF o varios PDF accesibles en la vista de lista de la biblioteca de medios y usa la acción «Firmar con AutoFirma».', 'wp-autofirma' ); ?>
					</p>
				</div>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'upload.php?mode=list' ) ); ?>">
						<?php esc_html_e( 'Abrir la biblioteca de medios', 'wp-autofirma' ); ?>
					</a>
				</p>
			<?php else : ?>
				<?php $this->render_documents( $attachments ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Muestra los documentos y una configuración de sello común.
	 *
	 * @param WP_Post[] $attachments Adjuntos seleccionados.
	 * @return void
	 */
	private function render_documents( array $attachments ) {
		?>
		<div class="wp-autofirma__card">
			<ul>
				<?php foreach ( $attachments as $attachment ) : ?>
					<li><?php echo esc_html( get_the_title( $attachment ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php esc_html_e( 'Los originales no se sobrescribirán. Cada PDF firmado se guardará como un adjunto nuevo.', 'wp-autofirma' ); ?>
			</p>

			<?php $this->render_visible_signature_fields(); ?>

			<button
				type="button"
				class="button button-primary button-hero"
				id="wp-autofirma-sign"
			>
				<?php echo esc_html( count( $attachments ) > 1 ? __( 'Firmar todos los PDF', 'wp-autofirma' ) : __( 'Firmar PDF', 'wp-autofirma' ) ); ?>
			</button>

			<p id="wp-autofirma-status" role="status" aria-live="polite">
				<span id="wp-autofirma-check" class="dashicons dashicons-yes-alt" aria-hidden="true" hidden></span>
				<span id="wp-autofirma-message"></span>
			</p>
			<div id="wp-autofirma-result" tabindex="-1" hidden></div>
		</div>
		<?php
	}

	/**
	 * Muestra los controles de la firma visible.
	 *
	 * Una firma PAdES es invisible salvo que se le dé un rectángulo donde
	 * dibujarse. Por eso el texto no basta: sin coordenadas AutoFirma no pinta
	 * nada, y quien firmara creería que el sello no funciona.
	 *
	 * @return void
	 */
	private function render_visible_signature_fields() {
		$defaults = self::visible_signature_defaults();
		?>
		<fieldset class="wp-autofirma__watermark">
			<legend><?php esc_html_e( 'Sello visible', 'wp-autofirma' ); ?></legend>

			<p>
				<label for="wp-autofirma-watermark">
					<input type="checkbox" id="wp-autofirma-watermark" />
					<?php esc_html_e( 'Dibujar un sello visible sobre el PDF', 'wp-autofirma' ); ?>
				</label>
			</p>

			<p class="description">
				<?php esc_html_e( 'El sello solo hace visible la firma. Se aplicarán el mismo texto, página y coordenadas a todos los PDF; comprueba que esa posición existe en todos ellos.', 'wp-autofirma' ); ?>
			</p>

			<?php // Un `fieldset` deshabilitado apaga todo lo que contiene, sin recorrer campo por campo. ?>
			<fieldset id="wp-autofirma-watermark-fields" class="wp-autofirma__watermark-fields" disabled>
				<p>
					<label for="wp-autofirma-layer2-text">
						<?php esc_html_e( 'Texto', 'wp-autofirma' ); ?>
					</label>
					<textarea id="wp-autofirma-layer2-text" class="large-text code" rows="2"><?php echo esc_textarea( $defaults['text'] ); ?></textarea>
					<span class="description">
						<?php
						printf(
							/* translators: %s: lista de variables admitidas. */
							esc_html__( 'Admite variables que AutoFirma sustituye al firmar: %s. En la fecha, PATTERN es un formato de Java, por ejemplo dd/MM/yyyy HH:mm.', 'wp-autofirma' ),
							'<code>' . implode( '</code>, <code>', array_map( 'esc_html', self::text_placeholders() ) ) . '</code>'
						);
						?>
					</span>
				</p>

				<p>
					<label for="wp-autofirma-page"><?php esc_html_e( 'Página', 'wp-autofirma' ); ?></label>
					<input type="number" id="wp-autofirma-page" min="1" step="1"
						value="<?php echo esc_attr( (string) $defaults['page'] ); ?>" class="small-text" />
				</p>

				<p>
					<?php
					foreach ( self::coordinate_fields() as $key => $label ) :
						?>
						<label for="wp-autofirma-<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $label ); ?>
							<input type="number" step="1" class="small-text"
								id="wp-autofirma-<?php echo esc_attr( $key ); ?>"
								value="<?php echo esc_attr( (string) $defaults[ $key ] ); ?>" />
						</label>
					<?php endforeach; ?>
				</p>

				<p class="description">
					<?php esc_html_e( 'En puntos PDF desde la esquina inferior izquierda: 72 puntos equivalen a una pulgada, y un A4 mide 595 × 842.', 'wp-autofirma' ); ?>
				</p>
			</fieldset>
		</fieldset>
		<?php
	}

	/**
	 * Devuelve los valores iniciales del sello.
	 *
	 * @return array<string, mixed>
	 */
	public static function visible_signature_defaults() {
		/**
		 * Filtra los valores iniciales del sello visible.
		 *
		 * @param array<string, mixed> $defaults Texto, página y coordenadas.
		 */
		return (array) apply_filters(
			'wp_autofirma_visible_signature_defaults',
			array(
				// El mismo que AutoFirma trae por omisión (`pdfLayer2Text` en su
				// `preferences.properties`), para que el sello salga igual que
				// firmando con la aplicación de escritorio.
				'text'   => 'Firmado por $$SUBJECTCN$$ el día $$SIGNDATE=dd/MM/yyyy$$ con un certificado emitido por $$ISSUERCN$$',
				'page'   => 1,
				'left'   => 40,
				'bottom' => 40,
				'right'  => 260,
				'top'    => 110,
			)
		);
	}

	/**
	 * Devuelve las etiquetas de las cuatro coordenadas.
	 *
	 * @return array<string, string>
	 */
	private static function coordinate_fields() {
		return array(
			'left'   => __( 'Izquierda', 'wp-autofirma' ),
			'bottom' => __( 'Abajo', 'wp-autofirma' ),
			'right'  => __( 'Derecha', 'wp-autofirma' ),
			'top'    => __( 'Arriba', 'wp-autofirma' ),
		);
	}

	/**
	 * Devuelve las variables que AutoFirma sustituye en el texto.
	 *
	 * Tomadas de la ayuda oficial de AutoFirma, no supuestas.
	 *
	 * @return array<int, string>
	 */
	private static function text_placeholders() {
		return array(
			'$$SUBJECTCN$$',
			'$$ISSUERCN$$',
			'$$CERTSERIAL$$',
			'$$SIGNDATE=PATTERN$$',
			'$$ORGANIZATION$$',
			'$$OU$$',
			'$$SURNAME$$',
			'$$TITLE$$',
			'$$REASON$$',
			'$$LOCATION$$',
			'$$CONTACT$$',
		);
	}

	/**
	 * Lee la selección sin modificar documentos; REST vuelve a comprobar permisos.
	 *
	 * @return int[]
	 */
	private function get_attachment_ids() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navegación de solo lectura. Las mutaciones siguen usando REST con nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se rechazan tipos y sintaxis inválidos y se normaliza con wp_parse_id_list justo debajo.
		$requested = isset( $_GET['attachment_ids'] ) ? wp_unslash( $_GET['attachment_ids'] ) : ( isset( $_GET['attachment_id'] ) ? wp_unslash( $_GET['attachment_id'] ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! is_string( $requested ) || ! preg_match( '/^[0-9]+(?:,[0-9]+)*$/D', $requested ) ) {
			return array();
		}
		return array_values( array_filter( wp_parse_id_list( $requested ) ) );
	}

	/**
	 * Obtiene la URL configurada para el fichero oficial.
	 *
	 * @return string
	 */
	private function get_autoscript_url() {
		$url = defined( 'WP_AUTOFIRMA_AUTOSCRIPT_URL' )
			? (string) WP_AUTOFIRMA_AUTOSCRIPT_URL
			: '';

		/**
		 * Filtra la URL de AutoScript proporcionada por el sitio.
		 *
		 * @param string $url URL absoluta o vacía.
		 */
		return esc_url_raw( (string) apply_filters( 'wp_autofirma_autoscript_url', $url ) );
	}
}

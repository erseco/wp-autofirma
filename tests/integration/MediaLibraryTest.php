<?php
/**
 * Pruebas de integración de la biblioteca de medios.
 *
 * @package WPAutoFirma
 */

/**
 * Comprueba la columna de la lista, la ficha del adjunto y la acción de firma.
 */
class MediaLibraryTest extends WP_UnitTestCase {

	/**
	 * Adjunto firmado.
	 *
	 * @var int
	 */
	private $signed_id;

	/**
	 * Adjunto sin firmar.
	 *
	 * @var int
	 */
	private $unsigned_id;

	/**
	 * Sube los documentos y entra como administrador.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->signed_id   = self::factory()->attachment->create_upload_object(
			dirname( __DIR__ ) . '/php/fixtures/signed.pdf'
		);
		$this->unsigned_id = self::factory()->attachment->create_upload_object(
			dirname( __DIR__ ) . '/php/fixtures/unsigned.pdf'
		);
	}

	/**
	 * La columna de firma se añade a la vista de lista.
	 */
	public function test_signature_column_is_registered() {
		$columns = apply_filters( 'manage_media_columns', array( 'title' => 'Título' ) );

		$this->assertArrayHasKey( 'wp_autofirma_signature', $columns );
	}

	/**
	 * Un documento firmado se marca y otro sin firmar no.
	 */
	public function test_column_marks_only_signed_documents() {
		ob_start();
		do_action( 'manage_media_custom_column', 'wp_autofirma_signature', $this->signed_id );
		$firmado = ob_get_clean();

		ob_start();
		do_action( 'manage_media_custom_column', 'wp_autofirma_signature', $this->unsigned_id );
		$sin_firmar = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-yes-alt', $firmado );
		$this->assertStringContainsString( 'Firmado digitalmente', $firmado );
		$this->assertStringNotContainsString( 'dashicons-yes-alt', $sin_firmar );
		$this->assertStringContainsString( 'Sin firma digital', $sin_firmar );
	}

	/**
	 * Otra columna no se ve afectada.
	 */
	public function test_other_columns_are_untouched() {
		ob_start();
		do_action( 'manage_media_custom_column', 'title', $this->signed_id );

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * La ficha del adjunto describe la firma.
	 */
	public function test_attachment_details_describe_the_signature() {
		$fields = apply_filters( 'attachment_fields_to_edit', array(), get_post( $this->signed_id ) );

		$this->assertArrayHasKey( 'wp_autofirma_signature', $fields );
		$this->assertStringContainsString( 'FIRMANTE DE PRUEBA', $fields['wp_autofirma_signature']['html'] );
		$this->assertStringContainsString( 'No se ha validado', $fields['wp_autofirma_signature']['html'] );
	}

	/**
	 * De lo que no se puede analizar no se dice nada.
	 *
	 * Una fila «Formato no analizable» en cada imagen de la biblioteca sería
	 * ruido: ahí no hay firma que buscar y decirlo no aporta.
	 */
	public function test_unanalysable_files_get_no_signature_details() {
		$imagen = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );

		$fields = apply_filters( 'attachment_fields_to_edit', array(), get_post( $imagen ) );

		$this->assertArrayNotHasKey( 'wp_autofirma_signature', $fields );
	}

	/**
	 * El filtro retira la señalización por completo.
	 */
	public function test_filter_removes_the_marking() {
		add_filter( 'wp_autofirma_show_signature_status', '__return_false' );

		$columns = apply_filters( 'manage_media_columns', array() );
		$fields  = apply_filters( 'attachment_fields_to_edit', array(), get_post( $this->signed_id ) );

		remove_filter( 'wp_autofirma_show_signature_status', '__return_false' );

		$this->assertArrayNotHasKey( 'wp_autofirma_signature', $columns );
		$this->assertArrayNotHasKey( 'wp_autofirma_signature', $fields );
	}

	/**
	 * La acción de firmar aparece en los PDF.
	 */
	public function test_sign_action_is_offered_for_pdf() {
		$actions = apply_filters( 'media_row_actions', array(), get_post( $this->unsigned_id ), false );

		$this->assertArrayHasKey( 'wp_autofirma_sign', $actions );
		$this->assertStringContainsString( 'page=wp-autofirma-sign', $actions['wp_autofirma_sign'] );
	}

	/**
	 * Y no en lo que no es un PDF.
	 */
	public function test_sign_action_is_not_offered_for_other_types() {
		$image = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );

		$actions = apply_filters( 'media_row_actions', array(), get_post( $image ), false );

		$this->assertArrayNotHasKey( 'wp_autofirma_sign', $actions );
	}
}

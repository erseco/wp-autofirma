<?php
/**
 * Pruebas de integración de la API REST.
 *
 * @package WPAutoFirma
 */

use Erseco\WPAutoFirma\Document_Service;
use Erseco\WPAutoFirma\Rest_Controller;
use Erseco\WPAutoFirma\Signature_Repository;

/**
 * Ejercita las rutas que leen y guardan documentos.
 *
 * Lo que se comprueba aquí es sobre todo quién puede hacer qué: son las rutas
 * por las que pasa el documento, y una capacidad mal puesta convierte la
 * biblioteca de medios en un servicio abierto.
 */
class RestControllerTest extends WP_UnitTestCase {

	/**
	 * Servidor REST de la prueba.
	 *
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Adjunto de partida.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Levanta el servidor REST y sube un PDF.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->attachment_id = self::factory()->attachment->create_upload_object(
			dirname( __DIR__ ) . '/php/fixtures/unsigned.pdf'
		);
	}

	/**
	 * Devuelve el servidor REST a su estado inicial.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Lanza una petición contra la API.
	 *
	 * @param string $method Método HTTP.
	 * @param string $route  Ruta.
	 * @param array  $body   Cuerpo en JSON.
	 * @return WP_REST_Response
	 */
	private function request( $method, $route, array $body = array() ) {
		$request = new WP_REST_Request( $method, $route );

		if ( array() !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Las rutas del plugin se registran.
	 */
	public function test_routes_are_registered() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp-autofirma/v1/documents/(?P<id>\d+)', $routes );
		$this->assertArrayHasKey( '/wp-autofirma/v1/signatures', $routes );
	}

	/**
	 * Quien puede leer el adjunto obtiene el documento en Base64.
	 */
	public function test_editor_reads_the_document() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$response = $this->request( 'GET', '/wp-autofirma/v1/documents/' . $this->attachment_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->attachment_id, $data['attachmentId'] );
		$this->assertStringStartsWith( '%PDF-', base64_decode( $data['data'], true ) );
	}

	/**
	 * Un documento que no existe da error, no una excepción sin controlar.
	 */
	public function test_missing_document_returns_an_error() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request( 'GET', '/wp-autofirma/v1/documents/999999' );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * Sin permiso para subir ficheros no se puede guardar una firma.
	 */
	public function test_subscriber_cannot_store_a_signature() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'filename'             => 'documento_signed.pdf',
				'signature'            => base64_encode( '%PDF-1.7 firmado' ),
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Quien no ha entrado tampoco.
	 */
	public function test_anonymous_cannot_store_a_signature() {
		wp_set_current_user( 0 );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'filename'             => 'documento_signed.pdf',
				'signature'            => base64_encode( '%PDF-1.7 firmado' ),
			)
		);

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Guardar una firma crea un adjunto nuevo y deja intacto el original.
	 */
	public function test_storing_a_signature_creates_another_attachment() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$original_path     = get_attached_file( $this->attachment_id );
		$original_contents = file_get_contents( $original_path );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'filename'             => 'documento_signed.pdf',
				'signature'            => base64_encode( '%PDF-1.7 firmado' ),
			)
		);

		$this->assertSame( 201, $response->get_status() );

		$signed_id = $response->get_data()['attachmentId'];

		$this->assertNotSame( $this->attachment_id, $signed_id );
		$this->assertSame(
			$original_contents,
			file_get_contents( $original_path ),
			'El documento original no puede modificarse.'
		);
		$this->assertSame(
			(string) $this->attachment_id,
			(string) get_post_meta( $signed_id, '_wp_autofirma_original_attachment_id', true )
		);
		$this->assertNotEmpty( get_post_meta( $signed_id, '_wp_autofirma_document_sha256', true ) );
	}

	/**
	 * Una petición sin los datos obligatorios se rechaza.
	 */
	public function test_incomplete_request_is_rejected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array( 'originalAttachmentId' => $this->attachment_id )
		);

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Un documento que supera el límite no se guarda.
	 *
	 * El límite existe para que nadie llene el disco a través de esta ruta.
	 */
	public function test_oversized_signature_is_rejected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'wp_autofirma_max_signed_size', array( $this, 'tiny_limit' ) );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'filename'             => 'documento_signed.pdf',
				'signature'            => base64_encode( str_repeat( 'a', 1024 ) ),
			)
		);

		remove_filter( 'wp_autofirma_max_signed_size', array( $this, 'tiny_limit' ) );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Un documento que no es PDF se rechaza diciendo por qué.
	 *
	 * El adjunto existe y quien lo pide puede leerlo, así que el permiso no es
	 * el problema: lo que no se admite es el formato, y la respuesta tiene que
	 * distinguir una cosa de la otra.
	 */
	public function test_a_document_that_is_not_a_pdf_explains_the_reason() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$documento = self::factory()->attachment->create_upload_object(
			dirname( __DIR__ ) . '/php/fixtures/signed.odt'
		);

		$response = $this->request( 'GET', '/wp-autofirma/v1/documents/' . $documento );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wp_autofirma_document_error', $data['code'] );
		$this->assertStringContainsString( 'PDF', $data['message'] );
	}

	/**
	 * Sin nombre de fichero, el documento firmado recibe uno por omisión.
	 */
	public function test_a_signature_without_a_filename_gets_a_default_one() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'signature'            => base64_encode( '%PDF-1.7 firmado' ),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertMatchesRegularExpression(
			'#/documento-firmado(-\d+)?\.pdf$#',
			(string) get_attached_file( $response->get_data()['attachmentId'] )
		);
	}

	/**
	 * Si el fichero no se puede escribir, se responde con el motivo.
	 *
	 * Un disco lleno o una carpeta sin permisos no puede acabar en un error 500
	 * ni, peor, en un 201 que anuncie una firma que no se ha guardado.
	 */
	public function test_a_storage_failure_is_reported() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'wp_upload_bits', array( $this, 'broken_upload' ) );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'filename'             => 'documento_signed.pdf',
				'signature'            => base64_encode( '%PDF-1.7 firmado' ),
			)
		);

		remove_filter( 'wp_upload_bits', array( $this, 'broken_upload' ) );

		$data = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wp_autofirma_signature_error', $data['code'] );
		$this->assertStringContainsString( 'No hay dónde escribir', $data['message'] );
	}

	/**
	 * Si WordPress rechaza crear el adjunto, tampoco se anuncia una firma.
	 */
	public function test_a_rejected_attachment_is_reported() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$response = $this->request(
			'POST',
			'/wp-autofirma/v1/signatures',
			array(
				'originalAttachmentId' => $this->attachment_id,
				'filename'             => 'documento_signed.pdf',
				'signature'            => base64_encode( '%PDF-1.7 firmado' ),
			)
		);

		remove_filter( 'wp_insert_post_empty_content', '__return_true' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wp_autofirma_signature_error', $response->get_data()['code'] );
	}

	/**
	 * Sin adjunto original no se guarda nada, aunque venga la firma.
	 *
	 * El permiso ya corta antes esta petición, pero el guardado no puede
	 * fiarse de eso: se comprueba llamando al controlador directamente, que es
	 * como quedaría si mañana la ruta se reutilizara desde otro sitio.
	 */
	public function test_a_signature_without_an_original_document_is_rejected() {
		$controlador = new Rest_Controller( new Document_Service(), new Signature_Repository() );

		$request = new WP_REST_Request( 'POST', '/wp-autofirma/v1/signatures' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'signature' => base64_encode( '%PDF-1.7 firmado' ) ) ) );

		$error = $controlador->create_signature( $request );

		$this->assertWPError( $error );
		$this->assertSame( 'wp_autofirma_invalid_request', $error->get_error_code() );
		$this->assertSame( 400, $error->get_error_data()['status'] );
	}

	/**
	 * Devuelve un límite diminuto para la prueba anterior.
	 *
	 * @return int
	 */
	public function tiny_limit() {
		return 16;
	}

	/**
	 * Simula un fallo al escribir el fichero.
	 *
	 * Devolver una cadena por este filtro es la forma que ofrece WordPress de
	 * cortar la escritura con un motivo.
	 *
	 * @return string
	 */
	public function broken_upload() {
		return 'No hay dónde escribir el documento.';
	}
}

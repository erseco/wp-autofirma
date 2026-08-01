<?php
/**
 * Pruebas de integración de la API REST.
 *
 * @package WPAutoFirma
 */

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
     * Devuelve un límite diminuto para la prueba anterior.
     *
     * @return int
     */
    public function tiny_limit() {
        return 16;
    }
}

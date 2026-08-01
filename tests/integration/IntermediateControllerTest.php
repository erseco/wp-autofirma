<?php
/**
 * Pruebas de integración del servidor intermedio.
 *
 * @package WPAutoFirma
 */

use Erseco\WPAutoFirma\Intermediate_Controller;

/**
 * Ejercita el protocolo y, sobre todo, quién puede usarlo.
 *
 * Las dos rutas son públicas por necesidad, porque AutoFirma no lleva la sesión
 * de WordPress. Lo único que las protege es el token, así que conviene que esté
 * comprobado.
 */
class IntermediateControllerTest extends WP_UnitTestCase {

    /**
     * Servidor REST de la prueba.
     *
     * @var WP_REST_Server
     */
    private $server;

    /**
     * Levanta el servidor REST con enlaces permanentes bonitos.
     */
    public function set_up() {
        parent::set_up();

        // Sin ellos la API REST vive tras `?rest_route=` y el servicio no se
        // ofrece, que es justo lo que comprueba otra de estas pruebas.
        $this->set_permalink_structure( '/%postname%/' );

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server   = $wp_rest_server;
        do_action( 'rest_api_init' );
    }

    /**
     * Devuelve el entorno a su estado inicial.
     */
    public function tear_down() {
        global $wp_rest_server;
        $wp_rest_server = null;

        $this->set_permalink_structure( '' );

        parent::tear_down();
    }

    /**
     * Abre una sesión como administrador y devuelve su token.
     *
     * @return string
     */
    private function open_session() {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

        $response = $this->server->dispatch(
            new WP_REST_Request( 'POST', '/wp-autofirma/v1/intermediate-sessions' )
        );

        $this->assertSame( 201, $response->get_status() );

        $parts = explode( '/', $response->get_data()['storageUrl'] );

        return $parts[ count( $parts ) - 2 ];
    }

    /**
     * Lanza una llamada al protocolo.
     *
     * @param string $token      Token de sesión.
     * @param string $service    `storage` o `retrieve`.
     * @param array  $parameters Parámetros del protocolo.
     * @return WP_REST_Response
     */
    private function call( $token, $service, array $parameters ) {
        // Se manda el cuerpo crudo como formulario, que es exactamente lo que
        // hace AutoScript: `op=put&v=1_0&id=…&dat=…` con
        // `application/x-www-form-urlencoded`.
        $request = new WP_REST_Request(
            'POST',
            '/wp-autofirma/v1/intermediate/' . $token . '/' . $service
        );
        $request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
        $request->set_body( http_build_query( $parameters ) );

        return $this->server->dispatch( $request );
    }

    /**
     * Quien no puede subir ficheros no abre sesión.
     */
    public function test_subscriber_cannot_open_a_session() {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

        $response = $this->server->dispatch(
            new WP_REST_Request( 'POST', '/wp-autofirma/v1/intermediate-sessions' )
        );

        $this->assertSame( 403, $response->get_status() );
    }

    /**
     * Quien no ha entrado tampoco.
     */
    public function test_anonymous_cannot_open_a_session() {
        wp_set_current_user( 0 );

        $response = $this->server->dispatch(
            new WP_REST_Request( 'POST', '/wp-autofirma/v1/intermediate-sessions' )
        );

        $this->assertSame( 401, $response->get_status() );
    }

    /**
     * La sesión devuelve las dos direcciones que espera AutoScript.
     */
    public function test_session_returns_both_service_urls() {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

        $data = $this->server->dispatch(
            new WP_REST_Request( 'POST', '/wp-autofirma/v1/intermediate-sessions' )
        )->get_data();

        $this->assertStringEndsWith( '/storage', $data['storageUrl'] );
        $this->assertStringEndsWith( '/retrieve', $data['retrieveUrl'] );
        $this->assertStringNotContainsString( '?', $data['storageUrl'], 'AutoScript concatena «?op=check» y una segunda interrogación rompería el sondeo.' );
        $this->assertGreaterThan( 0, $data['expiresIn'] );
    }

    /**
     * El protocolo completo: depositar y recoger una sola vez.
     */
    public function test_protocol_stores_and_delivers_once() {
        $token = $this->open_session();
        wp_set_current_user( 0 );

        $put = $this->call(
            $token,
            'storage',
            array(
                'op'  => 'put',
                'v'   => '1_0',
                'id'  => 'documento',
                'dat' => 'contenido opaco',
            )
        );
        $this->assertSame( 'OK', $put->get_data() );

        $parameters = array(
            'op' => 'get',
            'v'  => '1_0',
            'id' => 'documento',
        );

        $this->assertSame( 'contenido opaco', $this->call( $token, 'retrieve', $parameters )->get_data() );
        $this->assertStringStartsWith( 'ERR-', $this->call( $token, 'retrieve', $parameters )->get_data() );
    }

    /**
     * Sin sesión válida no se acepta ni se entrega nada.
     */
    public function test_unknown_token_is_rejected() {
        wp_set_current_user( 0 );

        $response = $this->call(
            str_repeat( 'a', 32 ),
            'storage',
            array(
                'op'  => 'put',
                'v'   => '1_0',
                'id'  => 'documento',
                'dat' => 'intruso',
            )
        );

        $this->assertSame( 403, $response->get_status() );
    }

    /**
     * El sondeo de disponibilidad responde sin sesión.
     *
     * Tiene que hacerlo: AutoScript lo lanza antes de que exista ninguna, y si
     * fallara daría el trámite por incompatible.
     */
    public function test_availability_check_answers_without_a_session() {
        wp_set_current_user( 0 );

        $request = new WP_REST_Request(
            'GET',
            '/wp-autofirma/v1/intermediate/' . str_repeat( 'b', 32 ) . '/storage'
        );
        $request->set_query_params( array( 'op' => 'check' ) );

        $response = $this->server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'OK', $response->get_data() );
    }

    /**
     * Dos sesiones no comparten datos aunque coincida el identificador.
     */
    public function test_sessions_are_isolated() {
        $primera = $this->open_session();
        $segunda = $this->open_session();

        $this->call(
            $primera,
            'storage',
            array(
                'op'  => 'put',
                'v'   => '1_0',
                'id'  => 'mismo-id',
                'dat' => 'de la primera',
            )
        );

        $ajena = $this->call(
            $segunda,
            'retrieve',
            array(
                'op' => 'get',
                'v'  => '1_0',
                'id' => 'mismo-id',
            )
        );

        $this->assertStringStartsWith( 'ERR-', $ajena->get_data() );
    }

    /**
     * Con enlaces permanentes simples el servicio no se ofrece.
     */
    public function test_service_is_not_offered_without_pretty_permalinks() {
        $this->set_permalink_structure( '' );

        $this->assertSame( '', Intermediate_Controller::base_url() );
        $this->assertFalse( Intermediate_Controller::is_available() );
    }

    /**
     * El filtro permite retirarlo por completo.
     */
    public function test_filter_can_disable_the_service() {
        add_filter( 'wp_autofirma_enable_intermediate_server', '__return_false' );

        $available = Intermediate_Controller::is_available();

        remove_filter( 'wp_autofirma_enable_intermediate_server', '__return_false' );

        $this->assertFalse( $available );
    }
}

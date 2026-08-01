<?php
/**
 * Servidor intermedio de AutoFirma expuesto por REST.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use Erseco\AutoFirma\IntermediateServer\IntermediateServer;
use Erseco\AutoFirma\IntermediateServer\Protocol\Request as ProtocolRequest;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Publica los servicios de almacenamiento y recuperación que necesita AutoScript.
 *
 * Cuando el navegador no puede hablar por WebSocket con AutoFirma —en móviles,
 * siempre— AutoScript deja la petición en un servicio HTTP, la aplicación la
 * recoge, firma y devuelve el resultado por el mismo camino. Sin ese servicio,
 * la firma en móvil no puede completarse: es exactamente lo que le faltaba al
 * plugin.
 *
 * Las dos rutas son públicas porque quien las llama no es solo el navegador,
 * sino también AutoFirma, que no arrastra la sesión de WordPress. Lo que las
 * protege es un token de sesión efímero que solo se entrega a quien ya ha
 * entrado y puede subir ficheros.
 */
final class Intermediate_Controller {

    /**
     * Espacio de nombres de las rutas.
     *
     * @var string
     */
    const NAMESPACE_V1 = 'wp-autofirma/v1';

    /**
     * Prefijo de los transients de sesión.
     *
     * @var string
     */
    const SESSION_PREFIX = 'wpaf_sess_';

    /**
     * Registra las rutas.
     *
     * @return void
     */
    public function register_routes() {
        if ( ! self::is_available() ) {
            return;
        }

        register_rest_route(
            self::NAMESPACE_V1,
            '/intermediate-sessions',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_session' ),
                'permission_callback' => array( $this, 'can_create_session' ),
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/intermediate/(?P<token>[A-Za-z0-9]{32})/(?P<service>storage|retrieve)',
            array(
                'methods'             => array( 'GET', 'POST' ),
                'callback'            => array( $this, 'serve' ),
                // Pública a propósito: AutoFirma llama a esta ruta y no lleva
                // la sesión de WordPress. El token de la ruta es lo que
                // autoriza, y solo se emite a quien ya ha entrado.
                'permission_callback' => '__return_true',
                'args'                => array(
                    'token' => array(
                        'required' => true,
                    ),
                ),
            )
        );
    }

    /**
     * Indica si el servidor intermedio puede ofrecerse.
     *
     * @return bool
     */
    public static function is_available() {
        // Las dos clases van juntas: el protocolo viene de la librería y el
        // almacén solo se carga si esa librería está. Comprobar ambas deja
        // explícito que sin dependencias no hay servicio que ofrecer.
        $available = class_exists( IntermediateServer::class )
            && class_exists( Transient_Store::class )
            && '' !== self::base_url();

        /**
         * Permite desactivar el servidor intermedio.
         *
         * @param bool $available Si se registran las rutas y se anuncian al navegador.
         */
        return (bool) apply_filters( 'wp_autofirma_enable_intermediate_server', $available );
    }

    /**
     * Devuelve la base de las rutas del servidor intermedio.
     *
     * AutoScript concatena `?op=check` a estas direcciones para comprobar que
     * responden. Si la dirección ya llevara una cadena de consulta —como ocurre
     * con los enlaces permanentes simples, donde la API REST vive en
     * `?rest_route=`— saldrían dos interrogantes y la comprobación fallaría, con
     * lo que AutoScript daría el trámite por incompatible. En ese caso es
     * preferible no ofrecer el servicio que ofrecer uno que no funciona.
     *
     * @return string Base sin barra final, o cadena vacía si no puede ofrecerse.
     */
    public static function base_url() {
        $base = rest_url( self::NAMESPACE_V1 . '/intermediate' );

        if ( false !== strpos( $base, '?' ) ) {
            return '';
        }

        return untrailingslashit( $base );
    }

    /**
     * Comprueba quién puede abrir una sesión.
     *
     * @return bool
     */
    public function can_create_session() {
        return current_user_can( 'upload_files' );
    }

    /**
     * Abre una sesión y devuelve las direcciones de los dos servicios.
     *
     * @return WP_REST_Response
     */
    public function create_session() {
        $token = self::generate_token();

        set_transient(
            self::SESSION_PREFIX . $token,
            get_current_user_id(),
            self::session_lifetime()
        );

        $base = self::base_url();

        return new WP_REST_Response(
            array(
                'storageUrl'  => $base . '/' . $token . '/storage',
                'retrieveUrl' => $base . '/' . $token . '/retrieve',
                'expiresIn'   => self::session_lifetime(),
            ),
            201
        );
    }

    /**
     * Atiende una llamada al servicio de almacenamiento o de recuperación.
     *
     * @param WP_REST_Request $request Petición entrante.
     * @return WP_REST_Response
     */
    public function serve( WP_REST_Request $request ) {
        $token = (string) $request->get_param( 'token' );

        // `op=check` es el sondeo de disponibilidad que AutoScript lanza antes
        // de nada. Se responde sin mirar la sesión: contestarlo no entrega ni
        // acepta datos, y exigir token aquí haría que AutoScript diese el
        // trámite por incompatible antes de que exista sesión alguna.
        if ( 'check' !== strtolower( (string) $request->get_param( 'op' ) )
            && false === get_transient( self::SESSION_PREFIX . $token ) ) {
            return $this->text_response( 'ERR-06=Invalid identifier', 403 );
        }

        $server = new IntermediateServer(
            new Transient_Store( $token ),
            self::max_payload(),
            self::payload_lifetime()
        );

        $response = $server->handle(
            ProtocolRequest::fromRawHttp(
                (string) $request->get_method(),
                (array) $request->get_query_params(),
                (string) $request->get_body()
            )
        );

        return $this->text_response(
            $response->body(),
            $response->statusCode(),
            $response->headers()
        );
    }

    /**
     * Envía el cuerpo tal cual, sin envolverlo en JSON.
     *
     * AutoScript espera texto plano: el dato cifrado que recupera se usa byte a
     * byte, de modo que serializarlo como JSON lo corrompería.
     *
     * @param string $body    Cuerpo de la respuesta.
     * @param int    $status  Código HTTP.
     * @param array  $headers Cabeceras.
     * @return WP_REST_Response
     */
    private function text_response( $body, $status = 200, array $headers = array() ) {
        $response = new WP_REST_Response( $body, $status );

        foreach ( $headers as $name => $value ) {
            $response->header( $name, $value );
        }

        $response->header( 'Content-Type', 'text/plain; charset=UTF-8' );

        return $response;
    }

    /**
     * Sirve en texto plano las respuestas de estas rutas.
     *
     * @param bool             $served  Si la respuesta ya se ha enviado.
     * @param WP_REST_Response $result  Respuesta.
     * @param WP_REST_Request  $request Petición.
     * @return bool
     */
    public function serve_as_text( $served, $result, $request ) {
        if ( $served || ! $request instanceof WP_REST_Request ) {
            return $served;
        }

        if ( 0 !== strpos( (string) $request->get_route(), '/' . self::NAMESPACE_V1 . '/intermediate/' ) ) {
            return $served;
        }

        $body = $result->get_data();

        // Los errores que genera WordPress —una ruta que no existe, un método
        // no admitido— llegan aquí como array. Esos se dejan a la maquinaria
        // normal de la API REST: imprimirlos como texto produciría un aviso de
        // PHP dentro de la respuesta.
        if ( ! is_string( $body ) ) {
            return $served;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Respuesta del protocolo en texto plano, servida con Content-Type text/plain y nosniff.
        echo $body;

        return true;
    }

    /**
     * Genera un token de sesión.
     *
     * @return string
     */
    private static function generate_token() {
        return substr( bin2hex( random_bytes( 20 ) ), 0, 32 );
    }

    /**
     * Devuelve cuánto vive una sesión de firma.
     *
     * @return int
     */
    private static function session_lifetime() {
        /**
         * Segundos que dura una sesión del servidor intermedio.
         *
         * Cubre desde que se pulsa firmar hasta que AutoFirma devuelve el
         * resultado, incluido el tiempo que tarda quien firma en desbloquear el
         * teléfono y elegir certificado.
         *
         * @param int $seconds Duración en segundos.
         */
        return (int) apply_filters( 'wp_autofirma_intermediate_session_lifetime', 15 * MINUTE_IN_SECONDS );
    }

    /**
     * Devuelve cuánto vive cada dato en tránsito.
     *
     * @return int
     */
    private static function payload_lifetime() {
        /**
         * Segundos que un dato espera a ser recogido.
         *
         * @param int $seconds Duración en segundos.
         */
        return (int) apply_filters( 'wp_autofirma_intermediate_payload_lifetime', 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Devuelve el tamaño máximo admitido.
     *
     * @return int
     */
    private static function max_payload() {
        /**
         * Tamaño máximo de un dato en tránsito.
         *
         * @param int $bytes Tamaño en bytes.
         */
        return (int) apply_filters( 'wp_autofirma_intermediate_max_payload', 20 * MB_IN_BYTES );
    }
}

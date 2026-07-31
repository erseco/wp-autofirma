<?php
/**
 * Orquestación principal del plugin.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

/**
 * Registra los componentes del plugin.
 */
final class Plugin {

    /**
     * Instancia compartida.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Devuelve la instancia compartida.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Registra los hooks de WordPress.
     *
     * @return void
     */
    public function register() {
        $document_service     = new Document_Service();
        $signature_repository = new Signature_Repository();
        $rest_controller      = new Rest_Controller(
            $document_service,
            $signature_repository
        );
        $media_page           = new Media_Page();

        add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );
        add_action( 'admin_menu', array( $media_page, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( $media_page, 'enqueue_assets' ) );
        add_filter( 'media_row_actions', array( $media_page, 'add_media_action' ), 10, 2 );
    }

    /**
     * Impide crear instancias externas.
     */
    private function __construct() {
    }
}

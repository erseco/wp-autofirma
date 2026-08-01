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
        $signature_index      = new Signature_Index();
        $media_library        = new Media_Library( $signature_index );
        $shortcodes           = new Shortcodes( $signature_index );

        $intermediate = new Intermediate_Controller();

        add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );
        add_action( 'rest_api_init', array( $intermediate, 'register_routes' ) );
        add_filter( 'rest_pre_serve_request', array( $intermediate, 'serve_as_text' ), 10, 3 );
        add_action( 'admin_menu', array( $media_page, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( $media_page, 'enqueue_assets' ) );
        add_filter( 'media_row_actions', array( $media_page, 'add_media_action' ), 10, 2 );

        add_action( 'add_attachment', array( $signature_index, 'scan_new_attachment' ) );
        add_action( 'admin_enqueue_scripts', array( $media_library, 'enqueue_styles' ) );
        add_filter( 'manage_media_columns', array( $media_library, 'add_column' ) );
        add_action( 'manage_media_custom_column', array( $media_library, 'render_column' ), 10, 2 );
        add_filter( 'attachment_fields_to_edit', array( $media_library, 'add_attachment_fields' ), 10, 2 );

        $shortcodes->register();
    }

    /**
     * Impide crear instancias externas.
     */
    private function __construct() {
    }
}

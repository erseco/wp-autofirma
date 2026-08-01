<?php
/**
 * Pruebas de integración del registro de componentes.
 *
 * @package WPAutoFirma
 */

use Erseco\WPAutoFirma\Plugin;

/**
 * Comprueba que el plugin engancha todo lo que dice enganchar.
 *
 * El registro ocurre durante el arranque, antes de que empiece a medirse nada,
 * así que aquí se vuelve a ejecutar sobre una copia limpia de los hooks: se
 * comprueba el registro de verdad, no una imitación.
 */
class PluginTest extends WP_UnitTestCase {

    /**
     * Hooks previos, para devolverlos al terminar.
     *
     * @var array
     */
    private $filters = array();

    /**
     * Guarda los hooks y los deja vacíos.
     */
    public function set_up() {
        parent::set_up();

        global $wp_filter;
        $this->filters = $wp_filter;
        $wp_filter     = array();
    }

    /**
     * Devuelve los hooks a como estaban.
     */
    public function tear_down() {
        global $wp_filter;
        $wp_filter = $this->filters;

        parent::tear_down();
    }

    /**
     * La instancia compartida es siempre la misma.
     */
    public function test_instance_is_shared() {
        $this->assertSame( Plugin::instance(), Plugin::instance() );
    }

    /**
     * Registrar deja enganchado todo lo que el plugin necesita.
     */
    public function test_register_hooks_every_component() {
        Plugin::instance()->register();

        $esperados = array(
            'rest_api_init',
            'admin_menu',
            'admin_enqueue_scripts',
            'media_row_actions',
            'add_attachment',
            'manage_media_columns',
            'manage_media_custom_column',
            'attachment_fields_to_edit',
            'rest_pre_serve_request',
        );

        foreach ( $esperados as $hook ) {
            $this->assertTrue( has_action( $hook ) || has_filter( $hook ), 'Falta enganchar ' . $hook );
        }
    }

    /**
     * Y deja registrados los tres shortcodes.
     */
    public function test_register_adds_the_shortcodes() {
        global $shortcode_tags;
        $previos        = $shortcode_tags;
        $shortcode_tags = array();

        Plugin::instance()->register();

        $encontrados    = array_keys( $shortcode_tags );
        $shortcode_tags = $previos;

        $this->assertContains( 'autofirma_signature_status', $encontrados );
        $this->assertContains( 'autofirma_signature_info', $encontrados );
        $this->assertContains( 'autofirma_signed_documents', $encontrados );
    }
}

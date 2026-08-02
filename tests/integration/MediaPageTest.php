<?php
/**
 * Pruebas de integración de la pantalla de firma.
 *
 * @package WPAutoFirma
 */

use Erseco\WPAutoFirma\Media_Page;

/**
 * Comprueba el registro de la pantalla, lo que se le entrega al navegador y lo
 * que se pinta según haya o no un PDF seleccionado.
 */
class MediaPageTest extends WP_UnitTestCase {

    /**
     * Pantalla bajo prueba.
     *
     * @var Media_Page
     */
    private $page;

    /**
     * Adjunto PDF.
     *
     * @var int
     */
    private $attachment_id;

    /**
     * Entra como administrador y prepara la pantalla.
     */
    public function set_up() {
        parent::set_up();

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';

        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

        // Las colas de scripts y estilos no se reinician entre pruebas, y un
        // identificador ya registrado ignora la fuente nueva.
        $GLOBALS['wp_scripts'] = null;
        $GLOBALS['wp_styles']  = null;

        $this->page          = new Media_Page();
        $this->attachment_id = self::factory()->attachment->create_upload_object(
            dirname( __DIR__ ) . '/php/fixtures/unsigned.pdf'
        );
    }

    /**
     * Limpia lo encolado entre pruebas.
     */
    public function tear_down() {
        unset( $_GET['attachment_id'] );

        parent::tear_down();
    }

    /**
     * La pantalla se registra pero no aparece en el menú.
     *
     * Existe para respetar la capacidad, y se llega a ella desde la acción del
     * propio adjunto: un elemento de menú suelto llevaría a una página sin
     * documento con el que hacer nada.
     */
    public function test_page_is_registered_but_hidden_from_the_menu() {
        global $submenu, $_registered_pages;

        $submenu           = array();
        $_registered_pages = array();

        $this->page->register_page();

        $slugs = array_column( isset( $submenu['upload.php'] ) ? $submenu['upload.php'] : array(), 2 );

        $this->assertArrayHasKey( 'admin_page_wp-autofirma-sign', $_registered_pages );
        $this->assertNotContains( 'wp-autofirma-sign', $slugs );
    }

    /**
     * Sin PDF seleccionado se explica cómo llegar a la pantalla.
     */
    public function test_without_a_document_it_explains_what_to_do() {
        ob_start();
        $this->page->render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Selecciona un PDF', $output );
        $this->assertStringNotContainsString( 'id="wp-autofirma-sign"', $output );
    }

    /**
     * Con un PDF seleccionado se ofrece el botón de firmar.
     */
    public function test_with_a_pdf_it_offers_the_button() {
        $_GET['attachment_id'] = (string) $this->attachment_id;

        ob_start();
        $this->page->render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'id="wp-autofirma-sign"', $output );
        $this->assertStringContainsString( get_the_title( $this->attachment_id ), $output );
    }

    /**
     * La tarjeta ofrece los controles del sello visible.
     */
    public function test_visible_signature_fields_are_offered() {
        $_GET['attachment_id'] = (string) $this->attachment_id;

        ob_start();
        $this->page->render_page();
        $output = ob_get_clean();

        foreach ( array( 'layer2-text', 'page', 'left', 'bottom', 'right', 'top' ) as $campo ) {
            $this->assertStringContainsString( 'wp-autofirma-' . $campo, $output );
        }

        $this->assertStringContainsString( '$$SUBJECTCN$$', $output );
    }

    /**
     * Los valores iniciales del sello se pueden filtrar.
     */
    public function test_visible_signature_defaults_can_be_filtered() {
        add_filter( 'wp_autofirma_visible_signature_defaults', array( $this, 'own_defaults' ) );

        $defaults = Media_Page::visible_signature_defaults();

        remove_filter( 'wp_autofirma_visible_signature_defaults', array( $this, 'own_defaults' ) );

        $this->assertSame( 7, $defaults['page'] );
    }

    /**
     * Devuelve unos valores propios para la prueba anterior.
     *
     * @param array $defaults Valores originales.
     * @return array
     */
    public function own_defaults( $defaults ) {
        $defaults['page'] = 7;

        return $defaults;
    }

    /**
     * Un adjunto que no es PDF no ofrece el botón.
     */
    public function test_a_non_pdf_does_not_offer_the_button() {
        $_GET['attachment_id'] = (string) self::factory()->attachment->create(
            array( 'post_mime_type' => 'image/jpeg' )
        );

        ob_start();
        $this->page->render_page();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'id="wp-autofirma-sign"', $output );
    }

    /**
     * Fuera de su pantalla no se carga nada.
     *
     * AutoScript pesa, y no tiene por qué viajar en cada página del escritorio.
     */
    public function test_assets_are_not_loaded_on_other_screens() {
        $this->page->register_page();
        $this->page->enqueue_assets( 'index.php' );

        $this->assertFalse( wp_script_is( 'wp-autofirma-autoscript', 'enqueued' ) );
        $this->assertFalse( wp_script_is( 'wp-autofirma-admin', 'enqueued' ) );
    }

    /**
     * En su pantalla se encola AutoScript y el bundle que depende de él.
     */
    public function test_assets_are_loaded_on_the_signing_screen() {
        $hook = $this->register_and_get_hook();

        $this->page->enqueue_assets( $hook );

        $this->assertTrue( wp_script_is( 'wp-autofirma-autoscript', 'enqueued' ) );
        $this->assertTrue( wp_script_is( 'wp-autofirma-admin', 'enqueued' ) );
        $this->assertTrue( wp_style_is( 'wp-autofirma-admin', 'enqueued' ) );
    }

    /**
     * La configuración que recibe el navegador lleva lo imprescindible.
     */
    public function test_browser_settings_carry_nonce_and_routes() {
        $_GET['attachment_id'] = (string) $this->attachment_id;

        $this->page->enqueue_assets( $this->register_and_get_hook() );

        $data = wp_scripts()->get_data( 'wp-autofirma-admin', 'data' );

        $this->assertStringContainsString( 'wpAutoFirmaSettings', (string) $data );
        $this->assertStringContainsString( 'wp-autofirma/v1', (string) $data );
        // `wp_localize_script()` convierte todos los valores a cadena, así que
        // el identificador llega entrecomillado. El navegador solo lo usa para
        // componer la ruta REST, de modo que le da igual.
        $this->assertStringContainsString( '"attachmentId":"' . $this->attachment_id . '"', (string) $data );
        $this->assertStringContainsString( 'nonce', (string) $data );
    }

    /**
     * El sitio puede servir su propia copia de AutoScript.
     */
    public function test_site_can_override_the_autoscript_url() {
        add_filter( 'wp_autofirma_autoscript_url', array( $this, 'custom_autoscript_url' ) );

        $this->page->enqueue_assets( $this->register_and_get_hook() );

        $source = wp_scripts()->registered['wp-autofirma-autoscript']->src;

        remove_filter( 'wp_autofirma_autoscript_url', array( $this, 'custom_autoscript_url' ) );

        $this->assertSame( 'https://example.org/autoscript.js', $source );
    }

    /**
     * Devuelve una URL propia para la prueba anterior.
     *
     * @return string
     */
    public function custom_autoscript_url() {
        return 'https://example.org/autoscript.js';
    }

    /**
     * Registra la pantalla y devuelve su sufijo.
     *
     * @return string
     */
    private function register_and_get_hook() {
        global $submenu, $_registered_pages, $_parent_pages;

        $submenu           = array();
        $_registered_pages = array();
        $_parent_pages     = array();

        $this->page->register_page();

        return get_plugin_page_hookname( 'wp-autofirma-sign', 'upload.php' );
    }
}

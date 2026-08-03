<?php
/**
 * Pruebas de integración de los shortcodes.
 *
 * @package WPAutoFirma
 */

/**
 * Ejercita quién ve el estado de firma y quién no.
 *
 * Estas reglas son la parte delicada: los shortcodes se pintan en el frontal,
 * donde puede mirar cualquiera, y el nombre de quien firma es un dato personal.
 * Sin WordPress cargado no hay forma de comprobarlas, porque dependen de cómo
 * resuelve el núcleo la visibilidad de un adjunto.
 */
class ShortcodesTest extends WP_UnitTestCase {

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
     * Sube los dos documentos de partida.
     */
    public function set_up() {
        parent::set_up();

        $this->signed_id   = self::factory()->attachment->create_upload_object(
            dirname( __DIR__ ) . '/php/fixtures/signed.pdf'
        );
        $this->unsigned_id = self::factory()->attachment->create_upload_object(
            dirname( __DIR__ ) . '/php/fixtures/unsigned.pdf'
        );
    }

    /**
     * Un adjunto público se describe a quien visita sin haber entrado.
     *
     * Es el caso que fallaba: `read_post` acaba mapeando a `read`, y quien no ha
     * entrado no tiene ninguna capacidad, de modo que un documento
     * perfectamente público no se pintaba.
     */
    public function test_anonymous_visitor_sees_a_public_attachment() {
        wp_set_current_user( 0 );

        $output = do_shortcode( '[autofirma_signature_info id="' . $this->signed_id . '"]' );

        $this->assertStringContainsString( 'Firmado digitalmente', $output );
        $this->assertStringContainsString( 'FIRMANTE DE PRUEBA', $output );
    }

    /**
     * Un adjunto que cuelga de un borrador no se describe a nadie de fuera.
     */
    public function test_anonymous_visitor_does_not_see_a_private_attachment() {
        $draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
        wp_update_post(
            array(
                'ID'          => $this->signed_id,
                'post_parent' => $draft,
            )
        );
        clean_post_cache( $this->signed_id );
        wp_set_current_user( 0 );

        $this->assertSame( '', do_shortcode( '[autofirma_signature_info id="' . $this->signed_id . '"]' ) );
        $this->assertSame( '', do_shortcode( '[autofirma_signature_status id="' . $this->signed_id . '"]' ) );
    }

    /**
     * Quien puede editarlo sí lo ve aunque no sea público.
     */
    public function test_editor_sees_a_private_attachment() {
        $draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
        wp_update_post(
            array(
                'ID'          => $this->signed_id,
                'post_parent' => $draft,
            )
        );
        clean_post_cache( $this->signed_id );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

        $this->assertStringContainsString(
            'Firmado digitalmente',
            do_shortcode( '[autofirma_signature_info id="' . $this->signed_id . '"]' )
        );
    }

    /**
     * Un documento sin firma se anuncia como tal.
     */
    public function test_unsigned_document_is_reported_as_unsigned() {
        $output = do_shortcode( '[autofirma_signature_status id="' . $this->unsigned_id . '"]' );

        $this->assertStringContainsString( 'Sin firma digital', $output );
    }

    /**
     * El texto puede sustituirse por uno propio.
     */
    public function test_custom_label_replaces_the_default() {
        $output = do_shortcode(
            '[autofirma_signature_status id="' . $this->unsigned_id . '" unsigned="Pendiente de firma"]'
        );

        $this->assertStringContainsString( 'Pendiente de firma', $output );
        $this->assertStringNotContainsString( 'Sin firma digital', $output );
    }

    /**
     * Un identificador que no existe no pinta nada.
     *
     * Ni un aviso ni un hueco: delatarían la existencia del documento.
     */
    public function test_unknown_identifier_renders_nothing() {
        $this->assertSame( '', do_shortcode( '[autofirma_signature_status id="999999"]' ) );
    }

    /**
     * Un identificador que no es un adjunto tampoco.
     */
    public function test_non_attachment_renders_nothing() {
        $post = self::factory()->post->create( array( 'post_status' => 'publish' ) );

        $this->assertSame( '', do_shortcode( '[autofirma_signature_info id="' . $post . '"]' ) );
    }

    /**
     * El listado recoge los documentos firmados y deja fuera los demás.
     */
    public function test_listing_only_includes_signed_documents() {
        $output = do_shortcode( '[autofirma_signed_documents]' );

        $this->assertStringContainsString( get_the_title( $this->signed_id ), $output );
        $this->assertStringNotContainsString( get_the_title( $this->unsigned_id ), $output );
    }

    /**
     * El filtro permite endurecer quién ve los datos.
     */
    public function test_filter_can_hide_the_status() {
        add_filter( 'wp_autofirma_can_read_signature', '__return_false' );

        $output = do_shortcode( '[autofirma_signature_info id="' . $this->signed_id . '"]' );

        remove_filter( 'wp_autofirma_can_read_signature', '__return_false' );

        $this->assertSame( '', $output );
    }

    /**
     * La advertencia de que no se ha validado acompaña a la ficha.
     */
    public function test_disclaimer_is_shown_with_a_signature() {
        $output = do_shortcode( '[autofirma_signature_info id="' . $this->signed_id . '"]' );

        $this->assertStringContainsString( 'No se ha validado', $output );
    }

    /**
     * El listado deja fuera lo que quien mira no puede ver.
     *
     * Es la misma regla que en el resto de shortcodes, pero aquí importa más:
     * el listado no lo escribe nadie con un identificador delante, así que sin
     * esta comprobación enseñaría todo lo firmado del sitio a cualquiera.
     */
    public function test_listing_hides_documents_the_visitor_cannot_read() {
        wp_update_post(
            array(
                'ID'         => $this->signed_id,
                'post_title' => 'Resolución publicada',
            )
        );

        $reservado = self::factory()->attachment->create_upload_object(
            dirname( __DIR__ ) . '/php/fixtures/signed.pdf'
        );
        $borrador  = self::factory()->post->create( array( 'post_status' => 'draft' ) );

        wp_update_post(
            array(
                'ID'          => $reservado,
                'post_title'  => 'Resolución reservada',
                'post_parent' => $borrador,
            )
        );
        clean_post_cache( $reservado );

        wp_set_current_user( 0 );

        $output = do_shortcode( '[autofirma_signed_documents]' );

        $this->assertStringContainsString( 'Resolución publicada', $output );
        $this->assertStringNotContainsString( 'Resolución reservada', $output );
    }

    /**
     * Sin documentos firmados no se pinta nada.
     *
     * Ni siquiera una lista vacía: dejaría un hueco en la página.
     */
    public function test_listing_renders_nothing_without_signed_documents() {
        wp_delete_attachment( $this->signed_id, true );

        $this->assertSame( '', do_shortcode( '[autofirma_signed_documents]' ) );
    }

    /**
     * Sin `id`, el estado se toma del adjunto que se está mostrando.
     *
     * Es lo que permite poner el shortcode en la plantilla de adjunto sin
     * repetir el identificador documento a documento.
     */
    public function test_status_falls_back_to_the_attachment_being_displayed() {
        $GLOBALS['post'] = get_post( $this->signed_id );

        $output = do_shortcode( '[autofirma_signature_status]' );

        unset( $GLOBALS['post'] );

        $this->assertStringContainsString( 'Firmado digitalmente', $output );
    }
}

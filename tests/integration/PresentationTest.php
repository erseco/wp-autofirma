<?php
/**
 * Pruebas de integración de la presentación y la lectura de documentos.
 *
 * @package WPAutoFirma
 */

use Erseco\WPAutoFirma\Document_Service;
use Erseco\WPAutoFirma\Media_Library;
use Erseco\WPAutoFirma\Signature_Detector;
use Erseco\WPAutoFirma\Signature_Index;
use Erseco\WPAutoFirma\Signature_Presenter;

/**
 * Cubre lo que se dice de una firma y lo que se responde cuando un documento
 * no puede leerse.
 */
class PresentationTest extends WP_UnitTestCase {

    /**
     * Rehace las colas de estilos, que no se reinician entre pruebas.
     */
    public function set_up() {
        parent::set_up();

        $GLOBALS['wp_styles'] = null;
    }

    /**
     * Un estado sin analizar se describe como tal.
     */
    public function test_unknown_format_is_described() {
        $this->assertSame(
            'Formato no analizable',
            Signature_Presenter::summary( Signature_Detector::UNKNOWN )
        );
    }

    /**
     * Varias firmas se cuentan en el resumen.
     */
    public function test_several_signatures_are_counted() {
        $status = array_merge(
            Signature_Detector::UNKNOWN,
            array(
                'known'      => true,
                'signed'     => true,
                'signatures' => 3,
            )
        );

        $this->assertSame( 'Firmado digitalmente (3 firmas)', Signature_Presenter::summary( $status ) );
    }

    /**
     * Un documento sin firma se describe con una sola fila.
     */
    public function test_unsigned_status_has_a_single_row() {
        $status = array_merge( Signature_Detector::UNKNOWN, array( 'known' => true ) );

        $rows = Signature_Presenter::rows( $status );

        $this->assertCount( 1, $rows );
        $this->assertSame( 'Estado', $rows[0]['label'] );
    }

    /**
     * Un firmante sin nombre no genera fila.
     *
     * Ocurre cuando el certificado embebido no trae un nombre reconocible:
     * mejor omitirlo que enseñar una fila vacía.
     */
    public function test_nameless_signer_is_skipped() {
        $status = array_merge(
            Signature_Detector::UNKNOWN,
            array(
                'known'      => true,
                'signed'     => true,
                'format'     => 'CAdES',
                'signatures' => 1,
                'signers'    => array(
                    array(
                        'name'       => '',
                        'issuer'     => 'Autoridad',
                        'serial'     => '',
                        'valid_from' => 0,
                        'valid_to'   => 0,
                    ),
                ),
            )
        );

        $labels = array_column( Signature_Presenter::rows( $status ), 'label' );

        $this->assertNotContains( 'Firmante declarado', $labels );
    }

    /**
     * Un firmante sin emisor se describe igualmente.
     */
    public function test_signer_without_issuer_is_described() {
        $status = array_merge(
            Signature_Detector::UNKNOWN,
            array(
                'known'      => true,
                'signed'     => true,
                'format'     => 'CAdES',
                'signatures' => 1,
                'signers'    => array(
                    array(
                        'name'       => 'ALGUIEN',
                        'issuer'     => '',
                        'serial'     => '',
                        'valid_from' => 0,
                        'valid_to'   => 0,
                    ),
                ),
            )
        );

        $values = array_column( Signature_Presenter::rows( $status ), 'value' );

        $this->assertContains( 'ALGUIEN', $values );
    }

    /**
     * Una fecha que no se entiende no se muestra.
     */
    public function test_unparsable_date_is_omitted() {
        $status = array_merge(
            Signature_Detector::UNKNOWN,
            array(
                'known'      => true,
                'signed'     => true,
                'format'     => 'PAdES',
                'signatures' => 1,
                'signed_at'  => 'no es una fecha',
            )
        );

        $labels = array_column( Signature_Presenter::rows( $status ), 'label' );

        $this->assertNotContains( 'Fecha declarada de firma', $labels );
    }

    /**
     * El estilo de la columna solo se carga en la biblioteca.
     */
    public function test_column_style_is_only_loaded_in_the_library() {
        $library = new Media_Library( new Signature_Index() );

        $library->enqueue_styles( 'index.php' );
        $this->assertFalse( wp_style_is( 'wp-autofirma-media-library', 'enqueued' ) );

        $library->enqueue_styles( 'upload.php' );
        $this->assertTrue( wp_style_is( 'wp-autofirma-media-library', 'enqueued' ) );
    }

    /**
     * Y no se carga si la señalización está desactivada.
     */
    public function test_column_style_respects_the_filter() {
        add_filter( 'wp_autofirma_show_signature_status', '__return_false' );

        $library = new Media_Library( new Signature_Index() );
        $library->enqueue_styles( 'upload.php' );

        remove_filter( 'wp_autofirma_show_signature_status', '__return_false' );

        $this->assertFalse( wp_style_is( 'wp-autofirma-media-library', 'enqueued' ) );
    }

    /**
     * Un adjunto inexistente no puede leerse.
     */
    public function test_missing_document_cannot_be_read() {
        $this->expectException( RuntimeException::class );

        ( new Document_Service() )->get_document( 999999 );
    }

    /**
     * Solo se admiten PDF.
     */
    public function test_only_pdf_documents_are_served() {
        $attachment_id = self::factory()->attachment->create_upload_object(
            dirname( __DIR__ ) . '/php/fixtures/signed.odt'
        );

        $this->expectException( RuntimeException::class );

        ( new Document_Service() )->get_document( $attachment_id );
    }

    /**
     * Un documento por encima del límite se rechaza.
     */
    public function test_oversized_document_is_rejected() {
        $attachment_id = self::factory()->attachment->create_upload_object(
            dirname( __DIR__ ) . '/php/fixtures/unsigned.pdf'
        );

        add_filter( 'wp_autofirma_max_document_size', array( $this, 'tiny_limit' ) );

        try {
            $this->expectException( RuntimeException::class );

            ( new Document_Service() )->get_document( $attachment_id );
        } finally {
            remove_filter( 'wp_autofirma_max_document_size', array( $this, 'tiny_limit' ) );
        }
    }

    /**
     * Un documento que existe pero no se deja leer se responde como tal.
     *
     * No es lo mismo que no encontrarlo ni que no admitir su formato, y el
     * mensaje tiene que distinguirlo: apunta a un problema del alojamiento.
     */
    public function test_an_unreadable_document_is_reported() {
        WP_AutoFirma_Unreadable_Stream::register();

        $attachment_id = self::factory()->attachment->create(
            array( 'post_mime_type' => 'application/pdf' )
        );

        add_filter( 'get_attached_file', array( $this, 'unreadable_path' ) );

        // El fallo de lectura genera un aviso de PHP, que aquí es el resultado
        // esperado: se silencia mientras dura la llamada.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- No es depuración: silencia el aviso que la propia prueba provoca a propósito.
        set_error_handler(
            static function () {
                return true;
            }
        );

        try {
            ( new Document_Service() )->get_document( $attachment_id );
            $mensaje = '';
        } catch ( RuntimeException $excepcion ) {
            $mensaje = $excepcion->getMessage();
        } finally {
            restore_error_handler();
            remove_filter( 'get_attached_file', array( $this, 'unreadable_path' ) );
            WP_AutoFirma_Unreadable_Stream::unregister();
        }

        $this->assertSame( 'No se pudo cargar el documento.', $mensaje );
    }

    /**
     * Devuelve una ruta que no se deja leer, para la prueba anterior.
     *
     * @return string
     */
    public function unreadable_path() {
        return WP_AutoFirma_Unreadable_Stream::path();
    }

    /**
     * Devuelve un límite diminuto para la prueba anterior.
     *
     * @return int
     */
    public function tiny_limit() {
        return 8;
    }
}

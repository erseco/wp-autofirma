<?php
/**
 * Pruebas del detector de firmas.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma\Tests;

use Erseco\WPAutoFirma\Signature_Detector;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba la detección sobre ficheros de cada formato.
 */
final class SignatureDetectorTest extends TestCase {

    /**
     * Devuelve la ruta de un fichero de prueba.
     *
     * @param string $name Nombre del fichero.
     * @return string
     */
    private function fixture( $name ) {
        return __DIR__ . '/fixtures/' . $name;
    }

    /**
     * Un PDF con firma incorporada se reconoce como PAdES.
     */
    public function test_detects_pades_in_pdf() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.pdf' ) );

        $this->assertTrue( $result['signed'] );
        $this->assertSame( 'PAdES', $result['format'] );
        $this->assertSame( 1, $result['signatures'] );
        $this->assertSame( 'PAdES (ETSI.CAdES.detached)', $result['profile'] );
    }

    /**
     * La fecha que declara la firma se lee del propio documento.
     */
    public function test_reads_declared_signing_date() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.pdf' ) );

        $this->assertSame( '2026-08-01 12:00:00', $result['signed_at'] );
    }

    /**
     * El firmante sale del certificado embebido, no de lo que diga nadie.
     */
    public function test_extracts_signer_from_embedded_certificate() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.pdf' ) );

        $this->assertCount( 1, $result['signers'] );
        $this->assertSame( 'FIRMANTE DE PRUEBA', $result['signers'][0]['name'] );
        $this->assertGreaterThan( 0, $result['signers'][0]['valid_to'] );
    }

    /**
     * Un PDF sin firma no puede dar positivo.
     */
    public function test_unsigned_pdf_is_not_reported_as_signed() {
        $result = Signature_Detector::inspect( $this->fixture( 'unsigned.pdf' ) );

        $this->assertFalse( $result['signed'] );
        $this->assertTrue( $result['known'] );
        $this->assertSame( 'PDF', $result['format'] );
        $this->assertSame( array(), $result['signers'] );
    }

    /**
     * Las firmas CAdES sueltas se reconocen en DER.
     */
    public function test_detects_cades_in_der() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.csig' ) );

        $this->assertTrue( $result['signed'] );
        $this->assertSame( 'CAdES', $result['format'] );
        $this->assertSame( 'FIRMANTE DE PRUEBA', $result['signers'][0]['name'] );
    }

    /**
     * Las firmas CAdES envueltas en Base64 con armadura PEM también.
     *
     * Sin quitar la cabecera, sus letras se colarían en el Base64 y la firma
     * pasaría por no detectada.
     */
    public function test_detects_cades_wrapped_in_pem() {
        $der = (string) file_get_contents( $this->fixture( 'signed.csig' ) );
        $pem = "-----BEGIN PKCS7-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PKCS7-----\n";

        $result = Signature_Detector::detect( $pem );

        $this->assertTrue( $result['signed'] );
        $this->assertSame( 'CAdES', $result['format'] );
    }

    /**
     * De la cadena que viaja dentro sale la hoja, no la autoridad.
     *
     * Un PKCS#7 lleva todos los certificados hasta la raíz. Si se presentara la
     * autoridad como firmante, la ficha del documento diría que lo firmó la
     * entidad emisora en lugar de quien lo firmó de verdad.
     */
    public function test_certificate_authority_is_not_reported_as_the_signer() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed-cadena.csig' ) );

        $this->assertCount( 1, $result['signers'] );
        $this->assertSame( 'FIRMANTE CON CADENA', $result['signers'][0]['name'] );
        $this->assertSame( 'AUTORIDAD DE PRUEBA', $result['signers'][0]['issuer'] );
    }

    /**
     * Un titular sin CN, OU ni O deja el nombre vacío en lugar de inventarlo.
     *
     * Los hay: algunos certificados identifican con el número de documento y
     * poco más. La firma se detecta igual, y quien la presenta ya decide qué
     * hacer con un firmante sin nombre.
     */
    public function test_subject_without_a_usable_name_leaves_it_empty() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed-sin-nombre.csig' ) );

        $this->assertTrue( $result['signed'] );
        $this->assertCount( 1, $result['signers'] );
        $this->assertSame( '', $result['signers'][0]['name'] );
        $this->assertSame( 'AUTORIDAD DE PRUEBA', $result['signers'][0]['issuer'] );
    }

    /**
     * Las firmas XAdES se distinguen de un XMLDSig cualquiera.
     */
    public function test_detects_xades() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.xsig' ) );

        $this->assertTrue( $result['signed'] );
        $this->assertSame( 'XAdES', $result['format'] );
        $this->assertSame( '2026-08-01T12:00:00Z', $result['signed_at'] );
    }

    /**
     * Las etiquetas de cierre no cuentan como firmas.
     */
    public function test_counts_each_xml_signature_once() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.xsig' ) );

        $this->assertSame( 1, $result['signatures'] );
    }

    /**
     * Los contenedores OpenDocument declaran sus firmas en una entrada propia.
     */
    public function test_detects_opendocument_signature() {
        $result = Signature_Detector::inspect( $this->fixture( 'signed.odt' ) );

        $this->assertTrue( $result['signed'] );
        $this->assertSame( 'XAdES', $result['format'] );
    }

    /**
     * Un certificado suelto no es un documento firmado.
     */
    public function test_certificate_alone_is_not_a_signature() {
        $result = Signature_Detector::detect(
            "-----BEGIN CERTIFICATE-----\nQUJD\n-----END CERTIFICATE-----\n"
        );

        $this->assertFalse( $result['signed'] );
    }

    /**
     * Un contenido cualquiera no dispara la detección.
     *
     * @dataProvider provide_unsigned_content
     *
     * @param string $bytes Contenido de entrada.
     */
    public function test_plain_content_is_not_signed( $bytes ) {
        $result = Signature_Detector::detect( $bytes );

        $this->assertFalse( $result['signed'] );
    }

    /**
     * Contenidos que no llevan firma.
     *
     * @return array<string, array<int, string>>
     */
    public function provide_unsigned_content() {
        return array(
            'vacío'          => array( '' ),
            'texto'          => array( 'un documento cualquiera' ),
            'json'           => array( '{"firma":"no"}' ),
            'xml sin firmar' => array( '<?xml version="1.0"?><doc><Signature>texto</Signature></doc>' ),
            'binario'        => array( "\x00\x01\x02\x03" ),
        );
    }

    /**
     * Un fichero que no existe no revienta la detección.
     */
    public function test_missing_file_is_reported_as_unknown() {
        $result = Signature_Detector::inspect( $this->fixture( 'no-existe.pdf' ) );

        $this->assertFalse( $result['signed'] );
        $this->assertFalse( $result['known'] );
    }

    /**
     * Los ficheros grandes se examinan por ventanas, sin cargarlos enteros.
     *
     * La firma se coloca al final a propósito: si solo se leyera el principio,
     * este documento pasaría por no firmado.
     */
    public function test_large_file_is_scanned_by_windows() {
        $signed = (string) file_get_contents( $this->fixture( 'signed.pdf' ) );
        $filler = str_repeat( "% relleno\n", 400000 );
        $path   = tempnam( sys_get_temp_dir(), 'wpaf' );

        file_put_contents(
            $path,
            "%PDF-1.7\n" . $filler . substr( $signed, 9 )
        );

        $result = Signature_Detector::inspect( $path, 1048576 );
        $peak   = memory_get_peak_usage( true );

        unlink( $path );

        $this->assertTrue( $result['signed'] );
        $this->assertLessThan( 64 * 1024 * 1024, $peak );
    }
}

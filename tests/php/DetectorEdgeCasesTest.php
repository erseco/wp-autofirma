<?php
/**
 * Pruebas de los casos límite del detector y del almacén.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma\Tests;

use Erseco\WPAutoFirma\Signature_Detector;
use Erseco\WPAutoFirma\Transient_Store;
use PHPUnit\Framework\TestCase;

/**
 * Cubre los formatos poco frecuentes y los caminos que no llevan a nada.
 */
final class DetectorEdgeCasesTest extends TestCase {

	/**
	 * Vacía los transients simulados entre pruebas.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_autofirma_test_transients']        = array();
		$GLOBALS['wp_autofirma_test_transients_stolen'] = false;
	}

	/**
	 * Un fichero vacío no se examina.
	 */
	public function test_empty_file_is_unknown() {
		$path = tempnam( sys_get_temp_dir(), 'wpaf' );

		$result = Signature_Detector::inspect( $path );

		unlink( $path );

		$this->assertFalse( $result['known'] );
	}

	/**
	 * Una ruta que no es una cadena tampoco.
	 */
	public function test_invalid_path_is_unknown() {
		$this->assertFalse( Signature_Detector::inspect( '' )['known'] );
		$this->assertFalse( Signature_Detector::inspect( null )['known'] );
	}

	/**
	 * Un sello de tiempo de documento se reconoce como tal.
	 */
	public function test_document_timestamp_is_described() {
		$pdf = "%PDF-1.7\n/Type /Sig /SubFilter /ETSI.RFC3161 /ByteRange [0 1 2 3] /Contents <00>\n%%EOF";

		$result = Signature_Detector::detect( $pdf );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( 'Sello de tiempo del documento (ETSI.RFC3161)', $result['profile'] );
	}

	/**
	 * Un PDF firmado sin SubFilter conocido no inventa un perfil.
	 */
	public function test_unknown_subfilter_leaves_the_profile_empty() {
		$pdf = "%PDF-1.7\n/SubFilter /Algo.Raro /ByteRange [0 1 2 3]\n%%EOF";

		$this->assertSame( '', Signature_Detector::detect( $pdf )['profile'] );
	}

	/**
	 * Un fichero que no se deja leer se responde como desconocido.
	 *
	 * La ruta existe y declara tamaño, pero la lectura falla. Antes que dar por
	 * no firmado lo que no se ha podido mirar, el detector responde que no lo
	 * sabe.
	 */
	public function test_unreadable_content_is_unknown() {
		\WP_AutoFirma_Unreadable_Stream::register();

		// El fallo de lectura genera un aviso de PHP, que aquí es el resultado
		// esperado: se silencia mientras dura la llamada.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- No es depuración: silencia el aviso que la propia prueba provoca a propósito.
		set_error_handler(
			static function () {
				return true;
			}
		);
		$result = Signature_Detector::inspect( \WP_AutoFirma_Unreadable_Stream::path() );
		restore_error_handler();

		\WP_AutoFirma_Unreadable_Stream::unregister();

		$this->assertFalse( $result['known'] );
		$this->assertFalse( $result['signed'] );
	}

	/**
	 * Los PDF firmados a la antigua se describen con su SubFilter.
	 *
	 * Son anteriores a PAdES y siguen circulando. No se pueden llamar PAdES,
	 * pero llevan firma y hay que decirlo.
	 */
	public function test_classic_pdf_signature_is_described() {
		$pdf = "%PDF-1.7\n/Type /Sig /SubFilter /adbe.pkcs7.detached /ByteRange [0 1 2 3]\n%%EOF";

		$result = Signature_Detector::detect( $pdf );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( 'PDF clásico (adbe.pkcs7.detached)', $result['profile'] );
	}

	/**
	 * Una estructura DER que no es un `signedData` no es una firma.
	 *
	 * Un certificado suelto en DER empieza igual que una firma CAdES, así que
	 * lo único que los distingue es el OID del PKCS#7. Sin comprobarlo, un
	 * certificado subido a la biblioteca pasaría por documento firmado.
	 */
	public function test_der_without_the_signed_data_oid_is_not_a_signature() {
		$certificate = self::first_certificate(
			(string) file_get_contents( __DIR__ . '/fixtures/signed.csig' )
		);

		$result = Signature_Detector::detect( $certificate );

		$this->assertFalse( $result['signed'] );
		$this->assertFalse( $result['known'] );
	}

	/**
	 * Un mismo firmante en dos firmas aparece una sola vez.
	 *
	 * Quien firma dos veces el mismo PDF deja su certificado repetido.
	 * Enumerarlo dos veces daría a entender que son dos personas distintas.
	 */
	public function test_repeated_signer_is_listed_once() {
		$pdf = (string) file_get_contents( __DIR__ . '/fixtures/signed.pdf' );

		$this->assertSame(
			1,
			preg_match( '#/ByteRange[^>]*/Contents\s*<[0-9A-Fa-f]+>#', $pdf, $signature ),
			'La firma del fichero de prueba debe poder duplicarse.'
		);

		$result = Signature_Detector::detect(
			str_replace( $signature[0], $signature[0] . ' ' . $signature[0], $pdf )
		);

		$this->assertSame( 2, $result['signatures'] );
		$this->assertCount( 1, $result['signers'] );
		$this->assertSame( 'FIRMANTE DE PRUEBA', $result['signers'][0]['name'] );
	}

	/**
	 * Un `/Contents` con un solo dígito no produce firmante.
	 *
	 * No llega ni a un byte, así que no hay nada que interpretar. La firma se
	 * sigue anunciando, porque el `/ByteRange` está.
	 */
	public function test_truncated_contents_yields_no_signer() {
		$pdf = "%PDF-1.7\n/ByteRange [0 1 2 3] /Contents <a>\n%%EOF";

		$result = Signature_Detector::detect( $pdf );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( array(), $result['signers'] );
	}

	/**
	 * Los contenedores de Office se reconocen por su entrada de firmas.
	 */
	public function test_office_open_xml_signature_is_detected() {
		$zip = "PK\x03\x04" . str_repeat( "\x00", 26 ) . '_xmlsignatures/sig1.xml';

		$result = Signature_Detector::detect( $zip );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( 'XMLDSig', $result['format'] );
		$this->assertSame( 'Firma de Office Open XML', $result['profile'] );
	}

	/**
	 * Un ZIP sin firmas se reconoce como ZIP y nada más.
	 */
	public function test_plain_zip_is_not_signed() {
		$zip = "PK\x03\x04" . str_repeat( "\x00", 26 ) . 'contenido.txt';

		$result = Signature_Detector::detect( $zip );

		$this->assertFalse( $result['signed'] );
		$this->assertTrue( $result['known'] );
		$this->assertSame( 'ZIP', $result['format'] );
	}

	/**
	 * El firmante de un XAdES sale del certificado que lleva dentro.
	 */
	public function test_xades_signer_comes_from_the_embedded_certificate() {
		$der = (string) file_get_contents( __DIR__ . '/fixtures/signed.csig' );
		$xml = '<?xml version="1.0"?><doc><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">'
			. '<ds:SignatureValue>QQ==</ds:SignatureValue>'
			. '<ds:KeyInfo><ds:X509Data><ds:X509Certificate>'
			. base64_encode( self::first_certificate( $der ) )
			. '</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></doc>';

		$result = Signature_Detector::detect( $xml );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( 'FIRMANTE DE PRUEBA', $result['signers'][0]['name'] );
	}

	/**
	 * Un XAdES con un certificado ilegible no revienta.
	 */
	public function test_xades_with_a_broken_certificate_still_reports_the_signature() {
		$xml = '<?xml version="1.0"?><doc><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">'
			. '<ds:SignatureValue>QQ==</ds:SignatureValue>'
			. '<ds:X509Certificate>no-es-base64-valido!!</ds:X509Certificate>'
			. '</ds:Signature></doc>';

		$result = Signature_Detector::detect( $xml );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( array(), $result['signers'] );
	}

	/**
	 * Una cadena hexadecimal impar no impide leer el resto.
	 */
	public function test_odd_hex_content_does_not_break_detection() {
		$pdf = "%PDF-1.7\n/ByteRange [0 1 2 3] /Contents <abc>\n%%EOF";

		$result = Signature_Detector::detect( $pdf );

		$this->assertTrue( $result['signed'] );
		$this->assertSame( array(), $result['signers'] );
	}

	/**
	 * El almacén no entrega nada si otra petición se lleva el dato antes.
	 *
	 * Es lo que garantiza el consumo único cuando dos peticiones coinciden:
	 * gana quien consigue borrar, no quien consigue leer. Aquí la lectura sale
	 * bien y el borrado ya no encuentra nada, que es exactamente el caso de la
	 * petición que pierde la carrera.
	 */
	public function test_store_returns_nothing_when_the_payload_vanishes() {
		$store = new Transient_Store( 'sesion' );
		$store->put( 'documento', 'contenido', 60 );

		$GLOBALS['wp_autofirma_test_transients_stolen'] = true;

		$this->assertNull( $store->consume( 'documento' ) );
	}

	/**
	 * WordPress ya retira lo caducado, así que no hay nada que purgar.
	 */
	public function test_store_delegates_expiry_to_wordpress() {
		$this->assertSame( 0, ( new Transient_Store( 'sesion' ) )->purgeExpired() );
	}

	/**
	 * Extrae el primer certificado X.509 de una estructura DER.
	 *
	 * @param string $der Estructura binaria.
	 * @return string
	 */
	private static function first_certificate( $der ) {
		$offset = 0;

		while ( true ) {
			$position = strpos( $der, "\x30\x82", $offset );

			if ( false === $position ) {
				return '';
			}

			$offset = $position + 2;
			$size   = ( ord( $der[ $position + 2 ] ) << 8 ) + ord( $der[ $position + 3 ] );
			$slice  = substr( $der, $position, $size + 4 );
			$pem    = "-----BEGIN CERTIFICATE-----\n"
				. chunk_split( base64_encode( $slice ), 64, "\n" )
				. "-----END CERTIFICATE-----\n";

            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Se prueban muchas secuencias a propósito y OpenSSL avisa por cada una que no sea un certificado.
			$parsed = @openssl_x509_parse( $pem );

			if ( is_array( $parsed ) && ! empty( $parsed['subject']['CN'] )
				&& 'FIRMANTE DE PRUEBA' === $parsed['subject']['CN'] ) {
				return $slice;
			}
		}
	}
}

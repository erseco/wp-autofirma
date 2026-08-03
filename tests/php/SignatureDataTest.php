<?php
/**
 * Pruebas de datos firmados.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma\Tests;

use Erseco\WPAutoFirma\Signature_Data;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba las transformaciones puras.
 */
final class SignatureDataTest extends TestCase {

	/**
	 * Comprueba la decodificación estricta.
	 *
	 * @return void
	 */
	public function test_decodes_valid_base64() {
		self::assertSame( 'document', Signature_Data::decode( 'ZG9jdW1lbnQ=' ) );
	}

	/**
	 * Comprueba el rechazo de datos inválidos.
	 *
	 * @return void
	 */
	public function test_rejects_invalid_base64() {
		$this->expectException( InvalidArgumentException::class );
		Signature_Data::decode( '%%%not-base64%%%' );
	}

	/**
	 * Comprueba una huella SHA-256 conocida.
	 *
	 * @return void
	 */
	public function test_calculates_sha256() {
		self::assertSame(
			'43cc23fa52b87b4cc1d02b5b114154151d6adddb17c9fddc06b027fa99e24008',
			Signature_Data::hash( 'document' )
		);
	}

	/**
	 * Comprueba la generación del nombre firmado.
	 *
	 * @return void
	 */
	public function test_creates_signed_filename() {
		self::assertSame(
			'resolucion-firmado.pdf',
			Signature_Data::signed_filename( 'resolucion.PDF' )
		);
		self::assertSame(
			'documento-firmado',
			Signature_Data::signed_filename( 'documento' )
		);
		self::assertSame(
			'resolucion-firmado.pdf',
			Signature_Data::signed_filename( 'resolucion-firmado.pdf' )
		);
	}
}

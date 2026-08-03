<?php
/**
 * Pruebas de integración del índice de firmas.
 *
 * @package WPAutoFirma
 */

use Erseco\WPAutoFirma\Signature_Detector;
use Erseco\WPAutoFirma\Signature_Index;

/**
 * Comprueba cuándo se examina un fichero y cuándo basta con la caché.
 */
class SignatureIndexTest extends WP_UnitTestCase {

	/**
	 * Índice bajo prueba.
	 *
	 * @var Signature_Index
	 */
	private $index;

	/**
	 * Construye el índice.
	 */
	public function set_up() {
		parent::set_up();

		$this->index = new Signature_Index();
	}

	/**
	 * Sube un fichero de prueba a la biblioteca.
	 *
	 * @param string $name Nombre del fichero.
	 * @return int
	 */
	private function upload( $name ) {
		return self::factory()->attachment->create_upload_object(
			dirname( __DIR__ ) . '/php/fixtures/' . $name
		);
	}

	/**
	 * Subir un documento firmado lo marca sin que nadie lo pida.
	 */
	public function test_upload_marks_a_signed_document() {
		$attachment_id = $this->upload( 'signed.pdf' );

		$this->assertSame( '1', get_post_meta( $attachment_id, Signature_Index::META_FLAG, true ) );
	}

	/**
	 * Y uno sin firma queda marcado como tal.
	 */
	public function test_upload_marks_an_unsigned_document() {
		$attachment_id = $this->upload( 'unsigned.pdf' );

		$this->assertSame( '0', get_post_meta( $attachment_id, Signature_Index::META_FLAG, true ) );
	}

	/**
	 * El detalle guardado describe la firma.
	 */
	public function test_detail_describes_the_signature() {
		$status = $this->index->status( $this->upload( 'signed.pdf' ) );

		$this->assertTrue( $status['signed'] );
		$this->assertSame( 'PAdES', $status['format'] );
		$this->assertSame( 'FIRMANTE DE PRUEBA', $status['signers'][0]['name'] );
	}

	/**
	 * Un adjunto anterior al plugin se examina la primera vez que se mira.
	 */
	public function test_attachment_without_cache_is_scanned_on_demand() {
		$attachment_id = $this->upload( 'signed.pdf' );

		delete_post_meta( $attachment_id, Signature_Index::META_DETAIL );
		delete_post_meta( $attachment_id, Signature_Index::META_FLAG );

		$status = $this->index->status( $attachment_id );

		$this->assertTrue( $status['signed'] );
		$this->assertSame(
			'1',
			get_post_meta( $attachment_id, Signature_Index::META_FLAG, true ),
			'La consulta debe dejar la caché escrita para las siguientes.'
		);
	}

	/**
	 * Un resultado de una versión antigua del detector se recalcula solo.
	 */
	public function test_stale_result_is_recomputed() {
		$attachment_id = $this->upload( 'signed.pdf' );

		update_post_meta(
			$attachment_id,
			Signature_Index::META_DETAIL,
			array(
				'signed'  => false,
				'known'   => true,
				'version' => Signature_Detector::VERSION - 1,
			)
		);

		$this->assertTrue( $this->index->status( $attachment_id )['signed'] );
	}

	/**
	 * La caché se sirve tal cual mientras la versión coincida.
	 *
	 * Se falsea el contenido a propósito: si la respuesta lo respeta, es que no
	 * ha vuelto a leer el fichero.
	 */
	public function test_cache_is_served_without_reading_the_file_again() {
		$attachment_id = $this->upload( 'unsigned.pdf' );

		update_post_meta(
			$attachment_id,
			Signature_Index::META_DETAIL,
			array(
				'signed'     => true,
				'known'      => true,
				'format'     => 'INVENTADO',
				'profile'    => '',
				'signatures' => 1,
				'signers'    => array(),
				'signed_at'  => '',
				'version'    => Signature_Detector::VERSION,
			)
		);

		$this->assertSame( 'INVENTADO', $this->index->status( $attachment_id )['format'] );
	}

	/**
	 * Lo que nunca lleva firma no se lee del disco.
	 */
	public function test_images_are_not_scanned() {
		$attachment_id = self::factory()->attachment->create(
			array( 'post_mime_type' => 'image/jpeg' )
		);

		$status = $this->index->scan( $attachment_id );

		$this->assertFalse( $status['signed'] );
		$this->assertFalse( $status['known'] );
	}

	/**
	 * Un identificador que no señala a nada se responde como desconocido.
	 *
	 * Llega desde los shortcodes, donde el número lo escribe quien redacta la
	 * página: un cero o un identificador inventado no puede acabar leyendo del
	 * disco ni escribiendo meta de ningún sitio.
	 */
	public function test_an_invalid_identifier_is_unknown() {
		$this->assertSame( Signature_Detector::UNKNOWN, $this->index->status( 0 ) );
		$this->assertSame( Signature_Detector::UNKNOWN, $this->index->status( -5 ) );
	}

	/**
	 * La marca puede consultarse con `meta_query`.
	 */
	public function test_signed_documents_can_be_queried() {
		$signed = $this->upload( 'signed.pdf' );
		$this->upload( 'unsigned.pdf' );

		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Signature_Index::META_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Es justo lo que se está comprobando: que la marca sea consultable.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Ídem.
			)
		);

		$this->assertSame( array( $signed ), $found );
	}
}

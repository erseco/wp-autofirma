<?php
/**
 * Pruebas del almacén del servidor intermedio.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma\Tests;

use Erseco\AutoFirma\IntermediateServer\IntermediateServer;
use Erseco\AutoFirma\IntermediateServer\Protocol\Request;
use Erseco\WPAutoFirma\Transient_Store;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba el contrato que el protocolo exige al almacenamiento.
 */
final class TransientStoreTest extends TestCase {

    /**
     * Vacía los transients simulados entre pruebas.
     */
    protected function setUp(): void {
        $GLOBALS['wp_autofirma_test_transients'] = array();
    }

    /**
     * Lo guardado se recupera igual.
     */
    public function test_stores_and_returns_the_payload() {
        $store = new Transient_Store( 'sesion' );
        $store->put( 'documento', 'contenido cifrado', 60 );

        $this->assertSame( 'contenido cifrado', $store->consume( 'documento' ) );
    }

    /**
     * Un dato se entrega una sola vez.
     *
     * Es el requisito central del protocolo: si un resultado pudiera
     * recuperarse dos veces, cualquiera que repitiese la petición se llevaría
     * una copia de la firma.
     */
    public function test_consumes_only_once() {
        $store = new Transient_Store( 'sesion' );
        $store->put( 'documento', 'contenido cifrado', 60 );

        $this->assertSame( 'contenido cifrado', $store->consume( 'documento' ) );
        $this->assertNull( $store->consume( 'documento' ) );
    }

    /**
     * Lo que no existe devuelve nulo en lugar de fallar.
     */
    public function test_missing_identifier_returns_null() {
        $store = new Transient_Store( 'sesion' );

        $this->assertNull( $store->consume( 'no-existe' ) );
    }

    /**
     * Dos sesiones no comparten datos aunque coincida el identificador.
     *
     * AutoScript elige el identificador, así que dos personas firmando a la vez
     * pueden usar el mismo. Sin separación, una recogería el documento de la
     * otra.
     */
    public function test_sessions_are_isolated() {
        $primera = new Transient_Store( 'sesion-a' );
        $segunda = new Transient_Store( 'sesion-b' );

        $primera->put( 'mismo-id', 'de la primera', 60 );
        $segunda->put( 'mismo-id', 'de la segunda', 60 );

        $this->assertSame( 'de la primera', $primera->consume( 'mismo-id' ) );
        $this->assertSame( 'de la segunda', $segunda->consume( 'mismo-id' ) );
    }

    /**
     * Un identificador largo no desborda el nombre de la opción.
     *
     * WordPress limita `option_name` a 191 caracteres y AutoScript admite
     * identificadores de hasta 128, que sumados al prefijo de los transients se
     * acercarían al límite.
     */
    public function test_long_identifiers_produce_short_keys() {
        $store = new Transient_Store( 'sesion' );
        $store->put( str_repeat( 'a', 128 ), 'contenido', 60 );

        foreach ( array_keys( $GLOBALS['wp_autofirma_test_transients'] ) as $key ) {
            $this->assertLessThanOrEqual( 60, strlen( $key ) );
        }
    }

    /**
     * El almacén cumple el protocolo completo de principio a fin.
     *
     * Recorre el camino real: sondeo de disponibilidad, depósito del dato y
     * recogida única, tal como los encadenan AutoScript y AutoFirma.
     */
    public function test_completes_the_protocol_round_trip() {
        $server = new IntermediateServer( new Transient_Store( 'sesion' ) );

        $check = $server->handle( new Request( 'GET', array( 'op' => 'check' ) ) );
        $this->assertSame( 'OK', $check->body() );

        $put = $server->handle(
            new Request(
                'POST',
                array(
                    'op'  => 'put',
                    'v'   => '1_0',
                    'id'  => 'documento',
                    'dat' => 'contenido cifrado',
                )
            )
        );
        $this->assertSame( 'OK', $put->body() );

        $parameters = array(
            'op' => 'get',
            'v'  => '1_0',
            'id' => 'documento',
        );

        $get = $server->handle( new Request( 'POST', $parameters ) );
        $this->assertSame( 'contenido cifrado', $get->body() );

        $again = $server->handle( new Request( 'POST', $parameters ) );
        $this->assertStringStartsWith( 'ERR-', $again->body() );
    }
}

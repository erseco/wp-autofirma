<?php
/**
 * Pruebas del cargador de la librería incluida en el paquete.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma\Tests;

use Composer\Autoload\ClassLoader;
use Erseco\AutoFirma\IntermediateServer\IntermediateServer;
use Erseco\WPAutoFirma\Bundled_Autoloader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Comprueba que la librería se carga sin Composer.
 *
 * Es lo que ocurre en la instalación real: el ZIP distribuible no lleva
 * `vendor/`, sino una copia de la librería bajo `includes/vendor/`. Si este
 * cargador no la encontrara, el servidor intermedio no existiría para nadie que
 * instalase el plugin desde el directorio de WordPress.
 */
final class BundledAutoloaderTest extends TestCase {

	/**
	 * Deja definida la ruta del plugin, que es de donde cuelga la librería.
	 */
	protected function setUp(): void {
		if ( ! defined( 'WP_AUTOFIRMA_PATH' ) ) {
			define( 'WP_AUTOFIRMA_PATH', dirname( __DIR__, 2 ) . '/' );
		}

		require_once dirname( __DIR__, 2 ) . '/includes/class-bundled-autoloader.php';
	}

	/**
	 * Con Composer delante, el cargador se aparta.
	 *
	 * Registrarse igualmente no rompería nada, pero dejaría dos cargadores
	 * sirviendo las mismas clases desde ficheros distintos. Aquí la librería ya
	 * está declarada, así que no hay nada que cargar.
	 */
	public function test_does_not_register_when_composer_already_provides_the_library() {
		$this->assertTrue(
			class_exists( IntermediateServer::class ),
			'Esta prueba parte de que Composer ha traído la librería.'
		);

		$before = count( (array) spl_autoload_functions() );

		Bundled_Autoloader::register();

		$this->assertSame( $before, count( (array) spl_autoload_functions() ) );
	}

	/**
	 * Una clase de otro espacio de nombres no es asunto suyo.
	 */
	public function test_classes_from_other_namespaces_are_left_alone() {
		Bundled_Autoloader::autoload( 'Otro\\Espacio\\Clock\\SystemClock' );

		$this->assertFalse( class_exists( 'Otro\\Espacio\\Clock\\SystemClock', false ) );
	}

	/**
	 * Una clase del espacio propio que no existe se ignora sin fallar.
	 *
	 * Un cargador que reventara aquí tumbaría cualquier `class_exists()` del
	 * sitio, viniera de donde viniera.
	 */
	public function test_a_missing_bundled_class_does_not_fail() {
		Bundled_Autoloader::autoload( 'Erseco\\AutoFirma\\IntermediateServer\\No\\Existe' );

		$this->assertFalse( class_exists( 'Erseco\\AutoFirma\\IntermediateServer\\No\\Existe', false ) );
	}

	/**
	 * Sin Composer, la librería se sirve desde la copia del paquete.
	 *
	 * Se ejecuta en un proceso aparte y se retiran los cargadores de Composer
	 * para reproducir la instalación real, donde `vendor/` no existe. Es la
	 * única forma de comprobar lo que de verdad recibe quien instala el plugin
	 * desde el directorio de WordPress.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_serves_the_library_from_the_package_without_composer() {
		$this->assertFalse(
			class_exists( IntermediateServer::class, false ),
			'El arranque de estas pruebas no declara la librería, solo la deja cargable.'
		);

		$composer = array();

		foreach ( (array) spl_autoload_functions() as $loader ) {
			if ( is_array( $loader ) && $loader[0] instanceof ClassLoader ) {
				$composer[] = $loader;
				spl_autoload_unregister( $loader );
			}
		}

		// Sin Composer tampoco quedaría quien declarase las clases que PHPUnit
		// carga al comprobar algo, así que aquí no puede afirmarse nada: se
		// recoge el resultado y se devuelve el cargador antes de mirarlo.
		Bundled_Autoloader::register();
		$loaded = class_exists( IntermediateServer::class );
		$file   = $loaded
			? str_replace( '\\', '/', (string) ( new ReflectionClass( IntermediateServer::class ) )->getFileName() )
			: '';

		foreach ( $composer as $loader ) {
			spl_autoload_register( $loader );
		}

		$this->assertCount( 1, $composer, 'Debe haberse retirado el cargador de Composer.' );
		$this->assertTrue( $loaded, 'El cargador incluido debe declarar la librería.' );
		$this->assertStringContainsString( 'includes/vendor/autofirma-intermediate-server/src/', $file );
	}
}

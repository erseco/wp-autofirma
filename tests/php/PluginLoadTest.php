<?php
/**
 * Pruebas de carga del plugin.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Comprueba que el plugin arranca aunque falten las dependencias de Composer.
 *
 * Quien instala el paquete distribuible las recibe dentro. Quien descarga el
 * repositorio —el ZIP de GitHub, un despliegue desde git, WordPress
 * Playground— no, porque `vendor/` no está versionado. En ese caso el plugin
 * debe arrancar igual y limitarse a no ofrecer el servidor intermedio: una
 * dependencia ausente no puede tumbar la biblioteca de medios entera.
 */
final class PluginLoadTest extends TestCase {

	/**
	 * Directorio temporal con la copia sin dependencias.
	 *
	 * @var string
	 */
	private $directory = '';

	/**
	 * Copia el plugin sin `vendor/`.
	 */
	protected function setUp(): void {
		$root            = dirname( __DIR__, 2 );
		$this->directory = sys_get_temp_dir() . '/wpaf-' . uniqid();

		mkdir( $this->directory . '/includes', 0777, true );
		mkdir( $this->directory . '/admin', 0777, true );
		mkdir( $this->directory . '/tests/php/Support', 0777, true );

		copy( $root . '/wp-autofirma.php', $this->directory . '/wp-autofirma.php' );
		copy(
			$root . '/tests/php/Support/plugin-probe.php',
			$this->directory . '/tests/php/Support/plugin-probe.php'
		);

		foreach ( array( 'includes', 'admin' ) as $folder ) {
			foreach ( (array) glob( $root . '/' . $folder . '/*.php' ) as $file ) {
				copy( $file, $this->directory . '/' . $folder . '/' . basename( $file ) );
			}
		}
	}

	/**
	 * Retira la copia.
	 */
	protected function tearDown(): void {
		if ( '' === $this->directory || ! is_dir( $this->directory ) ) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}

		rmdir( $this->directory );
	}

	/**
	 * El plugin carga sin `vendor/` y desactiva el servidor intermedio.
	 */
	public function test_loads_without_composer_dependencies() {
		$this->assertDirectoryDoesNotExist( $this->directory . '/vendor' );

		$output = array();
		$status = 0;

		exec(
			escapeshellarg( PHP_BINARY ) . ' '
				. escapeshellarg( $this->directory . '/tests/php/Support/plugin-probe.php' )
				. ' 2>&1',
			$output,
			$status
		);

		$printed = implode( "\n", $output );

		$this->assertSame( 0, $status, 'El plugin no debe fallar sin dependencias: ' . $printed );
		$this->assertStringNotContainsString( 'Fatal error', $printed );
		$this->assertStringContainsString( 'no disponible', $printed );
	}
}

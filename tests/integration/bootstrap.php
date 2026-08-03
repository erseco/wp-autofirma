<?php
/**
 * Arranque de las pruebas de integración, con WordPress cargado.
 *
 * Se ejecutan dentro del contenedor `tests-cli` de wp-env, que trae la suite de
 * pruebas oficial de WordPress y expone su ruta en `WP_TESTS_DIR`. A diferencia
 * de las pruebas unitarias, aquí hay base de datos, hooks, usuarios y API REST
 * de verdad: es la única forma de ejercitar las clases que no pueden probarse
 * sin WordPress.
 *
 * @package WPAutoFirma
 */

$wp_autofirma_root = dirname( __DIR__, 2 );
$wp_autofirma_dir  = getenv( 'WP_TESTS_DIR' );

if ( ! is_string( $wp_autofirma_dir ) || '' === $wp_autofirma_dir ) {
	$wp_autofirma_dir = '/wordpress-phpunit';
}

if ( ! is_readable( $wp_autofirma_dir . '/includes/functions.php' ) ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Mensaje por consola de un arranque de pruebas, no salida web.
	echo 'No se encuentra la suite de pruebas de WordPress en ' . $wp_autofirma_dir . ".\n"
		. "Estas pruebas se ejecutan dentro de wp-env: usa `make test-integration`.\n";
	exit( 1 );
}

// La suite de WordPress espera los polyfills de PHPUnit, que igualan la API
// entre versiones del framework.
require_once $wp_autofirma_root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $wp_autofirma_dir . '/includes/functions.php';
require_once __DIR__ . '/../php/Support/unreadable-stream.php';

/**
 * Carga el plugin antes de que WordPress termine de arrancar.
 *
 * @return void
 */
function wp_autofirma_load_plugin() {
	require dirname( __DIR__, 2 ) . '/wp-autofirma.php';
}

tests_add_filter( 'muplugins_loaded', 'wp_autofirma_load_plugin' );

require $wp_autofirma_dir . '/includes/bootstrap.php';

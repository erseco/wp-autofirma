<?php
/**
 * Carga el plugin con lo mínimo de WordPress para ver si sobrevive.
 *
 * Se ejecuta en un proceso aparte sobre una copia del plugin sin `vendor/`,
 * que es como lo instala quien descarga el repositorio en lugar del paquete.
 *
 * @package WPAutoFirma
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MB_IN_BYTES', 1048576 );

/**
 * Devuelve el directorio de un fichero de plugin.
 *
 * @param string $file Fichero.
 * @return string
 */
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

/**
 * Devuelve la URL de un fichero de plugin.
 *
 * @return string
 */
function plugin_dir_url() {
	return 'https://example.org/wp-content/plugins/wp-autofirma/';
}

/**
 * Registra una acción.
 *
 * @return bool
 */
function add_action() {
	return true;
}

/**
 * Registra un filtro.
 *
 * @return bool
 */
function add_filter() {
	return true;
}

/**
 * Registra un shortcode.
 *
 * @return bool
 */
function add_shortcode() {
	return true;
}

/**
 * Aplica un filtro devolviendo el valor sin tocar.
 *
 * @param string $hook  Nombre del filtro.
 * @param mixed  $value Valor.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	return $value;
}

/**
 * Devuelve una URL de la API REST.
 *
 * @param string $path Ruta.
 * @return string
 */
function rest_url( $path = '' ) {
	return 'https://example.org/wp-json/' . $path;
}

/**
 * Quita la barra final.
 *
 * @param string $value Cadena.
 * @return string
 */
function untrailingslashit( $value ) {
	return rtrim( $value, '/' );
}

// El probe vive en `tests/php/Support/`, así que la raíz del plugin está tres
// niveles por encima.
require dirname( __DIR__, 3 ) . '/wp-autofirma.php';

echo Erseco\WPAutoFirma\Intermediate_Controller::is_available() ? "disponible\n" : "no disponible\n";

<?php
/**
 * Transients de WordPress simulados para las pruebas.
 *
 * Las pruebas corren sin WordPress. Estas funciones reproducen lo que el
 * almacén necesita de los transients, incluido lo esencial: `delete_transient()`
 * devuelve `true` una sola vez, que es lo que garantiza el consumo único.
 *
 * @package WPAutoFirma
 */

$GLOBALS['wp_autofirma_test_transients'] = array();

/**
 * Cuando vale `true`, cada lectura se lleva el dato por detrás.
 *
 * Reproduce dos peticiones simultáneas: la lectura devuelve el contenido y, para
 * cuando el almacén intenta borrarlo, otra ya se lo ha llevado. Es la única
 * forma de provocar aquí la carrera que decide el consumo único.
 *
 * @var bool
 */
$GLOBALS['wp_autofirma_test_transients_stolen'] = false;

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Guarda un transient.
	 *
	 * @param string $key        Clave.
	 * @param mixed  $value      Valor.
	 * @param int    $expiration Segundos de vida.
	 * @return bool
	 */
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['wp_autofirma_test_transients'][ $key ] = array(
			'value'   => $value,
			'expires' => $expiration > 0 ? time() + $expiration : 0,
		);

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Recupera un transient.
	 *
	 * @param string $key Clave.
	 * @return mixed Valor, o false si no existe o ha caducado.
	 */
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['wp_autofirma_test_transients'][ $key ] ) ) {
			return false;
		}

		$entry = $GLOBALS['wp_autofirma_test_transients'][ $key ];

		if ( 0 !== $entry['expires'] && $entry['expires'] < time() ) {
			unset( $GLOBALS['wp_autofirma_test_transients'][ $key ] );

			return false;
		}

		if ( ! empty( $GLOBALS['wp_autofirma_test_transients_stolen'] ) ) {
			unset( $GLOBALS['wp_autofirma_test_transients'][ $key ] );
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Borra un transient.
	 *
	 * @param string $key Clave.
	 * @return bool Si había algo que borrar.
	 */
	function delete_transient( $key ) {
		if ( ! isset( $GLOBALS['wp_autofirma_test_transients'][ $key ] ) ) {
			return false;
		}

		unset( $GLOBALS['wp_autofirma_test_transients'][ $key ] );

		return true;
	}
}

<?php
/**
 * Cargador de la librería del servidor intermedio incluida en el paquete.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

/**
 * Carga las clases de `erseco/autofirma-intermediate-server` sin Composer.
 *
 * Quien instala un plugin de WordPress no ejecuta Composer, así que la librería
 * viaja dentro del paquete bajo `includes/vendor/`, copiada ahí por el script
 * `copy-runtime-dependencies` de composer.json. En el repositorio, en cambio,
 * `composer install` deja además el autoload de Composer en `vendor/`, que se
 * carga antes que esta clase y ya declara todo: por eso `register()` no hace
 * nada si las clases ya están.
 *
 * Es el mismo patrón que usan `wp-decker` y `wp-documentate` para sus
 * dependencias de ejecución.
 */
final class Bundled_Autoloader {

    /**
     * Prefijo de espacio de nombres que sirve la librería.
     *
     * @var string
     */
    const NAMESPACE_PREFIX = 'Erseco\\AutoFirma\\IntermediateServer\\';

    /**
     * Registra el cargador si Composer no ha traído ya la librería.
     *
     * @return void
     */
    public static function register() {
        if ( class_exists( self::NAMESPACE_PREFIX . 'IntermediateServer' ) ) {
            return;
        }

        if ( ! is_dir( self::base_dir() ) ) {
            return;
        }

        spl_autoload_register( array( self::class, 'autoload' ) );
    }

    /**
     * Carga una clase de la librería incluida.
     *
     * @param string $class_name Nombre completo de la clase.
     * @return void
     */
    public static function autoload( $class_name ) {
        if ( 0 !== strpos( $class_name, self::NAMESPACE_PREFIX ) ) {
            return;
        }

        $relative = substr( $class_name, strlen( self::NAMESPACE_PREFIX ) );
        $path     = self::base_dir() . str_replace( '\\', '/', $relative ) . '.php';

        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }

    /**
     * Devuelve el directorio de la librería incluida.
     *
     * @return string Ruta absoluta terminada en barra.
     */
    private static function base_dir() {
        return WP_AUTOFIRMA_PATH . 'includes/vendor/autofirma-intermediate-server/src/';
    }
}

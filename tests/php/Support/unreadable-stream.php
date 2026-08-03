<?php
/**
 * Envoltorio de flujo que se declara legible y falla al leerse.
 *
 * Reproduce un fichero que existe, declara tamaño y aun así no se deja leer: un
 * disco con errores, un montaje de red caído o un permiso que cambia entre la
 * comprobación y la lectura. Se hace así y no con un fichero de verdad porque
 * cada sistema responde distinto —un directorio devuelve `false` en unos y
 * cadena vacía en otros— y las pruebas corren en varios.
 *
 * @package WPAutoFirma
 */

/**
 * Sirve rutas que pasan las comprobaciones previas y no se pueden abrir.
 */
class WP_AutoFirma_Unreadable_Stream {

    /**
     * Protocolo con el que se registra.
     *
     * @var string
     */
    const PROTOCOL = 'wpaf-ilegible';

    /**
     * Contexto del flujo. Lo asigna PHP y ha de ser público.
     *
     * @var resource|null
     */
    public $context;

    /**
     * Registra el envoltorio.
     *
     * @return void
     */
    public static function register() {
        if ( ! in_array( self::PROTOCOL, stream_get_wrappers(), true ) ) {
            stream_wrapper_register( self::PROTOCOL, self::class );
        }
    }

    /**
     * Retira el envoltorio.
     *
     * @return void
     */
    public static function unregister() {
        if ( in_array( self::PROTOCOL, stream_get_wrappers(), true ) ) {
            stream_wrapper_unregister( self::PROTOCOL );
        }
    }

    /**
     * Devuelve una ruta servida por el envoltorio.
     *
     * @param string $name Nombre del fichero.
     * @return string
     */
    public static function path( $name = 'documento.pdf' ) {
        return self::PROTOCOL . '://' . $name;
    }

    /**
     * Niega la apertura, que es justo lo que se quiere provocar.
     *
     * @return bool
     */
    public function stream_open() {
        return false;
    }

    /**
     * Declara un fichero normal, legible y con tamaño.
     *
     * @return array<string, int>
     */
    public function url_stat() {
        return array(
            'mode' => 0100666,
            'size' => 4096,
        );
    }
}

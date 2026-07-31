<?php
/**
 * Utilidades puras para datos firmados.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use InvalidArgumentException;

/**
 * Normaliza y comprueba datos Base64.
 */
final class Signature_Data {

    /**
     * Decodifica Base64 en modo estricto.
     *
     * @param string $encoded Datos codificados.
     * @return string
     *
     * @throws InvalidArgumentException Cuando los datos no son Base64 válido.
     */
    public static function decode( $encoded ) {
        $decoded = base64_decode( $encoded, true );

        if ( false === $decoded ) {
            throw new InvalidArgumentException( 'Los datos firmados no son Base64 válido.' );
        }

        return $decoded;
    }

    /**
     * Calcula la huella SHA-256 de datos binarios.
     *
     * @param string $data Datos binarios.
     * @return string
     */
    public static function hash( $data ) {
        return hash( 'sha256', $data );
    }

    /**
     * Genera un nombre seguro para el documento firmado.
     *
     * @param string $filename Nombre original.
     * @return string
     */
    public static function signed_filename( $filename ) {
        $extension = pathinfo( $filename, PATHINFO_EXTENSION );
        $basename  = pathinfo( $filename, PATHINFO_FILENAME );
        $suffix    = '' !== $extension ? '.' . strtolower( $extension ) : '';

        if ( '-firmado' === substr( $basename, -8 ) ) {
            return $basename . $suffix;
        }

        return $basename . '-firmado' . $suffix;
    }
}

<?php
/**
 * Detección estructural de firmas digitales en ficheros.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

/**
 * Averigua si un fichero contiene una firma digital y describe lo que declara.
 *
 * Esto NO valida nada. Comprueba que existe una estructura de firma y lee lo
 * que esa estructura dice de sí misma. No verifica el resumen del documento, no
 * construye la cadena de confianza, no consulta revocación y no comprueba
 * sellos de tiempo. Un documento manipulado después de firmarlo sigue teniendo
 * firma, y aquí aparecerá como firmado. Para validar de verdad hace falta un
 * servicio especializado; en España, la plataforma VALIDe o @firma.
 */
final class Signature_Detector {

	/**
	 * Versión del algoritmo de detección.
	 *
	 * Se guarda junto al resultado cacheado: al subirla, los resultados
	 * antiguos se recalculan solos en lugar de quedarse obsoletos.
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * OID de PKCS#7 signedData (1.2.840.113549.1.7.2) en su forma DER.
	 *
	 * @var string
	 */
	const OID_SIGNED_DATA = "\x2a\x86\x48\x86\xf7\x0d\x01\x07\x02";

	/**
	 * Resultado de un fichero que no se ha podido examinar.
	 *
	 * @var array
	 */
	const UNKNOWN = array(
		'signed'     => false,
		'known'      => false,
		'format'     => '',
		'profile'    => '',
		'signatures' => 0,
		'signers'    => array(),
		'signed_at'  => '',
	);

	/**
	 * Examina un fichero del disco.
	 *
	 * Los ficheros grandes no se cargan enteros: se leen una ventana inicial y
	 * otra final, que es donde vive la estructura de firma en la práctica. Así
	 * el consumo de memoria queda acotado pase lo que pase.
	 *
	 * @param string $path      Ruta absoluta.
	 * @param int    $max_bytes Tamaño máximo que se lee de una vez.
	 * @return array
	 */
	public static function inspect( $path, $max_bytes = 16777216 ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return self::UNKNOWN;
		}

		$size = filesize( $path );

		if ( false === $size || 0 === $size ) {
			return self::UNKNOWN;
		}

		if ( $size <= $max_bytes ) {
			$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lectura local de un fichero de la biblioteca de medios, no una petición remota.
		} else {
			$window = (int) max( 1048576, $max_bytes / 16 );
			$bytes  = (string) file_get_contents( $path, false, null, 0, $window ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Ídem.
			$bytes .= (string) file_get_contents( $path, false, null, $size - $window, $window ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Ídem.
		}

		if ( false === $bytes ) {
			return self::UNKNOWN;
		}

		return self::detect( $bytes );
	}

	/**
	 * Examina un contenido ya cargado en memoria.
	 *
	 * @param string $bytes Contenido binario.
	 * @return array
	 */
	public static function detect( $bytes ) {
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return self::UNKNOWN;
		}

		if ( 0 === strncmp( $bytes, '%PDF-', 5 ) ) {
			return self::detect_pdf( $bytes );
		}

		if ( 0 === strncmp( $bytes, "PK\x03\x04", 4 ) ) {
			return self::detect_zip( $bytes );
		}

		$result = self::detect_xml( $bytes );

		if ( $result['known'] ) {
			return $result;
		}

		return self::detect_cms( $bytes );
	}

	/**
	 * Detecta firmas PAdES dentro de un PDF.
	 *
	 * @param string $bytes Contenido del PDF.
	 * @return array
	 */
	private static function detect_pdf( $bytes ) {
		$result           = self::UNKNOWN;
		$result['known']  = true;
		$result['format'] = 'PDF';

		// Cada firma incorporada declara su propio /ByteRange: los tramos del
		// fichero que cubre. Es el marcador más fiable, porque sin él la firma
		// no puede comprobarse y ningún visor la reconoce.
		$signatures = preg_match_all( '#/ByteRange\s*\[#', $bytes );

		if ( ! $signatures ) {
			return $result;
		}

		$result['signed']     = true;
		$result['format']     = 'PAdES';
		$result['signatures'] = (int) $signatures;

		if ( preg_match_all( '#/SubFilter\s*/([A-Za-z0-9._\#-]+)#', $bytes, $matches ) ) {
			$result['profile'] = self::describe_pdf_profile( array_unique( $matches[1] ) );
		}

		if ( preg_match( '#/M\s*\(\s*D:(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})#', $bytes, $date ) ) {
			$result['signed_at'] = sprintf(
				'%s-%s-%s %s:%s:%s',
				$date[1],
				$date[2],
				$date[3],
				$date[4],
				$date[5],
				$date[6]
			);
		}

		// El PKCS#7 de cada firma viaja como cadena hexadecimal en /Contents.
		if ( preg_match_all( '#/Contents\s*<([0-9A-Fa-f\s]+)>#', $bytes, $blobs ) ) {
			foreach ( $blobs[1] as $hex ) {
				$der = self::hex_to_binary( $hex );

				if ( '' !== $der ) {
					$result['signers'] = array_merge(
						$result['signers'],
						self::signers_from_der( $der )
					);
				}
			}
		}

		$result['signers'] = self::unique_signers( $result['signers'] );

		return $result;
	}

	/**
	 * Traduce los SubFilter de un PDF a una descripción legible.
	 *
	 * @param array $subfilters Valores hallados.
	 * @return string
	 */
	private static function describe_pdf_profile( array $subfilters ) {
		foreach ( $subfilters as $subfilter ) {
			$normalized = strtolower( $subfilter );

			if ( false !== strpos( $normalized, 'etsi.cades' ) ) {
				return 'PAdES (ETSI.CAdES.detached)';
			}

			if ( false !== strpos( $normalized, 'etsi.rfc3161' ) ) {
				return 'Sello de tiempo del documento (ETSI.RFC3161)';
			}

			if ( false !== strpos( $normalized, 'adbe.pkcs7' ) ) {
				return 'PDF clásico (' . $subfilter . ')';
			}
		}

		return '';
	}

	/**
	 * Detecta firmas XMLDSig y XAdES.
	 *
	 * @param string $bytes Contenido del documento.
	 * @return array
	 */
	private static function detect_xml( $bytes ) {
		$result = self::UNKNOWN;

		if ( false === strpos( $bytes, 'http://www.w3.org/2000/09/xmldsig#' ) ) {
			return $result;
		}

		$result['known']  = true;
		$result['signed'] = true;
		$result['format'] = 'XMLDSig';
		// El `[^>/]` descarta las etiquetas de cierre: contarlas duplicaría el
		// número de firmas.
		$result['signatures'] = (int) max( 1, preg_match_all( '#<[^>/]*SignatureValue[\s>]#', $bytes ) );

		if ( false !== strpos( $bytes, 'http://uri.etsi.org/01903' ) ) {
			$result['format']  = 'XAdES';
			$result['profile'] = 'XAdES';
		}

		if ( preg_match( '#<[^>]*SigningTime[^>]*>([^<]+)<#', $bytes, $time ) ) {
			$result['signed_at'] = trim( $time[1] );
		}

		if ( preg_match_all( '#<[^>]*X509Certificate[^>]*>([^<]+)<#', $bytes, $certs ) ) {
			foreach ( $certs[1] as $encoded ) {
				$der = base64_decode( preg_replace( '#\s+#', '', $encoded ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodifica un certificado X.509 del propio documento, no ofusca código.

				if ( is_string( $der ) && '' !== $der ) {
					$signer = self::parse_certificate( $der );

					if ( array() !== $signer ) {
						$result['signers'][] = $signer;
					}
				}
			}
		}

		$result['signers'] = self::unique_signers( $result['signers'] );

		return $result;
	}

	/**
	 * Detecta firmas en contenedores ZIP (ODF y OOXML).
	 *
	 * @param string $bytes Contenido del contenedor.
	 * @return array
	 */
	private static function detect_zip( $bytes ) {
		$result           = self::UNKNOWN;
		$result['known']  = true;
		$result['format'] = 'ZIP';

		// Los nombres de las entradas viajan sin comprimir en las cabeceras, de
		// modo que basta buscarlos para saber si el contenedor lleva firmas.
		if ( false !== strpos( $bytes, 'META-INF/documentsignatures.xml' ) ) {
			$result['signed']     = true;
			$result['format']     = 'XAdES';
			$result['profile']    = 'Firma de OpenDocument';
			$result['signatures'] = 1;

			return $result;
		}

		if ( false !== strpos( $bytes, '_xmlsignatures/sig' ) ) {
			$result['signed']     = true;
			$result['format']     = 'XMLDSig';
			$result['profile']    = 'Firma de Office Open XML';
			$result['signatures'] = 1;
		}

		return $result;
	}

	/**
	 * Detecta firmas CAdES y PKCS#7 sueltas.
	 *
	 * Cubre los `.csig` que genera AutoFirma y los `.p7s` habituales, tanto en
	 * DER como envueltos en Base64.
	 *
	 * @param string $bytes Contenido del fichero.
	 * @return array
	 */
	private static function detect_cms( $bytes ) {
		$result = self::UNKNOWN;
		$der    = $bytes;

		if ( "\x30" !== substr( $bytes, 0, 1 ) ) {
			$armored = $bytes;

			// Las firmas en PEM llevan cabecera y pie. Hay que quedarse con el
			// cuerpo: si se limpiara la cadena entera, las letras de la
			// cabecera se colarían en el Base64 y lo corromperían.
			if ( preg_match( '#-----BEGIN[^-]*-----(.+?)-----END#s', $bytes, $body ) ) {
				$armored = $body[1];
			}

			$candidate = base64_decode( preg_replace( '#[^A-Za-z0-9+/=]#', '', substr( $armored, 0, 262144 ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Desenvuelve una firma CAdES en Base64, no ofusca código.

			if ( ! is_string( $candidate ) || "\x30" !== substr( $candidate, 0, 1 ) ) {
				return $result;
			}

			$der = $candidate;
		}

		if ( false === strpos( $der, self::OID_SIGNED_DATA ) ) {
			return $result;
		}

		$result['known']      = true;
		$result['signed']     = true;
		$result['format']     = 'CAdES';
		$result['signatures'] = 1;
		$result['signers']    = self::signers_from_der( $der );

		return $result;
	}

	/**
	 * Extrae los certificados de firmante de una estructura DER.
	 *
	 * Recorre la estructura buscando secuencias que OpenSSL acepte como
	 * certificado X.509 y descarta las de las autoridades: dentro de un PKCS#7
	 * viaja la cadena entera, y quien firma es la hoja.
	 *
	 * @param string $der Estructura binaria.
	 * @return array
	 */
	private static function signers_from_der( $der ) {
		$signers = array();
		$offset  = 0;
		$length  = strlen( $der );

		while ( $offset < $length ) {
			$position = strpos( $der, "\x30\x82", $offset );

			if ( false === $position || $position + 4 > $length ) {
				break;
			}

			$offset = $position + 2;
			$size   = ( ord( $der[ $position + 2 ] ) << 8 ) + ord( $der[ $position + 3 ] );
			$signer = self::parse_certificate( substr( $der, $position, $size + 4 ) );

			if ( array() !== $signer ) {
				$signers[] = $signer;
			}
		}

		return self::unique_signers( $signers );
	}

	/**
	 * Interpreta un certificado X.509 en DER.
	 *
	 * @param string $der Certificado binario.
	 * @return array Datos del firmante, o vacío si no es un certificado de hoja.
	 */
	private static function parse_certificate( $der ) {
		if ( ! function_exists( 'openssl_x509_parse' ) ) {
			return array();
		}

		$pem = "-----BEGIN CERTIFICATE-----\n"
			. chunk_split( base64_encode( $der ), 64, "\n" ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Reempaqueta un certificado como PEM para OpenSSL.
			. "-----END CERTIFICATE-----\n";

		// OpenSSL avisa por cada secuencia que no sea un certificado, y aquí se
		// le ofrecen muchas a propósito: el aviso es el resultado esperado.
		$parsed = @openssl_x509_parse( $pem ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Ver comentario.

		if ( ! is_array( $parsed ) || empty( $parsed['subject'] ) ) {
			return array();
		}

		$constraints = isset( $parsed['extensions']['basicConstraints'] )
			? (string) $parsed['extensions']['basicConstraints']
			: '';

		if ( false !== stripos( $constraints, 'CA:TRUE' ) ) {
			return array();
		}

		return array(
			'name'       => self::distinguished_name( $parsed['subject'] ),
			'issuer'     => isset( $parsed['issuer'] ) ? self::distinguished_name( $parsed['issuer'] ) : '',
			'serial'     => isset( $parsed['serialNumberHex'] ) ? (string) $parsed['serialNumberHex'] : '',
			'valid_to'   => isset( $parsed['validTo_time_t'] ) ? (int) $parsed['validTo_time_t'] : 0,
			'valid_from' => isset( $parsed['validFrom_time_t'] ) ? (int) $parsed['validFrom_time_t'] : 0,
		);
	}

	/**
	 * Reduce un nombre distinguido a algo legible.
	 *
	 * @param array $parts Componentes del nombre.
	 * @return string
	 */
	private static function distinguished_name( array $parts ) {
		foreach ( array( 'CN', 'OU', 'O' ) as $key ) {
			if ( ! empty( $parts[ $key ] ) ) {
				$value = is_array( $parts[ $key ] ) ? end( $parts[ $key ] ) : $parts[ $key ];

				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Elimina firmantes repetidos conservando el orden.
	 *
	 * @param array $signers Lista de firmantes.
	 * @return array
	 */
	private static function unique_signers( array $signers ) {
		$seen   = array();
		$unique = array();

		foreach ( $signers as $signer ) {
			$key = $signer['name'] . '|' . $signer['serial'];

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $signer;
		}

		return $unique;
	}

	/**
	 * Convierte una cadena hexadecimal de PDF en binario.
	 *
	 * @param string $hex Cadena hexadecimal, posiblemente con saltos de línea.
	 * @return string
	 */
	private static function hex_to_binary( $hex ) {
		$clean = preg_replace( '#[^0-9A-Fa-f]#', '', $hex );

		if ( ! is_string( $clean ) || strlen( $clean ) < 2 ) {
			return '';
		}

		if ( 0 !== strlen( $clean ) % 2 ) {
			$clean = substr( $clean, 0, -1 );
		}

		$binary = hex2bin( $clean );

		return is_string( $binary ) ? $binary : '';
	}
}

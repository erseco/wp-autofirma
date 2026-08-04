<?php
/**
 * Almacenamiento del servidor intermedio sobre transients.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use Erseco\AutoFirma\IntermediateServer\Storage\StoreInterface;

/**
 * Guarda los datos en tránsito usando los transients de WordPress.
 *
 * Se eligen los transients y no el sistema de ficheros porque no hay que
 * configurar ninguna ruta, funcionan en cualquier alojamiento y, si el sitio
 * tiene varios nodos con la base de datos o la caché compartidas, funcionan
 * también ahí. El dato que se guarda es opaco: AutoScript y AutoFirma lo cifran
 * entre ellos, y WordPress solo lo transporta durante unos minutos.
 */
final class Transient_Store implements StoreInterface {

	/**
	 * Prefijo de las claves.
	 *
	 * @var string
	 */
	const PREFIX = 'wpaf_is_';

	/**
	 * Identificador de la sesión de firma.
	 *
	 * @var string
	 */
	private $session;

	/**
	 * Construye el almacén de una sesión.
	 *
	 * @param string $session Token de la sesión.
	 */
	public function __construct( $session ) {
		$this->session = (string) $session;
	}

	/**
	 * Guarda un dato con su caducidad.
	 *
	 * @param string $identifier Identificador que asigna AutoScript.
	 * @param string $payload    Contenido opaco.
	 * @param int    $ttlSeconds Segundos de vida.
	 * @return void
	 */
	public function put( string $identifier, string $payload, int $ttlSeconds ): void { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- El nombre lo fija StoreInterface: cambiarlo rompería a quien llamase con argumentos con nombre.
		set_transient( $this->key( $identifier ), $payload, $ttlSeconds ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Ídem.
	}

	/**
	 * Entrega un dato una sola vez.
	 *
	 * El protocolo exige consumo único: ningún resultado puede entregarse dos
	 * veces. Aquí quien devuelve el contenido es quien consigue borrarlo, no
	 * quien consigue leerlo. `delete_transient()` acaba en un `DELETE` que solo
	 * afecta a una fila una vez, así que si dos peticiones simultáneas leen lo
	 * mismo, únicamente la que borra de verdad se lo lleva; la otra recibe
	 * `null`, que es lo correcto.
	 *
	 * Con una caché de objetos persistente la atomicidad depende del backend.
	 * Es una limitación conocida y aceptable: el dato vive segundos y las dos
	 * partes que lo usan son el navegador y la aplicación de quien firma.
	 *
	 * @param string $identifier Identificador que asigna AutoScript.
	 * @return string|null
	 */
	public function consume( string $identifier ): ?string {
		$key     = $this->key( $identifier );
		$payload = get_transient( $key );

		if ( false === $payload ) {
			return null;
		}

		if ( ! delete_transient( $key ) ) {
			return null;
		}

		return (string) $payload;
	}

	/**
	 * Elimina lo caducado.
	 *
	 * WordPress ya retira los transients vencidos: no los devuelve una vez
	 * pasada su hora y su tarea programada `delete_expired_transients` los
	 * borra. No hay nada que hacer aquí.
	 *
	 * @return int
	 */
	public function purgeExpired(): int {
		return 0;
	}

	/**
	 * Calcula la clave de un identificador.
	 *
	 * Se resume con SHA-256 por dos razones: el identificador lo elige
	 * AutoScript y puede llegar a 128 caracteres, que sumados al prefijo de los
	 * transients se acercan al límite de `option_name`; y así la clave queda
	 * atada a la sesión, de modo que dos sesiones distintas nunca comparten
	 * dato aunque coincida el identificador.
	 *
	 * @param string $identifier Identificador que asigna AutoScript.
	 * @return string
	 */
	private function key( $identifier ) {
		return self::PREFIX . substr(
			hash( 'sha256', $this->session . '|' . $identifier ),
			0,
			40
		);
	}
}

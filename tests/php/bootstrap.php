<?php
/**
 * Arranque de pruebas unitarias sin WordPress.
 *
 * @package WPAutoFirma
 */

// El almacén implementa una interfaz de erseco/autofirma-intermediate-server.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/transients.php';

require_once dirname( __DIR__, 2 ) . '/includes/class-signature-data.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-signature-detector.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-transient-store.php';

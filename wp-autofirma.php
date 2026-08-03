<?php
/**
 * Plugin Name:       WP AutoFirma
 * Plugin URI:        https://github.com/erseco/wp-autofirma
 * Description:       Firma documentos PDF de la biblioteca de medios mediante AutoFirma.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Ernesto Serrano
 * Author URI:        https://github.com/erseco
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-autofirma
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WP_AUTOFIRMA_VERSION', '0.1.0' );
define( 'WP_AUTOFIRMA_FILE', __FILE__ );
define( 'WP_AUTOFIRMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_AUTOFIRMA_URL', plugin_dir_url( __FILE__ ) );

// Dependencias de Composer. En el repositorio, `composer install` deja aquí el
// autoload; el paquete distribuible no lleva `vendor/`, y en su lugar incluye la
// librería del servidor intermedio bajo `includes/vendor/`, que carga
// `Bundled_Autoloader`. Si no está ninguna de las dos, el plugin sigue firmando:
// lo único que no podrá ofrecer es el servidor intermedio, y
// `Intermediate_Controller::is_available()` lo comprueba antes de anunciarlo.
if ( is_readable( WP_AUTOFIRMA_PATH . 'vendor/autoload.php' ) ) {
    require_once WP_AUTOFIRMA_PATH . 'vendor/autoload.php';
}

require_once WP_AUTOFIRMA_PATH . 'includes/class-bundled-autoloader.php';
Bundled_Autoloader::register();

require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-data.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-detector.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-index.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-presenter.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-document-service.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-repository.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-rest-controller.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-intermediate-controller.php';

// El almacén implementa una interfaz de la librería, de modo que sin ella la
// clase no puede ni declararse: cargarla provocaría un error irrecuperable y el
// plugin entero dejaría de funcionar. Solo se carga si la interfaz está.
if ( interface_exists( 'Erseco\AutoFirma\IntermediateServer\Storage\StoreInterface' ) ) {
    require_once WP_AUTOFIRMA_PATH . 'includes/class-transient-store.php';
}

require_once WP_AUTOFIRMA_PATH . 'includes/class-shortcodes.php';
require_once WP_AUTOFIRMA_PATH . 'admin/class-media-page.php';
require_once WP_AUTOFIRMA_PATH . 'admin/class-media-library.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-plugin.php';

Plugin::instance()->register();

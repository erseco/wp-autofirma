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
 * Domain Path:       /languages
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

require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-data.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-document-service.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-signature-repository.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-rest-controller.php';
require_once WP_AUTOFIRMA_PATH . 'admin/class-media-page.php';
require_once WP_AUTOFIRMA_PATH . 'includes/class-plugin.php';

Plugin::instance()->register();

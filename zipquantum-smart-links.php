<?php
/**
 * Plugin Name: ZipQuantum – Smart Links & QR Codes
 * Plugin URI: https://zq.tn/
 * Description: Turn WordPress and WooCommerce content into ZipQuantum Smart Links and QR codes.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Xaere
 * Author URI: https://xaere.io/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zipquantum-smart-links
 * Domain Path: /languages
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

define( 'ZIPQUANTUM_SMART_LINKS_VERSION', '1.0.0' );
define( 'ZIPQUANTUM_SMART_LINKS_FILE', __FILE__ );
define( 'ZIPQUANTUM_SMART_LINKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZIPQUANTUM_SMART_LINKS_URL', plugin_dir_url( __FILE__ ) );
define( 'ZIPQUANTUM_SMART_LINKS_BASENAME', plugin_basename( __FILE__ ) );

require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-crypto.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-options.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-http-exception.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-api-client.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-oauth.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-associations.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-sync.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-queue.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-admin.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-plugin.php';
require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-activator.php';

register_activation_hook( __FILE__, array( 'ZIPQUANTUM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZIPQUANTUM_Activator', 'deactivate' ) );

ZIPQUANTUM_Plugin::instance()->boot();

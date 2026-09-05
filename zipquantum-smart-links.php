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

define( 'ZQ_SMART_LINKS_VERSION', '1.0.0' );
define( 'ZQ_SMART_LINKS_FILE', __FILE__ );
define( 'ZQ_SMART_LINKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZQ_SMART_LINKS_URL', plugin_dir_url( __FILE__ ) );
define( 'ZQ_SMART_LINKS_BASENAME', plugin_basename( __FILE__ ) );

require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-crypto.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-options.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-http-exception.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-api-client.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-oauth.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-associations.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-sync.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-queue.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-admin.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-plugin.php';
require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-activator.php';

register_activation_hook( __FILE__, array( 'ZQ_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZQ_Activator', 'deactivate' ) );

ZQ_Plugin::instance()->boot();

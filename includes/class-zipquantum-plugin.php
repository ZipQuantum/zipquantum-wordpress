<?php
/**
 * Plugin composition root.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Plugin {

	/** @var self|null */
	private static $instance;

	/** @var bool */
	private $booted = false;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	public function load() {
		$api   = new ZIPQUANTUM_API_Client();
		$oauth = new ZIPQUANTUM_OAuth( $api );
		$sync  = new ZIPQUANTUM_Sync( $api );
		$queue = new ZIPQUANTUM_Queue( $sync );
		$admin = new ZIPQUANTUM_Admin( $sync );

		$oauth->hooks();
		$sync->hooks();
		$queue->hooks();
		$admin->hooks();
		if ( class_exists( 'WooCommerce' ) ) {
			require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-woocommerce.php';
			ZIPQUANTUM_WooCommerce::instance()->hooks();
		}

		add_filter( 'plugin_action_links_' . ZIPQUANTUM_SMART_LINKS_BASENAME, array( $this, 'action_links' ) );
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=zipquantum-smart-links' ) ) . '">' .
			esc_html__( 'Settings', 'zipquantum-smart-links' ) . '</a>'
		);
		return $links;
	}
}

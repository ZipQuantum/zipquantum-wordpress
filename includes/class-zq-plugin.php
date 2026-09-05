<?php
/**
 * Plugin composition root.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZQ_Plugin {

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
		$api   = new ZQ_API_Client();
		$oauth = new ZQ_OAuth( $api );
		$sync  = new ZQ_Sync( $api );
		$queue = new ZQ_Queue( $sync );
		$admin = new ZQ_Admin( $sync );

		$oauth->hooks();
		$sync->hooks();
		$queue->hooks();
		$admin->hooks();
		if ( class_exists( 'WooCommerce' ) ) {
			require_once ZQ_SMART_LINKS_DIR . 'includes/class-zq-woocommerce.php';
			ZQ_WooCommerce::instance()->hooks();
		}

		add_filter( 'plugin_action_links_' . ZQ_SMART_LINKS_BASENAME, array( $this, 'action_links' ) );
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

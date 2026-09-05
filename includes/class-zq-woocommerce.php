<?php
/**
 * Optional WooCommerce integration.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZQ_WooCommerce {

	/** @var self|null */
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'init', array( __CLASS__, 'add_coupon_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'coupon_redirect' ) );
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_marketing_menu_items', array( $this, 'marketing_menu' ) );
			add_action( 'admin_menu', array( $this, 'marketing_submenu' ), 99 );
		}
	}

	public static function add_coupon_rewrite_rule() {
		add_rewrite_rule( '^zq-coupon/([^/]+)/?$', 'index.php?zq_coupon=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'zq_coupon';
		return $vars;
	}

	public function coupon_redirect() {
		$raw_code = get_query_var( 'zq_coupon' );
		if ( ! $raw_code ) {
			return;
		}
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			wp_die( esc_html__( 'WooCommerce is required to apply this coupon.', 'zipquantum-smart-links' ), '', array( 'response' => 404 ) );
		}

		$settings = ZQ_Options::settings();
		// A nonce is intentionally not required: coupon links are public and shareable.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$encoded_to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$raw_to     = $encoded_to ? rawurldecode( $encoded_to ) : ( $settings['coupon_destination'] ?? '/checkout/' );
		$target     = $this->safe_local_target( $raw_to );
		if ( ! $target ) {
			wp_die( esc_html__( 'The coupon destination must be a local path.', 'zipquantum-smart-links' ), '', array( 'response' => 400 ) );
		}

		$code   = wc_format_coupon_code( sanitize_text_field( rawurldecode( $raw_code ) ) );
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			wp_die( esc_html__( 'This coupon does not exist.', 'zipquantum-smart-links' ), '', array( 'response' => 404 ) );
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			wp_die( esc_html__( 'The WooCommerce cart is unavailable.', 'zipquantum-smart-links' ), '', array( 'response' => 503 ) );
		}

		if ( ! WC()->cart->has_discount( $code ) ) {
			$applied = WC()->cart->apply_coupon( $code );
			if ( ! $applied ) {
				wp_die( esc_html__( 'This coupon cannot be applied to the current cart.', 'zipquantum-smart-links' ), '', array( 'response' => 422 ) );
			}
		}
		wp_safe_redirect( home_url( $target ) );
		exit;
	}

	public function marketing_menu( $items ) {
		$items[] = array(
			'id'            => 'zipquantum-smart-links',
			'title'         => __( 'ZipQuantum Smart Links', 'zipquantum-smart-links' ),
			'parent'        => 'woocommerce-marketing',
			'capability'    => 'manage_woocommerce',
			'existing_page' => true,
			'screen_id'     => 'settings_page_zipquantum-smart-links',
			'path'          => 'options-general.php?page=zipquantum-smart-links',
		);
		return $items;
	}

	public function marketing_submenu() {
		add_submenu_page(
			'woocommerce-marketing',
			__( 'ZipQuantum Smart Links', 'zipquantum-smart-links' ),
			__( 'ZipQuantum Smart Links', 'zipquantum-smart-links' ),
			'manage_woocommerce',
			'options-general.php?page=zipquantum-smart-links'
		);
	}

	private function safe_local_target( $target ) {
		$target = trim( (string) $target );
		if ( '' === $target || '/' !== substr( $target, 0, 1 ) || '//' === substr( $target, 0, 2 ) || false !== strpos( $target, '\\' ) ) {
			return false;
		}
		$parts = wp_parse_url( $target );
		if ( false === $parts || isset( $parts['scheme'] ) || isset( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		return $target;
	}
}

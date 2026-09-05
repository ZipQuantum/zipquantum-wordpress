<?php
/**
 * Activation lifecycle.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Activator {

	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( ZIPQUANTUM_SMART_LINKS_BASENAME, true, true );
			wp_die(
				esc_html__( 'ZipQuantum does not support network activation in version 1.0. Activate it separately on each site.', 'zipquantum-smart-links' ),
				esc_html__( 'Network activation is not supported', 'zipquantum-smart-links' ),
				array( 'back_link' => true )
			);
		}

		$restored_associations = ! ZIPQUANTUM_Options::get( ZIPQUANTUM_Options::INSTALLATION, array() ) && ZIPQUANTUM_Associations::has_any();
		self::create_queue_table();
		ZIPQUANTUM_Options::installation_uuid();
		if ( $restored_associations ) {
			ZIPQUANTUM_Associations::quarantine_all();
			ZIPQUANTUM_Queue::quarantine_all();
		}
		if ( class_exists( 'WooCommerce' ) ) {
			require_once ZIPQUANTUM_SMART_LINKS_DIR . 'includes/class-zipquantum-woocommerce.php';
			ZIPQUANTUM_WooCommerce::add_coupon_rewrite_rule();
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( ZIPQUANTUM_Queue::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, ZIPQUANTUM_Queue::CRON_HOOK );
		}
		delete_transient( ZIPQUANTUM_Queue::LOCK_KEY );
		flush_rewrite_rules();
	}

	public static function create_queue_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = ZIPQUANTUM_Queue::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation varchar(40) NOT NULL,
			object_type varchar(100) NOT NULL,
			object_id varchar(191) NOT NULL,
			payload_hash char(64) NOT NULL,
			payload longtext NOT NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'pending',
			last_error text NULL,
			locked_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY zipquantum_queue_dedupe (operation, object_type, object_id, payload_hash),
			KEY zipquantum_queue_due (status, next_attempt_at)
		) {$charset};";
		dbDelta( $sql );
	}
}

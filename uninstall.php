<?php
/**
 * ZipQuantum uninstall cleanup.
 *
 * Remote Smart Links are deliberately never deleted here.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings            = get_option( 'zq_smart_links_settings', array() );
$delete_associations = ! empty( $settings['delete_metadata_uninstall'] );

wp_clear_scheduled_hook( 'zq_smart_links_process_queue' );
delete_transient( 'zq_smart_links_queue_lock' );

delete_option( 'zq_smart_links_credentials' );
delete_option( 'zq_smart_links_oauth_pending' );
delete_option( 'zq_smart_links_context' );
delete_option( 'zq_smart_links_state' );
delete_option( 'zq_smart_links_installation' );
delete_option( 'zq_smart_links_settings' );

global $wpdb;
$queue_table = $wpdb->prefix . 'zq_queue';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $queue_table ) );

if ( $delete_associations ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_zq_smart_link_association' ), array( '%s' ) );
	if ( isset( $wpdb->termmeta ) ) {
		$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => '_zq_smart_link_association' ), array( '%s' ) );
	}
}

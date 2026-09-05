<?php
/**
 * ZipQuantum uninstall cleanup.
 *
 * Remote Smart Links are deliberately never deleted here.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$zipquantum_settings            = get_option( 'zipquantum_smart_links_settings', array() );
$zipquantum_delete_associations = ! empty( $zipquantum_settings['delete_metadata_uninstall'] );

wp_clear_scheduled_hook( 'zipquantum_smart_links_process_queue' );
delete_transient( 'zipquantum_smart_links_queue_lock' );

delete_option( 'zipquantum_smart_links_credentials' );
delete_option( 'zipquantum_smart_links_oauth_pending' );
delete_option( 'zipquantum_smart_links_context' );
delete_option( 'zipquantum_smart_links_state' );
delete_option( 'zipquantum_smart_links_installation' );
delete_option( 'zipquantum_smart_links_settings' );

global $wpdb;
$zipquantum_queue_table = $wpdb->prefix . 'zipquantum_queue';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $zipquantum_queue_table ) );

if ( $zipquantum_delete_associations ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_zipquantum_smart_link_association' ), array( '%s' ) );
	if ( isset( $wpdb->termmeta ) ) {
		$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => '_zipquantum_smart_link_association' ), array( '%s' ) );
	}
}

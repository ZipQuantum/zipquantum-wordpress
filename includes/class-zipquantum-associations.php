<?php
/**
 * Local association metadata.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Associations {

	const META_KEY = '_zipquantum_smart_link_association';

	public static function get( $object_type, $object_id ) {
		if ( 'product_cat' === $object_type ) {
			$value = get_term_meta( (int) $object_id, self::META_KEY, true );
		} else {
			$value = get_post_meta( (int) $object_id, self::META_KEY, true );
		}

		return is_array( $value ) ? $value : array();
	}

	public static function set( $object_type, $object_id, $association ) {
		$association['local_updated_at'] = gmdate( 'c' );
		if ( 'product_cat' === $object_type ) {
			return update_term_meta( (int) $object_id, self::META_KEY, $association );
		}

		return update_post_meta( (int) $object_id, self::META_KEY, $association );
	}

	public static function delete( $object_type, $object_id ) {
		if ( 'product_cat' === $object_type ) {
			return delete_term_meta( (int) $object_id, self::META_KEY );
		}

		return delete_post_meta( (int) $object_id, self::META_KEY );
	}

	public static function quarantine_all() {
		global $wpdb;
		$post_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_KEY )
		);
		foreach ( $post_ids as $post_id ) {
			$association = get_post_meta( (int) $post_id, self::META_KEY, true );
			if ( is_array( $association ) ) {
				$association['local_status'] = 'quarantined';
				update_post_meta( (int) $post_id, self::META_KEY, $association );
			}
		}

		if ( isset( $wpdb->termmeta ) ) {
			$term_ids = $wpdb->get_col(
				$wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s", self::META_KEY )
			);
			foreach ( $term_ids as $term_id ) {
				$association = get_term_meta( (int) $term_id, self::META_KEY, true );
				if ( is_array( $association ) ) {
					$association['local_status'] = 'quarantined';
					update_term_meta( (int) $term_id, self::META_KEY, $association );
				}
			}
		}
	}

	public static function has_any() {
		global $wpdb;
		$post_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", self::META_KEY )
		);
		if ( $post_id ) {
			return true;
		}
		if ( isset( $wpdb->termmeta ) ) {
			$term_id = $wpdb->get_var(
				$wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s LIMIT 1", self::META_KEY )
			);
			return (bool) $term_id;
		}
		return false;
	}
}

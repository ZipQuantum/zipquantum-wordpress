<?php
/**
 * Non-autoloaded plugin options.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Options {

	const SETTINGS      = 'zipquantum_smart_links_settings';
	const INSTALLATION  = 'zipquantum_smart_links_installation';
	const CREDENTIALS   = 'zipquantum_smart_links_credentials';
	const OAUTH_PENDING = 'zipquantum_smart_links_oauth_pending';
	const CONTEXT       = 'zipquantum_smart_links_context';
	const STATE         = 'zipquantum_smart_links_state';

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public static function settings() {
		return wp_parse_args(
			self::get( self::SETTINGS, array() ),
			array(
				'api_base'                  => 'https://a.zq.tn',
				'managed_subdomain'         => '',
				'custom_domain'             => '',
				'auto_create'               => false,
				'object_types'              => array( 'post', 'page' ),
				'delete_metadata_uninstall' => false,
			)
		);
	}

	/**
	 * Get an option.
	 *
	 * @param string $name Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $name, $default = null ) {
		$value = get_option( $name, null );
		return null === $value ? $default : $value;
	}

	/**
	 * Store an option without autoloading it.
	 *
	 * @param string $name Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public static function set( $name, $value ) {
		if ( false === get_option( $name, false ) ) {
			return add_option( $name, $value, '', false );
		}

		return update_option( $name, $value, false );
	}

	/**
	 * Delete an option.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	public static function delete( $name ) {
		return delete_option( $name );
	}

	/**
	 * Store encrypted data.
	 *
	 * @param string $name Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public static function set_secret( $name, $value ) {
		return self::set( $name, ZIPQUANTUM_Crypto::encrypt( $value ) );
	}

	/**
	 * Read encrypted data.
	 *
	 * @param string $name Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_secret( $name, $default = null ) {
		$value = self::get( $name );
		if ( ! $value ) {
			return $default;
		}

		$decrypted = ZIPQUANTUM_Crypto::decrypt( $value );
		return null === $decrypted ? $default : $decrypted;
	}

	/**
	 * Return or create the local installation UUID.
	 *
	 * @return string
	 */
	public static function installation_uuid() {
		$installation = self::get( self::INSTALLATION, array() );
		if ( empty( $installation['local_uuid'] ) ) {
			$installation['local_uuid'] = wp_generate_uuid4();
			$installation['created_at'] = gmdate( 'c' );
			self::set( self::INSTALLATION, $installation );
		}

		return $installation['local_uuid'];
	}

	/**
	 * Reset local identity while quarantining cloned associations.
	 *
	 * @return string New UUID.
	 */
	public static function create_new_installation() {
		$old = self::installation_uuid();
		$new = wp_generate_uuid4();
		self::set(
			self::INSTALLATION,
			array(
				'local_uuid'          => $new,
				'created_at'          => gmdate( 'c' ),
				'cloned_from_uuid'    => $old,
				'associations_status' => 'quarantined',
			)
		);
		self::delete( self::CREDENTIALS );
		self::delete( self::CONTEXT );

		return $new;
	}
}

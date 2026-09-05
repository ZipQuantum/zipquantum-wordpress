<?php
/**
 * Encryption helpers.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Crypto {

	/**
	 * Encrypt a value with keys derived from the WordPress salts.
	 *
	 * @param mixed $value Serializable value.
	 * @return string
	 * @throws RuntimeException When OpenSSL or secure randomness is unavailable.
	 */
	public static function encrypt( $value ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			throw new RuntimeException( 'ZipQuantum requires the PHP OpenSSL extension.' );
		}

		$iv         = random_bytes( 12 );
		$tag        = '';
		$plain_text = wp_json_encode( $value );
		$cipher     = openssl_encrypt(
			$plain_text,
			'aes-256-gcm',
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'zipquantum-smart-links'
		);

		if ( false === $cipher ) {
			throw new RuntimeException( 'ZipQuantum could not encrypt credentials.' );
		}

		return 'zq1:' . base64_encode(
			wp_json_encode(
				array(
					'iv'   => base64_encode( $iv ),
					'tag'  => base64_encode( $tag ),
					'data' => base64_encode( $cipher ),
				)
			)
		);
	}

	/**
	 * Decrypt a previously encrypted value.
	 *
	 * @param string $payload Encrypted payload.
	 * @return mixed|null
	 */
	public static function decrypt( $payload ) {
		if ( ! is_string( $payload ) || 0 !== strpos( $payload, 'zq1:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return null;
		}

		$envelope = json_decode( base64_decode( substr( $payload, 4 ), true ), true );
		if ( ! is_array( $envelope ) || empty( $envelope['iv'] ) || empty( $envelope['tag'] ) || empty( $envelope['data'] ) ) {
			return null;
		}

		$plain_text = openssl_decrypt(
			base64_decode( $envelope['data'], true ),
			'aes-256-gcm',
			self::key(),
			OPENSSL_RAW_DATA,
			base64_decode( $envelope['iv'], true ),
			base64_decode( $envelope['tag'], true ),
			'zipquantum-smart-links'
		);

		if ( false === $plain_text ) {
			return null;
		}

		return json_decode( $plain_text, true );
	}

	/**
	 * Derive a stable binary key without storing another secret.
	 *
	 * @return string
	 */
	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
	}
}

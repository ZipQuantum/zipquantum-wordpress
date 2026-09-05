<?php
/**
 * ZipQuantum HTTP client.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_API_Client {

	const CLIENT_ID = 'zipquantum-smart-links';
	const RESOURCE  = 'https://a.zq.tn/api';

	/**
	 * Perform an unauthenticated request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path API path.
	 * @param array  $body JSON body.
	 * @param array  $headers Extra headers.
	 * @return array
	 */
	public function public_request( $method, $path, $body = array(), $headers = array() ) {
		return $this->request_raw( $method, $path, $body, $headers );
	}

	/**
	 * Perform an authenticated request, refreshing once after a 401.
	 *
	 * @param string $method HTTP method.
	 * @param string $path API path.
	 * @param array  $body JSON body.
	 * @param array  $headers Extra headers.
	 * @return array
	 */
	public function request( $method, $path, $body = array(), $headers = array() ) {
		$credentials = ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::CREDENTIALS, array() );
		if ( empty( $credentials['access_token'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
			throw new ZIPQUANTUM_HTTP_Exception( __( 'Reconnect ZipQuantum to continue.', 'zipquantum-smart-links' ), 401, 'reconnect_required' );
		}

		$headers['Authorization'] = 'Bearer ' . $credentials['access_token'];
		$headers['X-ZQ-Site-URL'] = home_url( '/' );

		try {
			return $this->request_raw( $method, $path, $body, $headers );
		} catch ( ZIPQUANTUM_HTTP_Exception $error ) {
			if ( 401 !== $error->status() || empty( $credentials['refresh_token'] ) ) {
				throw $error;
			}

			$tokens                   = $this->refresh( $credentials['refresh_token'] );
			$headers['Authorization'] = 'Bearer ' . $tokens['access_token'];
			return $this->request_raw( $method, $path, $body, $headers );
		}
	}

	/**
	 * Refresh and rotate tokens.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array
	 */
	public function refresh( $refresh_token ) {
		$tokens = $this->request_raw(
			'POST',
			'/api/v1/integrations/oauth/token',
			array(
				'grant_type'    => 'refresh_token',
				'client_id'     => self::CLIENT_ID,
				'resource'      => self::RESOURCE,
				'refresh_token' => $refresh_token,
			)
		);
		ZIPQUANTUM_Options::set_secret( ZIPQUANTUM_Options::CREDENTIALS, $tokens );

		return $tokens;
	}

	/**
	 * Normalize and send an HTTP request.
	 *
	 * @param string $method Method.
	 * @param string $path Path.
	 * @param array  $body Body.
	 * @param array  $headers Headers.
	 * @return array
	 */
	private function request_raw( $method, $path, $body, $headers ) {
		$settings = ZIPQUANTUM_Options::settings();
		$base     = untrailingslashit( esc_url_raw( $settings['api_base'] ) );
		$url      = $base . '/' . ltrim( $path, '/' );
		$args     = array(
			'method'      => strtoupper( $method ),
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => array_merge(
				array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ZipQuantum-WordPress/' . ZIPQUANTUM_SMART_LINKS_VERSION . '; ' . home_url( '/' ),
				),
				$headers
			),
		);

		if ( ! empty( $body ) || in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
			throw new ZIPQUANTUM_HTTP_Exception( $response->get_error_message(), 0, 'network_error' );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$content = (string) wp_remote_retrieve_body( $response );
		$data    = '' === $content ? array() : json_decode( $content, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $status < 200 || $status >= 300 ) {
			$message          = isset( $data['message'] ) ? $data['message'] : wp_remote_retrieve_response_message( $response );
			$code             = isset( $data['code'] ) ? $data['code'] : 'http_' . $status;
			$response_headers = wp_remote_retrieve_headers( $response );
			if ( is_object( $response_headers ) && method_exists( $response_headers, 'getAll' ) ) {
				$response_headers = $response_headers->getAll();
			} else {
				$response_headers = (array) $response_headers;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception values are normalized and escaped only at the eventual HTML output boundary.
			throw new ZIPQUANTUM_HTTP_Exception( sanitize_text_field( $message ), $status, sanitize_key( $code ), $response_headers );
		}

		return $data;
	}
}

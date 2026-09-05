<?php
/**
 * OAuth handoff and PKCE workflow.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_OAuth {

	/** @var ZIPQUANTUM_API_Client */
	private $api;

	public function __construct( ZIPQUANTUM_API_Client $api ) {
		$this->api = $api;
	}

	public function hooks() {
		add_action( 'wp_ajax_zipquantum_oauth_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_zipquantum_oauth_poll', array( $this, 'ajax_poll' ) );
		add_action( 'admin_post_zipquantum_disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_post_zipquantum_new_installation', array( $this, 'new_installation' ) );
	}

	public function ajax_start() {
		$this->guard_ajax();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by guard_ajax().
		$intent = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : 'connect';
		if ( ! in_array( $intent, array( 'connect', 'move', 'reconnect' ), true ) ) {
			$intent = 'connect';
		}

		try {
			$verifier  = self::random_urlsafe( 64 );
			$state     = self::random_urlsafe( 48 );
			$nonce     = self::random_urlsafe( 64 );
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			$response  = $this->api->public_request(
				'POST',
				'/api/v1/integrations/wordpress/handshakes',
				array(
					'client_id'             => ZIPQUANTUM_API_Client::CLIENT_ID,
					'installation_uuid'     => ZIPQUANTUM_Options::installation_uuid(),
					'installation_nonce'    => $nonce,
					'home_url'              => home_url( '/' ),
					'state'                 => $state,
					'code_challenge'        => $challenge,
					'code_challenge_method' => 'S256',
					'intent'                => $intent,
				)
			);

			ZIPQUANTUM_Options::set_secret(
				ZIPQUANTUM_Options::OAUTH_PENDING,
				array(
					'handshake_id'   => $response['handshake_id'],
					'polling_secret' => $response['polling_secret'],
					'state'          => $state,
					'code_verifier'  => $verifier,
					'expires_at'     => time() + (int) $response['expires_in'],
				)
			);

			$authorization_host = wp_parse_url( $response['authorization_url'], PHP_URL_HOST );
			$authorization_path = wp_parse_url( $response['authorization_url'], PHP_URL_PATH );
			$api_host           = wp_parse_url( ZIPQUANTUM_Options::settings()['api_base'], PHP_URL_HOST );
			if ( 'https' !== wp_parse_url( $response['authorization_url'], PHP_URL_SCHEME ) || ! $authorization_host || ! $authorization_path || ! hash_equals( strtolower( $api_host ), strtolower( $authorization_host ) ) || 0 !== strpos( $authorization_path, '/integrations/wordpress/authorize/' ) ) {
				ZIPQUANTUM_Options::delete( ZIPQUANTUM_Options::OAUTH_PENDING );
				throw new ZIPQUANTUM_HTTP_Exception( __( 'ZipQuantum returned an invalid authorization URL.', 'zipquantum-smart-links' ), 502, 'invalid_authorization_url' );
			}

			wp_send_json_success(
				array(
					'authorization_url' => esc_url_raw( $response['authorization_url'] ),
					'interval'          => max( 3, (int) $response['interval'] ),
				)
			);
		} catch ( ZIPQUANTUM_HTTP_Exception $error ) {
			$this->send_error( $error );
		} catch ( Throwable $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 500 );
		}
	}

	public function ajax_poll() {
		$this->guard_ajax();
		$pending = ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::OAUTH_PENDING, array() );
		if ( empty( $pending['handshake_id'] ) || empty( $pending['polling_secret'] ) || empty( $pending['code_verifier'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Start the ZipQuantum connection again.', 'zipquantum-smart-links' ) ), 410 );
		}

		if ( time() >= (int) $pending['expires_at'] ) {
			ZIPQUANTUM_Options::delete( ZIPQUANTUM_Options::OAUTH_PENDING );
			wp_send_json_error( array( 'message' => __( 'The connection request expired.', 'zipquantum-smart-links' ) ), 410 );
		}

		try {
			$poll = $this->api->public_request(
				'POST',
				'/api/v1/integrations/wordpress/handshakes/' . rawurlencode( $pending['handshake_id'] ) . '/poll',
				array( 'polling_secret' => $pending['polling_secret'] )
			);

			if ( 'authorized' !== ( $poll['status'] ?? '' ) ) {
				wp_send_json_success( array( 'status' => 'pending' ) );
			}

			if ( empty( $poll['state'] ) || ! hash_equals( $pending['state'], $poll['state'] ) ) {
				throw new ZIPQUANTUM_HTTP_Exception( __( 'OAuth state verification failed.', 'zipquantum-smart-links' ), 400, 'invalid_state' );
			}

			$tokens = $this->api->public_request(
				'POST',
				'/api/v1/integrations/oauth/token',
				array(
					'grant_type'    => 'authorization_code',
					'client_id'     => ZIPQUANTUM_API_Client::CLIENT_ID,
					'resource'      => ZIPQUANTUM_API_Client::RESOURCE,
					'code'          => $poll['authorization_code'],
					'code_verifier' => $pending['code_verifier'],
				)
			);

			ZIPQUANTUM_Options::set_secret( ZIPQUANTUM_Options::CREDENTIALS, $tokens );
			ZIPQUANTUM_Options::delete( ZIPQUANTUM_Options::OAUTH_PENDING );
			ZIPQUANTUM_Options::set(
				ZIPQUANTUM_Options::STATE,
				array(
					'identity_mismatch' => false,
					'connected_at'      => gmdate( 'c' ),
				)
			);
			$context = $this->api->request( 'GET', '/api/v1/integration/context' );
			ZIPQUANTUM_Options::set( ZIPQUANTUM_Options::CONTEXT, $context );

			wp_send_json_success(
				array(
					'status'  => 'connected',
					'context' => $context,
				)
			);
		} catch ( ZIPQUANTUM_HTTP_Exception $error ) {
			if ( 202 === $error->status() ) {
				wp_send_json_success( array( 'status' => 'pending' ) );
			}
			$this->send_error( $error );
		}
	}

	public function disconnect() {
		$this->guard_action( 'zipquantum_disconnect' );
		$credentials = ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::CREDENTIALS, array() );
		if ( ! empty( $credentials['access_token'] ) ) {
			try {
				$this->api->public_request( 'POST', '/api/v1/integrations/oauth/revoke', array( 'token' => $credentials['access_token'] ) );
			} catch ( Throwable $error ) {
				// Local revocation must still complete when the service is unavailable.
			}
		}
		ZIPQUANTUM_Options::delete( ZIPQUANTUM_Options::CREDENTIALS );
		ZIPQUANTUM_Options::delete( ZIPQUANTUM_Options::CONTEXT );
		wp_safe_redirect( admin_url( 'options-general.php?page=zipquantum-smart-links&zipquantum_notice=disconnected' ) );
		exit;
	}

	public function new_installation() {
		$this->guard_action( 'zipquantum_new_installation' );
		ZIPQUANTUM_Options::create_new_installation();
		ZIPQUANTUM_Associations::quarantine_all();
		ZIPQUANTUM_Queue::quarantine_all();
		wp_safe_redirect( admin_url( 'options-general.php?page=zipquantum-smart-links&zipquantum_notice=new_installation' ) );
		exit;
	}

	private function guard_ajax() {
		check_ajax_referer( 'zipquantum_oauth', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zipquantum-smart-links' ) ), 403 );
		}
	}

	private function guard_action( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zipquantum-smart-links' ) );
		}
		check_admin_referer( $action );
	}

	private function send_error( ZIPQUANTUM_HTTP_Exception $error ) {
		if ( 409 === $error->status() && 'installation_identity_mismatch' === $error->api_code() ) {
			ZIPQUANTUM_Options::set(
				ZIPQUANTUM_Options::STATE,
				array(
					'identity_mismatch' => true,
					'detected_at'       => gmdate( 'c' ),
				)
			);
		}
		wp_send_json_error(
			array(
				'message' => $error->getMessage(),
				'code'    => $error->api_code(),
			),
			$error->status() ? $error->status() : 500
		);
	}

	private static function random_urlsafe( $bytes ) {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}
}

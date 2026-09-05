<?php
/**
 * Independent retry queue.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Queue {

	const CRON_HOOK = 'zipquantum_smart_links_process_queue';
	const LOCK_KEY  = 'zipquantum_smart_links_queue_lock';

	/** @var ZIPQUANTUM_Sync */
	private $sync;

	public function __construct( ZIPQUANTUM_Sync $sync ) {
		$this->sync = $sync;
	}

	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'ensure_schedule' ), 20 );
		add_action( self::CRON_HOOK, array( $this, 'process' ) );
		add_action( 'admin_init', array( $this, 'opportunistic_process' ), 99 );
		add_action( 'admin_post_zipquantum_queue_retry', array( $this, 'retry_failed' ) );
		add_action( 'admin_post_zipquantum_queue_resume', array( $this, 'resume' ) );
	}

	public function ensure_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'zipquantum_every_minute', self::CRON_HOOK );
		}
	}

	public function cron_schedules( $schedules ) {
		$schedules['zipquantum_every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (ZipQuantum)', 'zipquantum-smart-links' ),
		);
		return $schedules;
	}

	public static function enqueue( $operation, $object_type, $object_id, $payload ) {
		global $wpdb;
		$table = self::table();
		$hash  = ZIPQUANTUM_Sync::payload_hash( $payload );
		$now   = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (operation, object_type, object_id, payload_hash, payload, attempts, next_attempt_at, status, created_at, updated_at)
				 VALUES (%s, %s, %s, %s, %s, 0, %s, 'pending', %s, %s)
				 ON DUPLICATE KEY UPDATE payload = VALUES(payload), next_attempt_at = VALUES(next_attempt_at), status = IF(status = 'processing', status, 'pending'), updated_at = VALUES(updated_at)",
				$table,
				$operation,
				$object_type,
				(string) $object_id,
				$hash,
				wp_json_encode( $payload ),
				$now,
				$now,
				$now
			)
		);
	}

	public function opportunistic_process() {
		if ( ! wp_doing_ajax() && current_user_can( 'manage_options' ) ) {
			$this->process( 2 );
		}
	}

	public function process( $limit = 5 ) {
		if ( get_transient( self::LOCK_KEY ) || ! ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::CREDENTIALS, array() ) ) {
			return;
		}
		set_transient( self::LOCK_KEY, 1, 30 );

		try {
			global $wpdb;
			$table = self::table();
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE status IN ('pending', 'retry') AND next_attempt_at <= %s ORDER BY id ASC LIMIT %d",
					$table,
					current_time( 'mysql', true ),
					absint( $limit )
				),
				ARRAY_A
			);

			foreach ( $rows as $row ) {
				$this->process_row( $row );
			}
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	private function process_row( $row ) {
		global $wpdb;
		$table   = self::table();
		$claimed = $wpdb->update(
			$table,
			array(
				'status'    => 'processing',
				'locked_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $row['id'],
				'status' => $row['status'],
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( ! $claimed ) {
			return;
		}

		try {
			$payload = json_decode( $row['payload'], true );
			$this->sync->sync( $row['object_type'], $row['object_id'], is_array( $payload ) ? $payload : array() );
			$wpdb->update(
				$table,
				array(
					'status'     => 'complete',
					'last_error' => null,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $row['id'] )
			);
		} catch ( ZIPQUANTUM_HTTP_Exception $error ) {
			$this->handle_error( $row, $error );
		} catch ( Throwable $error ) {
			$this->schedule_retry( $row, $error->getMessage() );
		}
	}

	private function handle_error( $row, ZIPQUANTUM_HTTP_Exception $error ) {
		global $wpdb;
		$table = self::table();
		if ( 409 === $error->status() && 'installation_identity_mismatch' === $error->api_code() ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET status = 'blocked', last_error = 'installation_identity_mismatch' WHERE status IN ('pending', 'retry', 'processing')",
					$table
				)
			);
			ZIPQUANTUM_Options::set(
				ZIPQUANTUM_Options::STATE,
				array(
					'identity_mismatch' => true,
					'detected_at'       => gmdate( 'c' ),
				)
			);
			return;
		}
		if ( 422 === $error->status() ) {
			$wpdb->update(
				$table,
				array(
					'status'     => 'failed',
					'last_error' => $error->getMessage(),
				),
				array( 'id' => $row['id'] )
			);
			return;
		}
		if ( 401 === $error->status() ) {
			$wpdb->update(
				$table,
				array(
					'status'     => 'blocked',
					'last_error' => 'reconnect_required',
				),
				array( 'id' => $row['id'] )
			);
			return;
		}
		if ( 429 === $error->status() ) {
			$headers     = array_change_key_case( $error->headers(), CASE_LOWER );
			$retry_after = isset( $headers['retry-after'] ) ? self::retry_after_seconds( $headers['retry-after'] ) : 60;
			$this->schedule_retry( $row, $error->getMessage(), max( 1, $retry_after ) );
			return;
		}
		$this->schedule_retry( $row, $error->getMessage() );
	}

	private function schedule_retry( $row, $message, $forced_delay = 0 ) {
		global $wpdb;
		$table    = self::table();
		$attempts = (int) $row['attempts'] + 1;
		$delays   = array( 60, 300, 1800, 7200, 43200 );
		if ( $attempts > count( $delays ) ) {
			$wpdb->update(
				$table,
				array(
					'status'     => 'failed',
					'attempts'   => $attempts,
					'last_error' => $message,
				),
				array( 'id' => $row['id'] )
			);
			return;
		}
		$delay  = $forced_delay ? $forced_delay : $delays[ $attempts - 1 ];
		$delay += wp_rand( 0, max( 3, (int) floor( $delay * 0.15 ) ) );
		$wpdb->update(
			$table,
			array(
				'status'          => 'retry',
				'attempts'        => $attempts,
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'last_error'      => sanitize_text_field( $message ),
				'updated_at'      => current_time( 'mysql', true ),
			),
			array( 'id' => $row['id'] )
		);
	}

	public function retry_failed() {
		$this->guard( 'zipquantum_queue_retry' );
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'pending', attempts = 0, next_attempt_at = UTC_TIMESTAMP(), last_error = NULL WHERE status = 'failed'",
				self::table()
			)
		);
		$this->redirect_tools();
	}

	public static function quarantine_all() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'quarantined', last_error = 'cloned_installation' WHERE status <> 'complete'",
				self::table()
			)
		);
	}

	public static function cancel_object( $object_type, $object_id ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'cancelled',
				'last_error' => 'wordpress_object_deleted',
			),
			array(
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => (string) absint( $object_id ),
			),
			array( '%s', '%s' ),
			array( '%s', '%s' )
		);
	}

	public function resume() {
		$this->guard( 'zipquantum_queue_resume' );
		$state = ZIPQUANTUM_Options::get( ZIPQUANTUM_Options::STATE, array() );
		if ( empty( $state['identity_mismatch'] ) ) {
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET status = 'pending', next_attempt_at = UTC_TIMESTAMP(), last_error = NULL WHERE status = 'blocked'",
					self::table()
				)
			);
		}
		$this->redirect_tools();
	}

	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zipquantum-smart-links' ) );
		}
		check_admin_referer( $action );
	}

	private function redirect_tools() {
		wp_safe_redirect( admin_url( 'tools.php?page=zipquantum-smart-links-tools' ) );
		exit;
	}

	public static function stats() {
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT status, COUNT(*) total FROM %i GROUP BY status', self::table() ),
			OBJECT_K
		);
		$stats = array();
		foreach ( (array) $rows as $status => $row ) {
			$stats[ $status ] = (int) $row->total;
		}
		return $stats;
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zipquantum_queue';
	}

	private static function retry_after_seconds( $value ) {
		if ( is_numeric( $value ) ) {
			return max( 1, absint( $value ) );
		}
		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? 60 : max( 1, $timestamp - time() );
	}
}

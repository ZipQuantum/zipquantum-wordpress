<?php
/**
 * API exception.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

class ZIPQUANTUM_HTTP_Exception extends RuntimeException {

	/** @var int */
	private $status;

	/** @var string */
	private $api_code;

	/** @var array */
	private $headers;

	/**
	 * Constructor.
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @param string $api_code API code.
	 * @param array  $headers Response headers.
	 */
	public function __construct( $message, $status = 0, $api_code = '', $headers = array() ) {
		parent::__construct( $message, $status );
		$this->status   = (int) $status;
		$this->api_code = (string) $api_code;
		$this->headers  = (array) $headers;
	}

	public function status() {
		return $this->status;
	}

	public function api_code() {
		return $this->api_code;
	}

	public function headers() {
		return $this->headers;
	}
}

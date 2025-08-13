<?php

/**
 * Dokobit API integration class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Dokobit_API {

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_endpoint = get_option( 'bc_dokobit_api_endpoint', 'https://developers.dokobit.com' );
		$this->api_key = get_option( 'bc_dokobit_api_key', '' );
	}

	/**
	 * Make API request to Dokobit.
	 *
	 * @param string $action API action.
	 * @param array  $params Request parameters.
	 * @param string $method HTTP method.
	 * @return array
	 */
	private function make_request( $action, $params = array(), $method = 'POST' ) {
		$url = $this->api_endpoint . '/';
		
		if ( $method === 'GET' ) {
			$params['access_token'] = $this->api_key;
			$url .= $action . '?' . http_build_query( $params );
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );
		} else {
			$url .= $action . '?access_token=' . $this->api_key;
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			if ( ! empty( $params ) ) {
				curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params ) );
			}
		}
		
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded'
		) );
		
		$response = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $ch );
		curl_close( $ch );
		
		if ( $response === false ) {
			error_log( 'BC Dokobit API CURL Error: ' . $curl_error );
			return array( 'error' => 'CURL Error: ' . $curl_error );
		}
		
		if ( $http_code >= 400 ) {
			error_log( 'BC Dokobit API HTTP Error: ' . $http_code . ' Response: ' . $response );
			return array( 'error' => 'HTTP Error: ' . $http_code, 'response' => $response );
		}
		
		$decoded = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'BC Dokobit API JSON Error: ' . json_last_error_msg() );
			return array( 'error' => 'JSON Error: ' . json_last_error_msg() );
		}
		
		return $decoded;
	}

	/**
	 * Initiate mobile login.
	 *
	 * @param string $phone Phone number.
	 * @return array
	 */
	public function initiate_mobile_login( $phone ) {
		$params = array(
			'phone' => $phone,
			'message' => sprintf( __( 'Login to %s', 'bc-business-central-sync' ), get_bloginfo( 'name' ) )
		);
		
		return $this->make_request( 'v2/mobile/login.json', $params );
	}

	/**
	 * Check login status.
	 *
	 * @param string $token Authentication token.
	 * @return array
	 */
	public function check_login_status( $token ) {
		return $this->make_request( 'v2/mobile/login/status/' . $token . '.json', array(), 'GET' );
	}

	/**
	 * Get certificate information.
	 *
	 * @param string $token Authentication token.
	 * @return array
	 */
	public function get_certificate_info( $token ) {
		return $this->make_request( 'v2/mobile/certificate/' . $token . '.json', array(), 'GET' );
	}

	/**
	 * Test API connection.
	 *
	 * @return array
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return array( 'error' => 'API key not configured' );
		}
		
		// Try to make a simple request to test connection
		$test_params = array(
			'phone' => '+37060000000',
			'message' => 'Test connection'
		);
		
		$result = $this->make_request( 'v2/mobile/login.json', $test_params );
		
		if ( isset( $result['error'] ) ) {
			return array( 'error' => 'Connection failed: ' . $result['error'] );
		}
		
		return array( 'success' => true, 'message' => 'Connection successful' );
	}

}

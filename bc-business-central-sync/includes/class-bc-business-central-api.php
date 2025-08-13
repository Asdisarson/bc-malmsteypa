<?php

/**
 * Business Central API integration class.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Business_Central_API {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Company ID.
	 *
	 * @var string
	 */
	private $company_id;

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client Secret.
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_url = get_option( 'bc_api_url' );
		$this->company_id = get_option( 'bc_company_id' );
		$this->client_id = get_option( 'bc_client_id' );
		$this->client_secret = get_option( 'bc_client_secret' );
	}

	/**
	 * Get access token using client credentials flow.
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_access_token() {
		if ( $this->access_token ) {
			return $this->access_token;
		}

		$token_url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
		
		$response = wp_remote_post( $token_url, array(
			'body' => array(
				'grant_type' => 'client_credentials',
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'scope' => 'https://api.businesscentral.dynamics.com/.default'
			)
		) );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Failed to get access token: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['access_token'] ) ) {
			throw new Exception( 'Invalid token response: ' . $body );
		}

		$this->access_token = $data['access_token'];
		return $this->access_token;
	}

	/**
	 * Make API request to Business Central.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $data Request data.
	 * @return array
	 * @throws Exception
	 */
	public function make_request( $endpoint, $method = 'GET', $data = null ) {
		$token = $this->get_access_token();
		
		$url = $this->api_url . '/' . $this->company_id . '/' . $endpoint;
		
		$args = array(
			'method' => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json'
			),
			'timeout' => 30
		);
		
		if ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = json_encode( $data );
		}
		
		$response = wp_remote_request( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'API request failed: ' . $response->get_error_message() );
		}
		
		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		if ( $status_code >= 400 ) {
			throw new Exception( 'API error ' . $status_code . ': ' . $body );
		}
		
		$data = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Invalid JSON response: ' . $body );
		}
		
		return $data;
	}

	/**
	 * Get products from Business Central.
	 *
	 * @param array $filters Optional filters.
	 * @return array
	 * @throws Exception
	 */
	public function get_products( $filters = array() ) {
		$endpoint = 'items';
		
		if ( ! empty( $filters ) ) {
			$query_params = array();
			foreach ( $filters as $key => $value ) {
				$query_params[] = '$filter=' . urlencode( $key . ' eq ' . $value );
			}
			$endpoint .= '?' . implode( '&', $query_params );
		}
		
		$response = $this->make_request( $endpoint );
		
		return isset( $response['value'] ) ? $response['value'] : array();
	}

	/**
	 * Get product by ID from Business Central.
	 *
	 * @param string $product_id Product ID.
	 * @return array|false
	 * @throws Exception
	 */
	public function get_product_by_id( $product_id ) {
		$endpoint = 'items(' . urlencode( $product_id ) . ')';
		
		try {
			$response = $this->make_request( $endpoint );
			return $response;
		} catch ( Exception $e ) {
			if ( strpos( $e->getMessage(), '404' ) !== false ) {
				return false;
			}
			throw $e;
		}
	}

	/**
	 * Get pricelists from Business Central.
	 *
	 * @param array $filters Optional filters.
	 * @return array
	 * @throws Exception
	 */
	public function get_pricelists( $filters = array() ) {
		$endpoint = 'salesPriceLists';
		
		if ( ! empty( $filters ) ) {
			$query_params = array();
			foreach ( $filters as $key => $value ) {
				$query_params[] = '$filter=' . urlencode( $key . ' eq ' . $value );
			}
			$endpoint .= '?' . implode( '&', $query_params );
		}
		
		$response = $this->make_request( $endpoint );
		
		return isset( $response['value'] ) ? $response['value'] : array();
	}

	/**
	 * Get pricelist by ID from Business Central.
	 *
	 * @param string $pricelist_id Pricelist ID.
	 * @return array|false
	 * @throws Exception
	 */
	public function get_pricelist_by_id( $pricelist_id ) {
		$endpoint = 'salesPriceLists(' . urlencode( $pricelist_id ) . ')';
		
		try {
			$response = $this->make_request( $endpoint );
			return $response;
		} catch ( Exception $e ) {
			if ( strpos( $e->getMessage(), '404' ) !== false ) {
				return false;
			}
			throw $e;
		}
	}

	/**
	 * Get pricelist lines from Business Central.
	 *
	 * @param string $pricelist_id Pricelist ID.
	 * @param array $filters Optional filters.
	 * @return array
	 * @throws Exception
	 */
	public function get_pricelist_lines( $pricelist_id, $filters = array() ) {
		$endpoint = 'salesPriceLists(' . urlencode( $pricelist_id ) . ')/salesPriceListLines';
		
		if ( ! empty( $filters ) ) {
			$query_params = array();
			foreach ( $filters as $key => $value ) {
				$query_params[] = '$filter=' . urlencode( $key . ' eq ' . $value );
			}
			$endpoint .= '?' . implode( '&', $query_params );
		}
		
		$response = $this->make_request( $endpoint );
		
		return isset( $response['value'] ) ? $response['value'] : array();
	}

	/**
	 * Get companies from Business Central.
	 *
	 * @param array $filters Optional filters.
	 * @return array
	 * @throws Exception
	 */
	public function get_companies( $filters = array() ) {
		$endpoint = 'customers';
		
		if ( ! empty( $filters ) ) {
			$query_params = array();
			foreach ( $filters as $key => $value ) {
				$query_params[] = '$filter=' . urlencode( $key . ' eq ' . $value );
			}
			$endpoint .= '?' . implode( '&', $query_params );
		}
		
		$response = $this->make_request( $endpoint );
		
		return isset( $response['value'] ) ? $response['value'] : array();
	}

	/**
	 * Get company by ID from Business Central.
	 *
	 * @param string $company_id Company ID.
	 * @return array|false
	 * @throws Exception
	 */
	public function get_company_by_id( $company_id ) {
		$endpoint = 'customers(' . urlencode( $company_id ) . ')';
		
		try {
			$response = $this->make_request( $endpoint );
			return $response;
		} catch ( Exception $e ) {
			if ( strpos( $e->getMessage(), '404' ) !== false ) {
				return false;
			}
			throw $e;
		}
	}

	/**
	 * Get customers from Business Central.
	 *
	 * @param array $filters Optional filters.
	 * @return array
	 * @throws Exception
	 */
	public function get_customers( $filters = array() ) {
		$endpoint = 'customers';
		
		if ( ! empty( $filters ) ) {
			$query_params = array();
			foreach ( $filters as $key => $value ) {
				$query_params[] = '$filter=' . urlencode( $key . ' eq ' . $value );
			}
			$endpoint .= '?' . implode( '&', $query_params );
		}
		
		$response = $this->make_request( $endpoint );
		
		return isset( $response['value'] ) ? $response['value'] : array();
	}

	/**
	 * Get customer by ID from Business Central.
	 *
	 * @param string $customer_id Customer ID.
	 * @return array|false
	 * @throws Exception
	 */
	public function get_customer_by_id( $customer_id ) {
		$endpoint = 'customers(' . urlencode( $customer_id ) . ')';
		
		try {
			$response = $this->make_request( $endpoint );
			return $response;
		} catch ( Exception $e ) {
			if ( strpos( $e->getMessage(), '404' ) !== false ) {
				return false;
			}
			throw $e;
		}
	}

	/**
	 * Test the API connection.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function test_connection() {
		try {
			// Try to get a simple endpoint to test connection
			$response = $this->make_request( 'customers?$top=1' );
			
			return array(
				'success' => true,
				'message' => 'Connection successful',
				'data' => $response
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Connection failed: ' . $e->getMessage(),
				'error' => $e->getMessage()
			);
		}
	}

	/**
	 * Get API status and limits.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_api_status() {
		try {
			$response = $this->make_request( '$metadata' );
			
			return array(
				'success' => true,
				'data' => $response
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error' => $e->getMessage()
			);
		}
	}
}

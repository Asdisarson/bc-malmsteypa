<?php

/**
 * Business Central API Integration Class
 *
 * Handles all communication with Microsoft Dynamics 365 Business Central
 * via REST API, including authentication, product fetching, and data synchronization.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes/features
 */
class BC_Business_Central_API extends BC_Plugin_Core {

	// =============================================================================
	// CLASS PROPERTIES
	// =============================================================================

	/**
	 * API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_url;

	/**
	 * Company ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $company_id;

	/**
	 * Client ID for Azure AD authentication.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $client_id;

	/**
	 * Client secret for Azure AD authentication.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $client_secret;

	/**
	 * Access token for API requests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $access_token;

	/**
	 * Token expiration time.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $token_expires;

	// =============================================================================
	// CONSTRUCTOR & INITIALIZATION
	// =============================================================================

	/**
	 * Initialize the Business Central API class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();
		$this->init_api_config();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function init_hooks() {
		// API-specific hooks can be added here
	}

	/**
	 * Initialize API configuration from plugin options.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function init_api_config() {
		$this->api_url = $this->get_option( 'api_url', '' );
		$this->company_id = $this->get_option( 'company_id', '' );
		$this->client_id = $this->get_option( 'client_id', '' );
		$this->client_secret = $this->get_option( 'client_secret', '' );
	}

	// =============================================================================
	// AUTHENTICATION METHODS
	// =============================================================================

	/**
	 * Get access token for Business Central API.
	 *
	 * @since 1.0.0
	 * @return string|false Access token on success, false on failure.
	 */
	private function get_access_token() {
		// Check if we have a valid token
		if ( $this->access_token && $this->token_expires > time() ) {
			return $this->access_token;
		}

		// Get new token
		return $this->request_access_token();
	}

	/**
	 * Request new access token from Azure AD.
	 *
	 * @since 1.0.0
	 * @return string|false Access token on success, false on failure.
	 */
	private function request_access_token() {
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			$this->log( 'Missing client credentials for Business Central API', 'error' );
			return false;
		}

		$token_url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
		
		$body = array(
			'grant_type' => 'client_credentials',
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'scope' => 'https://api.businesscentral.dynamics.com/.default',
		);

		$response = wp_remote_post( $token_url, array(
			'body' => $body,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Failed to get access token: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['access_token'] ) ) {
			$this->access_token = $data['access_token'];
			$this->token_expires = time() + ( $data['expires_in'] - 300 ); // 5 minutes buffer
			return $this->access_token;
		}

		$this->log( 'Failed to get access token from response: ' . $body, 'error' );
		return false;
	}

	// =============================================================================
	// API REQUEST METHODS
	// =============================================================================

	/**
	 * Make authenticated request to Business Central API.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $data Request data.
	 * @return array|false Response data on success, false on failure.
	 */
	private function make_api_request( $endpoint, $method = 'GET', $data = array() ) {
		$access_token = $this->get_access_token();
		if ( ! $access_token ) {
			return false;
		}

		$url = $this->api_url . '/companies(' . $this->company_id . ')/' . $endpoint;
		
		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
		);

		$args = array(
			'method' => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'API request failed: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 200 && $response_code < 300 ) {
			return json_decode( $response_body, true );
		}

		$this->log( 'API request failed with code ' . $response_code . ': ' . $response_body, 'error' );
		return false;
	}

	// =============================================================================
	// PRODUCT METHODS
	// =============================================================================

	/**
	 * Get products from Business Central.
	 *
	 * @since 1.0.0
	 * @param int $limit Maximum number of products to fetch.
	 * @return array|false Array of products on success, false on failure.
	 */
	public function get_products( $limit = 1000 ) {
		$endpoint = 'items?$top=' . $limit . '&$filter=type eq \'Inventory\'';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	/**
	 * Get single product by ID.
	 *
	 * @since 1.0.0
	 * @param string $product_id Product ID.
	 * @return array|false Product data on success, false on failure.
	 */
	public function get_product( $product_id ) {
		$endpoint = 'items(' . $product_id . ')';
		return $this->make_api_request( $endpoint );
	}

	/**
	 * Get products with pagination.
	 *
	 * @since 1.0.0
	 * @param string $skip_token Skip token for pagination.
	 * @param int    $limit      Number of products per page.
	 * @return array|false Array of products on success, false on failure.
	 */
	public function get_products_paginated( $skip_token = '', $limit = 100 ) {
		$endpoint = 'items?$top=' . $limit . '&$filter=type eq \'Inventory\'';
		
		if ( ! empty( $skip_token ) ) {
			$endpoint .= '&$skiptoken=' . urlencode( $skip_token );
		}

		$response = $this->make_api_request( $endpoint );

		if ( $response ) {
			return array(
				'products' => isset( $response['value'] ) ? $response['value'] : array(),
				'next_link' => isset( $response['@odata.nextLink'] ) ? $response['@odata.nextLink'] : '',
			);
		}

		return false;
	}

	// =============================================================================
	// PRICELIST METHODS
	// =============================================================================

	/**
	 * Get pricelists from Business Central.
	 *
	 * @since 1.0.0
	 * @return array|false Array of pricelists on success, false on failure.
	 */
	public function get_pricelists() {
		$endpoint = 'salesPriceLists';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	/**
	 * Get pricelist lines from Business Central.
	 *
	 * @since 1.0.0
	 * @param string $pricelist_id Pricelist ID.
	 * @return array|false Array of pricelist lines on success, false on failure.
	 */
	public function get_pricelist_lines( $pricelist_id ) {
		$endpoint = 'salesPriceLists(' . $pricelist_id . ')/salesPriceListLines';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	/**
	 * Get all pricelist lines from all pricelists.
	 *
	 * @since 1.0.0
	 * @return array|false Array of all pricelist lines on success, false on failure.
	 */
	public function get_all_pricelist_lines() {
		$endpoint = 'salesPriceListLines';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	// =============================================================================
	// CUSTOMER METHODS
	// =============================================================================

	/**
	 * Get customers from Business Central.
	 *
	 * @since 1.0.0
	 * @return array|false Array of customers on success, false on failure.
	 */
	public function get_customers() {
		$endpoint = 'customers';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	/**
	 * Get customer companies from Business Central.
	 *
	 * @since 1.0.0
	 * @return array|false Array of customer companies on success, false on failure.
	 */
	public function get_customer_companies() {
		$endpoint = 'customers?$select=id,number,name,priceListId';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	/**
	 * Get customers with company information.
	 *
	 * @since 1.0.0
	 * @return array|false Array of customers with companies on success, false on failure.
	 */
	public function get_customers_with_companies() {
		$endpoint = 'customers?$expand=priceList';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	// =============================================================================
	// COMPANY METHODS
	// =============================================================================

	/**
	 * Get companies from Business Central.
	 *
	 * @since 1.0.0
	 * @return array|false Array of companies on success, false on failure.
	 */
	public function get_companies() {
		$endpoint = 'companies';
		$response = $this->make_api_request( $endpoint );

		if ( $response && isset( $response['value'] ) ) {
			return $response['value'];
		}

		return false;
	}

	// =============================================================================
	// TESTING & VALIDATION METHODS
	// =============================================================================

	/**
	 * Test connection to Business Central API.
	 *
	 * @since 1.0.0
	 * @return array|false Connection test result on success, false on failure.
	 */
	public function test_connection() {
		// Try to get a small number of products to test the connection
		$endpoint = 'items?$top=1';
		$response = $this->make_api_request( $endpoint );

		if ( $response ) {
			$count = isset( $response['value'] ) ? count( $response['value'] ) : 0;
			return array(
				'success' => true,
				'message' => 'Connection successful',
				'count' => $count,
			);
		}

		return false;
	}

	/**
	 * Validate API configuration.
	 *
	 * @since 1.0.0
	 * @return array Validation result.
	 */
	public function validate_config() {
		$errors = array();
		$warnings = array();

		if ( empty( $this->api_url ) ) {
			$errors[] = 'API URL is required';
		} elseif ( ! filter_var( $this->api_url, FILTER_VALIDATE_URL ) ) {
			$errors[] = 'API URL is not a valid URL';
		}

		if ( empty( $this->company_id ) ) {
			$errors[] = 'Company ID is required';
		}

		if ( empty( $this->client_id ) ) {
			$errors[] = 'Client ID is required';
		}

		if ( empty( $this->client_secret ) ) {
			$errors[] = 'Client Secret is required';
		}

		return array(
			'valid' => empty( $errors ),
			'errors' => $errors,
			'warnings' => $warnings,
		);
	}

	// =============================================================================
	// UTILITY METHODS
	// =============================================================================

	/**
	 * Get API configuration status.
	 *
	 * @since 1.0.0
	 * @return array Configuration status.
	 */
	public function get_config_status() {
		$validation = $this->validate_config();
		
		return array(
			'configured' => $validation['valid'],
			'api_url' => ! empty( $this->api_url ),
			'company_id' => ! empty( $this->company_id ),
			'client_id' => ! empty( $this->client_id ),
			'client_secret' => ! empty( $this->client_secret ),
			'errors' => $validation['errors'],
			'warnings' => $validation['warnings'],
		);
	}

	/**
	 * Get last sync information.
	 *
	 * @since 1.0.0
	 * @return array Last sync information.
	 */
	public function get_last_sync_info() {
		return array(
			'products' => get_option( 'bc_last_sync', '' ),
			'pricelists' => get_option( 'bc_last_pricelist_sync', '' ),
			'customers' => get_option( 'bc_last_customer_sync', '' ),
		);
	}
}


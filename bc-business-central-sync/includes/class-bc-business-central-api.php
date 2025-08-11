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
	private function make_request( $endpoint, $method = 'GET', $data = null ) {
		$token = $this->get_access_token();
		
		$url = $this->api_url . '/companies(' . $this->company_id . ')/' . $endpoint;
		
		$args = array(
			'method' => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
			),
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

		return json_decode( $body, true );
	}

	/**
	 * Get all products from Business Central.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_products() {
		$endpoint = 'items?$select=id,number,displayName,description,unitPrice,unitCost,inventory,blocked,lastModifiedDateTime';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid products response' );
		}

		return $response['value'];
	}

	/**
	 * Test connection to Business Central.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function test_connection() {
		$endpoint = 'items?$top=1&$select=id,number,displayName';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid test response' );
		}

		// Get total count
		$count_endpoint = 'items?$count=true';
		$count_response = $this->make_request( $count_endpoint );
		
		$total_count = isset( $count_response['@odata.count'] ) ? $count_response['@odata.count'] : 0;

		return array(
			'count' => $total_count,
			'sample' => $response['value']
		);
	}

	/**
	 * Get product details by ID.
	 *
	 * @param string $product_id Product ID.
	 * @return array
	 * @throws Exception
	 */
	public function get_product( $product_id ) {
		$endpoint = 'items(' . $product_id . ')?$select=id,number,displayName,description,unitPrice,unitCost,inventory,blocked,lastModifiedDateTime';
		
		return $this->make_request( $endpoint );
	}

	/**
	 * Get all pricelists from Business Central.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_pricelists() {
		$endpoint = 'salesPriceLists?$select=id,code,name,currencyCode,lastModifiedDateTime';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid pricelists response' );
		}

		return $response['value'];
	}

	/**
	 * Get pricelist lines for a specific pricelist.
	 *
	 * @param string $pricelist_id Pricelist ID.
	 * @return array
	 * @throws Exception
	 */
	public function get_pricelist_lines( $pricelist_id ) {
		$endpoint = 'salesPriceLists(' . $pricelist_id . ')/salesPriceListLines?$select=id,itemId,itemNumber,unitPrice,currencyCode,startingDate,endingDate,minimumQuantity';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid pricelist lines response' );
		}

		return $response['value'];
	}

	/**
	 * Get all pricelist lines with item information.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_all_pricelist_lines() {
		$endpoint = 'salesPriceListLines?$expand=item($select=id,number,displayName)&$select=id,salesPriceListId,itemId,itemNumber,unitPrice,currencyCode,startingDate,endingDate,minimumQuantity';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid pricelist lines response' );
		}

		return $response['value'];
	}

	/**
	 * Get customer company assignments.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_customer_companies() {
		$endpoint = 'customers?$select=id,number,name,priceListId,priceListCode&$filter=priceListId ne null';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid customers response' );
		}

		return $response['value'];
	}

	/**
	 * Get customer by number.
	 *
	 * @param string $customer_number Customer number.
	 * @return array|false
	 * @throws Exception
	 */
	public function get_customer_by_number( $customer_number ) {
		$endpoint = 'customers?$filter=number eq \'' . $customer_number . '\'&$select=id,number,name,priceListId,priceListCode';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) || empty( $response['value'] ) ) {
			return false;
		}

		return $response['value'][0];
	}

	/**
	 * Get companies from Business Central.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_companies() {
		$endpoint = 'companies?$select=id,name,displayName,country,currencyCode,languageId,timeZone';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid companies response' );
		}

		return $response['value'];
	}

	/**
	 * Get customers with company information.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_customers_with_companies() {
		$endpoint = 'customers?$select=id,number,name,companyName,priceListId,priceListCode&$filter=companyName ne null';
		
		$response = $this->make_request( $endpoint );
		
		if ( ! isset( $response['value'] ) ) {
			throw new Exception( 'Invalid customers response' );
		}

		return $response['value'];
	}

}

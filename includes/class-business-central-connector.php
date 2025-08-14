<?php
/**
 * Main Business Central Connector Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Business_Central_Connector {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('bcc_settings', array());
        add_action('wp_ajax_bcc_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_bcc_refresh_connection', array($this, 'refresh_connection'));
        add_action('wp_ajax_bcc_fetch_customers', array($this, 'ajax_fetch_customers'));
        add_action('wp_ajax_bcc_save_customers', array($this, 'ajax_save_customers'));
		add_action('wp_ajax_bcc_fetch_categories', array($this, 'ajax_fetch_categories'));
		add_action('wp_ajax_bcc_save_categories', array($this, 'ajax_save_categories'));
		add_action('wp_ajax_bcc_fetch_items', array($this, 'ajax_fetch_items'));
		add_action('wp_ajax_bcc_save_items', array($this, 'ajax_save_items'));
        // OAuth callback via admin-ajax as per fixed callback URL
        add_action('wp_ajax_bc_oauth_callback', array($this, 'ajax_oauth_callback'));
        add_action('wp_ajax_nopriv_bc_oauth_callback', array($this, 'ajax_oauth_callback'));
    }
    
    /**
     * Test the Business Central connection
     */
    public function test_connection() {
        check_ajax_referer('bcc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = $this->get_settings();
        
        if (empty($settings['tenant_id']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
            wp_send_json_error('Missing required connection parameters');
            return;
        }
        
        try {
            $response = $this->make_api_request('GET', 'companies');
            
            if ($response && isset($response['value'])) {
                $this->update_connection_status('connected');
                wp_send_json_success('Connection successful! Found ' . count($response['value']) . ' companies.');
            } else {
                $this->update_connection_status('failed');
                wp_send_json_error('Connection failed: Invalid response from API');
            }
            
        } catch (Exception $e) {
            $this->update_connection_status('failed');
            wp_send_json_error('Connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Refresh the connection status
     */
    public function refresh_connection() {
        check_ajax_referer('bcc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = $this->get_settings();
        $status = 'disconnected';
        
        if (!empty($settings['tenant_id']) && !empty($settings['client_id']) && !empty($settings['client_secret'])) {
            try {
                $response = $this->make_api_request('GET', 'companies');
                if ($response && isset($response['value'])) {
                    $status = 'connected';
                }
            } catch (Exception $e) {
                $status = 'failed';
            }
        }
        
        $this->update_connection_status($status);
        wp_send_json_success(array('status' => $status));
    }
    
    /**
     * Handle OAuth 2.0 callback (for authorization code flow)
     */
    public function ajax_oauth_callback() {
        $result = 'error';
        if (isset($_GET['code'])) {
            $this->handle_authorization_code(sanitize_text_field($_GET['code']));
            $result = 'success';
        } elseif (isset($_GET['error'])) {
            $this->handle_oauth_error(sanitize_text_field($_GET['error']), isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : '');
            $result = 'error';
        }
        // Redirect back to the settings page with a status
        $redirect = admin_url('admin.php?page=business-central-connector&bcc_oauth=' . $result);
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Handle authorization code from OAuth callback
     */
    private function handle_authorization_code($code) {
        $settings = $this->get_settings();
        
        $token_url = 'https://login.microsoftonline.com/' . $settings['tenant_id'] . '/oauth2/v2.0/token';
        
        $body = array(
            'grant_type' => 'authorization_code',
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'code' => $code,
            'redirect_uri' => $settings['callback_url'],
            'scope' => 'https://api.businesscentral.dynamics.com/.default offline_access'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('OAuth callback failed: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);
        
        if (isset($token_data['access_token'])) {
            // Store the token
            $expires_at = time() + $token_data['expires_in'] - 300;
            $stored_token = array(
                'access_token' => $token_data['access_token'],
                'expires_at' => $expires_at,
                'token_type' => $token_data['token_type']
            );
            if (isset($token_data['refresh_token'])) {
                $stored_token['refresh_token'] = $token_data['refresh_token'];
            }
            
            set_transient('bcc_access_token', $stored_token, $token_data['expires_in'] - 300);
            
            // Update connection status
            $this->update_connection_status('connected');
        }
    }
    
    /**
     * Handle OAuth errors
     */
    private function handle_oauth_error($error, $description) {
        error_log('OAuth error: ' . $error . ' - ' . $description);
        $this->update_connection_status('failed');
    }
    
    /**
     * Get OAuth 2.0 authorization URL
     */
    public function get_authorization_url() {
        $settings = $this->get_settings();
        
        $params = array(
            'client_id' => $settings['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $settings['callback_url'],
            'scope' => 'https://api.businesscentral.dynamics.com/.default offline_access',
            'response_mode' => 'query'
        );
        
        return 'https://login.microsoftonline.com/' . $settings['tenant_id'] . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }
    
    /**
     * Make API request to Business Central
     */
    private function make_api_request($method, $endpoint, $data = null) {
        $settings = $this->get_settings();
        
        $url = $settings['base_url'] . $settings['api_version'] . '/' . $settings['tenant_id'] . '/' . $settings['bc_environment'] . '/api/' . $settings['api_version'] . '/' . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->get_access_token(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );
        
        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 400) {
            throw new Exception('API Error: ' . $status_code . ' - ' . $body);
        }
        
        return json_decode($body, true);
    }

    /**
     * AJAX: Fetch Customers using cURL and return raw response
     */
    public function ajax_fetch_customers() {
        check_ajax_referer('bcc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = $this->get_settings();
        $tenantId = isset($settings['tenant_id']) ? trim($settings['tenant_id']) : '';
        $env = isset($settings['bc_environment']) ? trim($settings['bc_environment']) : '';
        $companyId = isset($settings['company_id']) ? trim($settings['company_id']) : '';
        $apiVersion = isset($settings['api_version']) ? trim($settings['api_version']) : 'v2.0';
        $baseUrl = rtrim($settings['base_url'], '/') . '/';

        if ($tenantId === '' || $env === '' || $companyId === '') {
            wp_send_json_error('Missing tenant, environment, or company ID');
        }

        // Build URL per required structure
        $url = $baseUrl . 'v2.0/' . rawurlencode($tenantId) . '/' . rawurlencode($env) . '/api/' . rawurlencode($apiVersion) . '/companies(' . rawurlencode($companyId) . ')/customers?%24top=10';

        try {
            $token = $this->get_access_token();
        } catch (Exception $e) {
            wp_send_json_error('Auth error: ' . $e->getMessage());
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            wp_send_json_error('cURL error: ' . $err);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            wp_send_json_error('HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode($response, true);
        if ($data === null) {
            wp_send_json_error('Invalid JSON response');
        }
        wp_send_json_success($data);
    }

	/**
	 * AJAX: Fetch Item Categories
	 */
	public function ajax_fetch_categories() {
		check_ajax_referer('bcc_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$settings = $this->get_settings();
		$tenantId = isset($settings['tenant_id']) ? trim($settings['tenant_id']) : '';
		$env = isset($settings['bc_environment']) ? trim($settings['bc_environment']) : '';
		$companyId = isset($settings['company_id']) ? trim($settings['company_id']) : '';
		$apiVersion = isset($settings['api_version']) ? trim($settings['api_version']) : 'v2.0';
		$baseUrl = rtrim($settings['base_url'], '/') . '/';

		if ($tenantId === '' || $env === '' || $companyId === '') {
			wp_send_json_error('Missing tenant, environment, or company ID');
		}

		$url = $baseUrl . 'v2.0/' . rawurlencode($tenantId) . '/' . rawurlencode($env) . '/api/' . rawurlencode($apiVersion) . '/companies(' . rawurlencode($companyId) . ')/itemCategories';

		try {
			$token = $this->get_access_token();
		} catch (Exception $e) {
			wp_send_json_error('Auth error: ' . $e->getMessage());
		}

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				'Accept: application/json',
				'Authorization: Bearer ' . $token
			),
		));

		$response = curl_exec($ch);
		if ($response === false) {
			$err = curl_error($ch);
			curl_close($ch);
			wp_send_json_error('cURL error: ' . $err);
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode >= 400) {
			wp_send_json_error('HTTP ' . $httpCode . ': ' . $response);
		}

		$data = json_decode($response, true);
		if ($data === null) {
			wp_send_json_error('Invalid JSON response');
		}
		wp_send_json_success($data);
	}

	/**
	 * AJAX: Save Item Categories to WooCommerce
	 */
	public function ajax_save_categories() {
		check_ajax_referer('bcc_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
		if ($payload === '') {
			wp_send_json_error('Missing payload');
		}
		$data = json_decode($payload, true);
		if (!is_array($data)) {
			wp_send_json_error('Invalid JSON payload');
		}

		$categories = isset($data['value']) && is_array($data['value']) ? $data['value'] : array();
		$result = array(
			'created' => 0,
			'updated' => 0,
			'errors' => array()
		);

		foreach ($categories as $cat) {
			try {
				$action = $this->upsert_product_category($cat);
				if ($action === 'created') {
					$result['created']++;
				} elseif ($action === 'updated') {
					$result['updated']++;
				}
			} catch (Exception $e) {
				$result['errors'][] = $e->getMessage();
			}
		}

		wp_send_json_success($result);
	}

	/**
	 * Find or create/update a WooCommerce product category from BC itemCategory
	 */
	private function upsert_product_category($bcCategory) {
		$bc_id = isset($bcCategory['id']) ? $bcCategory['id'] : '';
		$code = isset($bcCategory['code']) ? $bcCategory['code'] : '';
		$name = isset($bcCategory['displayName']) && $bcCategory['displayName'] !== '' ? $bcCategory['displayName'] : ($code !== '' ? $code : 'Category');
		$parent_bc_id = isset($bcCategory['parentId']) ? $bcCategory['parentId'] : (isset($bcCategory['parentCategoryId']) ? $bcCategory['parentCategoryId'] : '');
		$etag = isset($bcCategory['@odata.etag']) ? $bcCategory['@odata.etag'] : '';
		$last_modified = isset($bcCategory['lastModifiedDateTime']) ? $bcCategory['lastModifiedDateTime'] : '';

		$term_id = $this->get_term_id_by_bc_category_id($bc_id);
		$parent_term_id = $parent_bc_id ? $this->get_term_id_by_bc_category_id($parent_bc_id) : 0;

		if (!$term_id) {
			$slug = $code !== '' ? sanitize_title($code) : sanitize_title($name);
			$inserted = wp_insert_term($name, 'product_cat', array(
				'slug' => $slug,
				'parent' => $parent_term_id ? intval($parent_term_id) : 0
			));
			if (is_wp_error($inserted)) {
				throw new Exception('Failed to insert category ' . $name . ': ' . $inserted->get_error_message());
			}
			$term_id = isset($inserted['term_id']) ? intval($inserted['term_id']) : 0;
			if ($term_id) {
				update_term_meta($term_id, 'business_central_category_id', $bc_id);
				if ($code !== '') {
					update_term_meta($term_id, 'business_central_category_code', $code);
				}
				if ($etag !== '') {
					update_term_meta($term_id, 'business_central_category_etag', $etag);
				}
				if ($last_modified !== '') {
					update_term_meta($term_id, 'business_central_category_last_modified', $last_modified);
				}
			}
			return 'created';
		}

		$updated = wp_update_term($term_id, 'product_cat', array(
			'name' => $name,
			'parent' => $parent_term_id ? intval($parent_term_id) : 0
		));
		if (is_wp_error($updated)) {
			throw new Exception('Failed to update category ' . $name . ': ' . $updated->get_error_message());
		}
		// Update metas on existing term
		if ($code !== '') {
			update_term_meta($term_id, 'business_central_category_code', $code);
		}
		if ($etag !== '') {
			update_term_meta($term_id, 'business_central_category_etag', $etag);
		}
		if ($last_modified !== '') {
			update_term_meta($term_id, 'business_central_category_last_modified', $last_modified);
		}
		return 'updated';
	}

	private function get_term_id_by_bc_category_id($bc_id) {
		if (!$bc_id) {
			return 0;
		}
		$terms = get_terms(array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => 'business_central_category_id',
					'value' => $bc_id,
					'compare' => '='
				)
			)
		));
		if (is_wp_error($terms) || empty($terms)) {
			return 0;
		}
		return intval($terms[0]);
	}

	/**
	 * AJAX: Fetch Items (Products)
	 */
	public function ajax_fetch_items() {
		check_ajax_referer('bcc_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$settings = $this->get_settings();
		$tenantId = isset($settings['tenant_id']) ? trim($settings['tenant_id']) : '';
		$env = isset($settings['bc_environment']) ? trim($settings['bc_environment']) : '';
		$companyId = isset($settings['company_id']) ? trim($settings['company_id']) : '';
		$apiVersion = isset($settings['api_version']) ? trim($settings['api_version']) : 'v2.0';
		$baseUrl = rtrim($settings['base_url'], '/') . '/';

		if ($tenantId === '' || $env === '' || $companyId === '') {
			wp_send_json_error('Missing tenant, environment, or company ID');
		}

		$url = $baseUrl . 'v2.0/' . rawurlencode($tenantId) . '/' . rawurlencode($env) . '/api/' . rawurlencode($apiVersion) . '/companies(' . rawurlencode($companyId) . ')/items';

		try {
			$token = $this->get_access_token();
		} catch (Exception $e) {
			wp_send_json_error('Auth error: ' . $e->getMessage());
		}

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				'Accept: application/json',
				'Authorization: Bearer ' . $token
			),
		));

		$response = curl_exec($ch);
		if ($response === false) {
			$err = curl_error($ch);
			curl_close($ch);
			wp_send_json_error('cURL error: ' . $err);
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode >= 400) {
			wp_send_json_error('HTTP ' . $httpCode . ': ' . $response);
		}

		$data = json_decode($response, true);
		if ($data === null) {
			wp_send_json_error('Invalid JSON response');
		}
		wp_send_json_success($data);
	}

	/**
	 * AJAX: Save Items to WooCommerce Products
	 */
	public function ajax_save_items() {
		check_ajax_referer('bcc_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
		if ($payload === '') {
			wp_send_json_error('Missing payload');
		}
		$data = json_decode($payload, true);
		if (!is_array($data)) {
			wp_send_json_error('Invalid JSON payload');
		}

		$items = isset($data['value']) && is_array($data['value']) ? $data['value'] : array();
		$result = array(
			'created' => 0,
			'updated' => 0,
			'errors' => array()
		);

		foreach ($items as $item) {
			try {
				$action = $this->upsert_product_from_item($item);
				if ($action === 'created') {
					$result['created']++;
				} elseif ($action === 'updated') {
					$result['updated']++;
				}
			} catch (Exception $e) {
				$result['errors'][] = $e->getMessage();
			}
		}

		wp_send_json_success($result);
	}

	/**
	 * Create or update a WooCommerce product from a BC item payload
	 */
	private function upsert_product_from_item($bcItem) {
		if (!function_exists('wc_get_product_id_by_sku')) {
			throw new Exception('WooCommerce not installed');
		}
		$sku = isset($bcItem['number']) ? (string) $bcItem['number'] : '';
		$title = isset($bcItem['displayName']) && $bcItem['displayName'] !== '' ? $bcItem['displayName'] : ($sku !== '' ? $sku : 'Product');
		$subtitle = isset($bcItem['displayName2']) ? $bcItem['displayName2'] : '';
		$price = isset($bcItem['unitPrice']) ? floatval($bcItem['unitPrice']) : 0.0;
		$stockQty = isset($bcItem['inventory']) ? intval($bcItem['inventory']) : 0;
		$bc_item_id = isset($bcItem['id']) ? $bcItem['id'] : '';
		$item_category_id = isset($bcItem['itemCategoryId']) ? $bcItem['itemCategoryId'] : '';
		$item_category_code = isset($bcItem['itemCategoryCode']) ? $bcItem['itemCategoryCode'] : '';
		$etag = isset($bcItem['@odata.etag']) ? $bcItem['@odata.etag'] : '';
		$last_modified = isset($bcItem['lastModifiedDateTime']) ? $bcItem['lastModifiedDateTime'] : '';
		$price_includes_tax = isset($bcItem['priceIncludesTax']) ? (bool) $bcItem['priceIncludesTax'] : false;
		$unit_cost = isset($bcItem['unitCost']) ? floatval($bcItem['unitCost']) : 0.0;
		$gtin = isset($bcItem['gtin']) ? $bcItem['gtin'] : '';
		$type = isset($bcItem['type']) ? $bcItem['type'] : '';
		$blocked = isset($bcItem['blocked']) ? (bool) $bcItem['blocked'] : false;
		$base_uom_code = isset($bcItem['baseUnitOfMeasureCode']) ? $bcItem['baseUnitOfMeasureCode'] : '';
		$tax_group_code = isset($bcItem['taxGroupCode']) ? $bcItem['taxGroupCode'] : '';
		$general_posting_group = isset($bcItem['generalProductPostingGroupCode']) ? $bcItem['generalProductPostingGroupCode'] : '';
		$inventory_posting_group = isset($bcItem['inventoryPostingGroupCode']) ? $bcItem['inventoryPostingGroupCode'] : '';

		$product_id = 0;
		if ($sku !== '') {
			$product_id = wc_get_product_id_by_sku($sku);
		}
		if (!$product_id && $bc_item_id) {
			$query = new WP_Query(array(
				'post_type' => 'product',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'meta_query' => array(
					array(
						'key' => 'business_central_item_id',
						'value' => $bc_item_id,
						'compare' => '='
					)
				)
			));
			if ($query->have_posts()) {
				$product_id = intval($query->posts[0]);
			}
			wp_reset_postdata();
		}

		$action = 'updated';
		if (!$product_id) {
			$postarr = array(
				'post_title' => $title,
				'post_status' => 'publish',
				'post_type' => 'product'
			);
			$product_id = wp_insert_post($postarr, true);
			if (is_wp_error($product_id)) {
				throw new Exception('Failed to create product: ' . $product_id->get_error_message());
			}
			$action = 'created';
		}

		// Ensure product type simple
		wp_set_object_terms($product_id, 'simple', 'product_type', false);

		// Update meta
		if ($sku !== '') {
			update_post_meta($product_id, '_sku', $sku);
		}
		update_post_meta($product_id, 'business_central_item_id', $bc_item_id);
		update_post_meta($product_id, 'business_central_item_number', $sku);
		if ($etag !== '') {
			update_post_meta($product_id, 'business_central_item_etag', $etag);
		}
		if ($last_modified !== '') {
			update_post_meta($product_id, 'business_central_item_last_modified', $last_modified);
		}
		if ($item_category_code !== '') {
			update_post_meta($product_id, 'business_central_item_category_code', $item_category_code);
		}
		update_post_meta($product_id, 'business_central_item_price_includes_tax', $price_includes_tax ? 'yes' : 'no');
		update_post_meta($product_id, 'business_central_item_unit_cost', $unit_cost);
		if ($gtin !== '') {
			update_post_meta($product_id, 'business_central_item_gtin', $gtin);
		}
		if ($type !== '') {
			update_post_meta($product_id, 'business_central_item_type', $type);
		}
		update_post_meta($product_id, 'business_central_item_blocked', $blocked ? 'yes' : 'no');
		if ($base_uom_code !== '') {
			update_post_meta($product_id, 'business_central_item_base_uom_code', $base_uom_code);
		}
		if ($tax_group_code !== '') {
			update_post_meta($product_id, 'business_central_item_tax_group_code', $tax_group_code);
		}
		if ($general_posting_group !== '') {
			update_post_meta($product_id, 'business_central_item_general_posting_group', $general_posting_group);
		}
		if ($inventory_posting_group !== '') {
			update_post_meta($product_id, 'business_central_item_inventory_posting_group', $inventory_posting_group);
		}
		update_post_meta($product_id, '_regular_price', wc_format_decimal($price));
		update_post_meta($product_id, '_price', wc_format_decimal($price));
		if ($stockQty > 0 && !$blocked) {
			update_post_meta($product_id, '_manage_stock', 'yes');
			update_post_meta($product_id, '_stock', $stockQty);
			update_post_meta($product_id, '_stock_status', 'instock');
		} else {
			update_post_meta($product_id, '_manage_stock', 'no');
			update_post_meta($product_id, '_stock_status', 'outofstock');
		}
		// Respect blocked flag: hide from catalog if blocked
		if ($blocked) {
			update_post_meta($product_id, 'catalog_visibility', 'hidden');
		}

		// Assign category if available
		if ($item_category_id) {
			$term_id = $this->get_term_id_by_bc_category_id($item_category_id);
			if ($term_id) {
				wp_set_object_terms($product_id, array(intval($term_id)), 'product_cat', false);
			}
		}

		// Optionally update title and excerpt
		wp_update_post(array(
			'ID' => $product_id,
			'post_title' => $title,
			'post_excerpt' => $subtitle
		));

		return $action;
	}

    /**
     * AJAX: Save customers to WooCommerce and Dokobit
     */
    public function ajax_save_customers() {
        check_ajax_referer('bcc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
        if ($payload === '') {
            wp_send_json_error('Missing payload');
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            wp_send_json_error('Invalid JSON payload');
        }

        $customers = isset($data['value']) && is_array($data['value']) ? $data['value'] : array();
        $result = array(
            'woocommerce_saved' => 0,
            'dokobit_saved' => 0,
            'errors' => array()
        );

        foreach ($customers as $c) {
            try {
                $this->save_customer_to_woocommerce($c);
                $result['woocommerce_saved']++;
            } catch (Exception $e) {
                $result['errors'][] = 'Woo: ' . $e->getMessage();
            }
            try {
                $this->save_customer_to_dokobit($c);
                $result['dokobit_saved']++;
            } catch (Exception $e) {
                $result['errors'][] = 'Dokobit: ' . $e->getMessage();
            }
            // Upsert into dokobit companies table locally
            try {
                if (class_exists('BCC_Dokobit_Database')) {
                    $this->upsert_local_dokobit_company($c);
                }
            } catch (Exception $e) {
                $result['errors'][] = 'DB: ' . $e->getMessage();
            }
        }

        wp_send_json_success($result);
    }

    private function upsert_local_dokobit_company($c) {
        global $wpdb;
        $table = $wpdb->prefix . 'dokobit_companies';
        $kennitala = isset($c['number']) ? $c['number'] : '';
        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE kennitala = %s LIMIT 1", $kennitala));
        $data = array(
            'company_name' => isset($c['displayName']) ? $c['displayName'] : '',
            'kennitala' => $kennitala,
            'business_central_id' => isset($c['id']) ? $c['id'] : '',
            'address_line1' => isset($c['addressLine1']) ? $c['addressLine1'] : '',
            'address_line2' => isset($c['addressLine2']) ? $c['addressLine2'] : '',
            'city' => isset($c['city']) ? $c['city'] : '',
            'state' => isset($c['state']) ? $c['state'] : '',
            'postal_code' => isset($c['postalCode']) ? $c['postalCode'] : '',
            'country' => isset($c['country']) ? $c['country'] : '',
            'email' => isset($c['email']) ? $c['email'] : '',
            'phone' => isset($c['phoneNumber']) ? $c['phoneNumber'] : '',
            'balance_due' => isset($c['balanceDue']) ? $c['balanceDue'] : 0,
            'credit_limit' => isset($c['creditLimit']) ? $c['creditLimit'] : 0,
        );
        if ($existing_id) {
            $wpdb->update($table, $data, array('id' => $existing_id));
        } else {
            $wpdb->insert($table, $data);
        }
    }

    private function save_customer_to_woocommerce($c) {
        if (!function_exists('wc_create_new_customer')) {
            throw new Exception('WooCommerce not installed');
        }
        $email = isset($c['email']) && $c['email'] !== '' ? $c['email'] : (isset($c['number']) ? strtolower($c['number']) . '@example.invalid' : 'noemail@example.invalid');
        $display = isset($c['displayName']) ? $c['displayName'] : 'Customer';
        $first = $display;
        $last = '';
        if (strpos($display, ' ') !== false) {
            $parts = explode(' ', $display, 2);
            $first = $parts[0];
            $last = $parts[1];
        }

        $existing = get_user_by('email', $email);
        if ($existing) {
            // Update meta
            update_user_meta($existing->ID, 'billing_company', isset($c['displayName']) ? $c['displayName'] : '');
            update_user_meta($existing->ID, 'billing_address_1', isset($c['addressLine1']) ? $c['addressLine1'] : '');
            update_user_meta($existing->ID, 'billing_address_2', isset($c['addressLine2']) ? $c['addressLine2'] : '');
            update_user_meta($existing->ID, 'billing_city', isset($c['city']) ? $c['city'] : '');
            update_user_meta($existing->ID, 'billing_postcode', isset($c['postalCode']) ? $c['postalCode'] : '');
            update_user_meta($existing->ID, 'billing_country', isset($c['country']) ? $c['country'] : '');
            update_user_meta($existing->ID, 'billing_phone', isset($c['phoneNumber']) ? $c['phoneNumber'] : '');
            // Custom fields
            if (isset($c['number'])) {
                update_user_meta($existing->ID, 'kennitala', $c['number']);
                update_user_meta($existing->ID, 'billing_kennitala', $c['number']);
            }
            if (isset($c['id'])) {
                update_user_meta($existing->ID, 'business_central_id', $c['id']);
            }
            if (isset($c['balanceDue'])) {
                update_user_meta($existing->ID, 'balance_due', $c['balanceDue']);
            }
            if (isset($c['creditLimit'])) {
                update_user_meta($existing->ID, 'credit_limit', $c['creditLimit']);
            }
            return;
        }

        $password = wp_generate_password(20, true);
        $user_id = wc_create_new_customer($email, '', $password);
        if (is_wp_error($user_id)) {
            throw new Exception($user_id->get_error_message());
        }
        wp_update_user(array('ID' => $user_id, 'first_name' => $first, 'last_name' => $last, 'display_name' => $display));
        update_user_meta($user_id, 'billing_company', isset($c['displayName']) ? $c['displayName'] : '');
        update_user_meta($user_id, 'billing_address_1', isset($c['addressLine1']) ? $c['addressLine1'] : '');
        update_user_meta($user_id, 'billing_address_2', isset($c['addressLine2']) ? $c['addressLine2'] : '');
        update_user_meta($user_id, 'billing_city', isset($c['city']) ? $c['city'] : '');
        update_user_meta($user_id, 'billing_postcode', isset($c['postalCode']) ? $c['postalCode'] : '');
        update_user_meta($user_id, 'billing_country', isset($c['country']) ? $c['country'] : '');
        update_user_meta($user_id, 'billing_phone', isset($c['phoneNumber']) ? $c['phoneNumber'] : '');
        // Custom fields
        if (isset($c['number'])) {
            update_user_meta($user_id, 'kennitala', $c['number']);
            update_user_meta($user_id, 'billing_kennitala', $c['number']);
        }
        if (isset($c['id'])) {
            update_user_meta($user_id, 'business_central_id', $c['id']);
        }
        if (isset($c['balanceDue'])) {
            update_user_meta($user_id, 'balance_due', $c['balanceDue']);
        }
        if (isset($c['creditLimit'])) {
            update_user_meta($user_id, 'credit_limit', $c['creditLimit']);
        }
    }

    private function save_customer_to_dokobit($c) {
        $settings = $this->get_settings();
        $base = isset($settings['dokobit_api_base']) ? rtrim($settings['dokobit_api_base'], '/') : '';
        $key = isset($settings['dokobit_api_key']) ? $settings['dokobit_api_key'] : '';
        if ($base === '' || $key === '') {
            throw new Exception('Dokobit settings missing');
        }

        $payload = array(
            'name' => isset($c['displayName']) ? $c['displayName'] : '',
            'registration_number' => isset($c['number']) ? $c['number'] : '',
            'address' => array(
                'street' => isset($c['addressLine1']) ? $c['addressLine1'] : '',
                'city' => isset($c['city']) ? $c['city'] : '',
                'postal_code' => isset($c['postalCode']) ? $c['postalCode'] : '',
                'country' => isset($c['country']) ? $c['country'] : ''
            ),
            'email' => isset($c['email']) ? $c['email'] : '',
            'phone' => isset($c['phoneNumber']) ? $c['phoneNumber'] : '',
            'metadata' => array(
                'kennitala' => isset($c['number']) ? $c['number'] : '',
                'business_central_id' => isset($c['id']) ? $c['id'] : '',
                'balance_due' => isset($c['balanceDue']) ? $c['balanceDue'] : '',
                'credit_limit' => isset($c['creditLimit']) ? $c['creditLimit'] : '',
                'address_line_2' => isset($c['addressLine2']) ? $c['addressLine2'] : '',
                'state' => isset($c['state']) ? $c['state'] : ''
            )
        );

        $url = $base . '/api/companies';
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-KEY' => $key
            ),
            'body' => wp_json_encode($payload)
        );
        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            throw new Exception($resp->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 400) {
            throw new Exception('Dokobit HTTP ' . $code . ': ' . wp_remote_retrieve_body($resp));
        }
    }
    
    /**
     * Get access token for Business Central API using OAuth 2.0
     */
    private function get_access_token() {
        $settings = $this->get_settings();
        
        // Check if we have a valid token stored
        $token_data = get_transient('bcc_access_token');
        if ($token_data && isset($token_data['expires_at']) && $token_data['expires_at'] > time()) {
            return $token_data['access_token'];
        }
        // If expired but we have a refresh token, try to refresh using it
        if ($token_data && isset($token_data['refresh_token']) && !empty($token_data['refresh_token'])) {
            try {
                return $this->refresh_access_token_with_refresh_token($token_data['refresh_token']);
            } catch (Exception $e) {
                // Fall back to client credentials below
            }
        }
        // Fallback to client credentials flow
        return $this->refresh_access_token();
    }
    
    /**
     * Refresh access token using OAuth 2.0 client credentials flow
     */
    private function refresh_access_token() {
        $settings = $this->get_settings();
        
        if (empty($settings['tenant_id']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
            throw new Exception('Missing OAuth 2.0 credentials');
        }
        
        $token_url = 'https://login.microsoftonline.com/' . $settings['tenant_id'] . '/oauth2/v2.0/token';
        
        $body = array(
            'grant_type' => 'client_credentials',
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'scope' => 'https://api.businesscentral.dynamics.com/.default'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to get access token: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            throw new Exception('Token request failed: ' . $status_code . ' - ' . $body);
        }
        
        $token_data = json_decode($body, true);
        
        if (!isset($token_data['access_token'])) {
            throw new Exception('Invalid token response from Microsoft');
        }
        
        // Store token with expiration (subtract 5 minutes for safety)
        $expires_at = time() + $token_data['expires_in'] - 300;
        $stored_token = array(
            'access_token' => $token_data['access_token'],
            'expires_at' => $expires_at,
            'token_type' => $token_data['token_type']
        );
        
        set_transient('bcc_access_token', $stored_token, $token_data['expires_in'] - 300);
        
        return $token_data['access_token'];
    }

    /**
     * Refresh access token using a refresh_token (authorization code flow)
     */
    private function refresh_access_token_with_refresh_token($refresh_token) {
        $settings = $this->get_settings();

        if (empty($settings['tenant_id']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
            throw new Exception('Missing OAuth 2.0 credentials');
        }

        $token_url = 'https://login.microsoftonline.com/' . $settings['tenant_id'] . '/oauth2/v2.0/token';

        $body = array(
            'grant_type' => 'refresh_token',
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'refresh_token' => $refresh_token,
            'redirect_uri' => $settings['callback_url']
        );

        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            throw new Exception('Failed to refresh access token: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            throw new Exception('Refresh token request failed: ' . $status_code . ' - ' . $body);
        }

        $token_data = json_decode($body, true);

        if (!isset($token_data['access_token'])) {
            throw new Exception('Invalid refresh token response from Microsoft');
        }

        $expires_at = time() + $token_data['expires_in'] - 300;
        $stored_token = array(
            'access_token' => $token_data['access_token'],
            'expires_at' => $expires_at,
            'token_type' => $token_data['token_type']
        );

        if (isset($token_data['refresh_token']) && !empty($token_data['refresh_token'])) {
            $stored_token['refresh_token'] = $token_data['refresh_token'];
        } else {
            $stored_token['refresh_token'] = $refresh_token; // keep previous one
        }

        set_transient('bcc_access_token', $stored_token, $token_data['expires_in'] - 300);

        return $token_data['access_token'];
    }
    
    /**
     * Get plugin settings
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Update connection status
     */
    private function update_connection_status($status) {
        $this->settings['connection_status'] = $status;
        update_option('bcc_settings', $this->settings);
    }
}

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
        // Product sync AJAX handlers
        add_action('wp_ajax_bcc_fetch_items', array($this, 'ajax_fetch_items'));
        add_action('wp_ajax_bcc_save_items', array($this, 'ajax_save_items'));
        add_action('wp_ajax_bcc_enhanced_product_sync', array($this, 'ajax_enhanced_product_sync'));
        add_action('wp_ajax_bcc_get_sync_status', array($this, 'ajax_get_sync_status'));
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
        }
    }

    /**
     * Handle OAuth callback from Azure AD
     */
    public function handle_oauth_callback() {
        // Check if we have an authorization code
        if (!isset($_GET['code'])) {
            wp_die('Authorization code not received');
        }

        $code = sanitize_text_field($_GET['code']);
        $settings = $this->get_settings();
        
        if (empty($settings['tenant_id']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
            wp_die('Missing OAuth configuration');
        }

        try {
            // Exchange authorization code for access token
            $token_response = wp_remote_post("https://login.microsoftonline.com/{$settings['tenant_id']}/oauth2/v2.0/token", [
                'body' => [
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $settings['callback_url'] ?? admin_url('admin-ajax.php?action=bc_oauth_callback'),
                    'scope' => 'https://api.businesscentral.dynamics.com/.default'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($token_response)) {
                throw new Exception('Token request failed: ' . $token_response->get_error_message());
            }

            $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
            
            if (empty($token_data['access_token'])) {
                throw new Exception('No access token received: ' . wp_remote_retrieve_body($token_response));
            }

            // Store the access token
            $settings['access_token'] = $token_data['access_token'];
            $settings['token_expires'] = time() + ($token_data['expires_in'] ?? 3600);
            $settings['connection_status'] = 'connected';
            
            update_option('bcc_settings', $settings);

            // Clear any cached tokens
            delete_transient('bcwoo_token');

            // Redirect back to admin with success message
            wp_redirect(admin_url('admin.php?page=business-central-connector&bcc_oauth=success'));
            exit;

                } catch (Exception $e) {
            // Log the error
            error_log('[BCC] OAuth callback failed: ' . $e->getMessage());
            
            // Redirect back to admin with error message
            wp_redirect(admin_url('admin.php?page=business-central-connector&bcc_oauth=error'));
            exit;
        }
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
        $url = $baseUrl . 'v2.0/' . rawurlencode($tenantId) . '/' . rawurlencode($env) . '/api/' . rawurlencode($apiVersion) . '/companies(' . rawurlencode($companyId) . ')/customers';

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

		try {
			$settings = $this->get_settings();
			$company_id = $settings['company_id'];
			
			if (empty($company_id)) {
				wp_send_json_error('Missing company ID');
			}

			// Use BCWoo_Client for consistent API handling
			$bc_client = new BCWoo_Client();
			
			// Test connection first
			$bc_client->test_connection();
			
			// Fetch categories
			$data = $bc_client->get_item_categories($company_id);
			
			wp_send_json_success($data);
			
		} catch (Exception $e) {
			wp_send_json_error('Failed to fetch categories: ' . $e->getMessage());
		}
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

        try {
            $settings = $this->get_settings();
            $company_id = $settings['company_id'];
            
            if (empty($company_id)) {
                wp_send_json_error('Missing company ID');
            }

            // Use BCWoo_Client for consistent API handling
            $bc_client = new BCWoo_Client();
            
            // Test connection first
            $bc_client->test_connection();
            
            // Fetch items with picture expansion
            $data = $bc_client->list_items($company_id, null, 100);
            
            // Log information about fetched items and image data
            $items_with_images = 0;
            $total_items = 0;
            if (isset($data['value']) && is_array($data['value'])) {
                $total_items = count($data['value']);
                foreach ($data['value'] as $item) {
                    if (isset($item['picture']) && !empty($item['picture'])) {
                        $items_with_images++;
                    }
                }
            }
            error_log('[BC Fetch Items] Fetched ' . $total_items . ' items, ' . $items_with_images . ' with image data');
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to fetch items: ' . $e->getMessage());
        }
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

		$total_items = count($items);
		$processed_items = 0;
		$items_with_images = 0;
		
		error_log('[BC Bulk Sync] ===== Starting bulk sync of ' . $total_items . ' items =====');
		
		foreach ($items as $item) {
			$processed_items++;
			$item_number = isset($item['number']) ? $item['number'] : 'Unknown';
			$has_image = isset($item['picture']) && !empty($item['picture']);
			
			if ($has_image) {
				$items_with_images++;
			}
			
			error_log('[BC Bulk Sync] Processing item ' . $processed_items . '/' . $total_items . ' (' . $item_number . ') - Has image: ' . ($has_image ? 'Yes' : 'No'));
			
			try {
				$action = $this->upsert_product_from_item($item);
				if ($action === 'created') {
					$result['created']++;
					error_log('[BC Bulk Sync] ✓ Created product: ' . $item_number);
				} elseif ($action === 'updated') {
					$result['updated']++;
					error_log('[BC Bulk Sync] ✓ Updated product: ' . $item_number);
				}
			} catch (Exception $e) {
				$error_msg = $e->getMessage();
				$result['errors'][] = $error_msg;
				error_log('[BC Bulk Sync] ✗ ERROR processing item ' . $item_number . ': ' . $error_msg);
			}
		}
		
		error_log('[BC Bulk Sync] ===== Bulk sync completed =====');
		error_log('[BC Bulk Sync] Total items: ' . $total_items);
		error_log('[BC Bulk Sync] Items with images: ' . $items_with_images);
		error_log('[BC Bulk Sync] Created: ' . $result['created']);
		error_log('[BC Bulk Sync] Updated: ' . $result['updated']);
		error_log('[BC Bulk Sync] Errors: ' . count($result['errors']));

		wp_send_json_success($result);
	}

	/**
	 * Process product image data from Business Central
	 */
	private function process_product_image($product_id, $picture_data, $bc_item_data) {
		error_log('[BC Product Sync] ===== Starting image processing for product ' . $product_id . ' =====');
		
		// Get access token for image download
		$token = $this->get_access_token();
		if (!$token) {
			error_log('[BC Product Sync] ERROR: Missing access token for image processing');
			return;
		}
		error_log('[BC Product Sync] ✓ Access token retrieved successfully');

		// Extract picture URL from the picture data
		$picture_url = $this->resolve_picture_url($picture_data, $bc_item_data, $product_id);
		
		if (!$picture_url) {
			error_log('[BC Product Sync] ERROR: Could not resolve picture URL for product ' . $product_id);
			return;
		}

		// Download and attach the image
		try {
			error_log('[BC Product Sync] Starting image download process...');
			$attachment_id = $this->download_and_attach_image($picture_url, $token, $product_id);
			
			if ($attachment_id && !is_wp_error($attachment_id)) {
				$this->set_product_featured_image($product_id, $attachment_id);
			} else {
				error_log('[BC Product Sync] ERROR: Failed to create attachment for product ' . $product_id);
			}
		} catch (Exception $e) {
			error_log('[BC Product Sync] ERROR: Failed to process image for product ' . $product_id . ': ' . $e->getMessage());
		}
		
		error_log('[BC Product Sync] ===== Completed image processing for product ' . $product_id . ' =====');
	}

	/**
	 * Resolve picture URL from Business Central picture data
	 */
	private function resolve_picture_url($picture_data, $bc_item_data, $product_id) {
		error_log('[BC URL Resolution] Starting URL resolution for product ' . $product_id);
		error_log('[BC URL Resolution] Picture data structure: ' . json_encode($picture_data));
		
		$picture_url = null;
		
		// Handle different picture data structures
		if (is_array($picture_data) && isset($picture_data[0])) {
			error_log('[BC URL Resolution] Processing collection-based picture data');
			// Picture is a collection (v22+)
			if (isset($picture_data[0]['content@odata.mediaReadLink'])) {
				$picture_url = $picture_data[0]['content@odata.mediaReadLink'];
				error_log('[BC URL Resolution] ✓ Found content@odata.mediaReadLink in collection: ' . $picture_url);
			} elseif (isset($picture_data[0]['pictureContent@odata.mediaReadLink'])) {
				// Business Central v22+ with pictureContent structure
				$picture_url = $picture_data[0]['pictureContent@odata.mediaReadLink'];
				error_log('[BC URL Resolution] ✓ Found pictureContent@odata.mediaReadLink in collection: ' . $picture_url);
			} elseif (isset($picture_data[0]['id'])) {
				// Build content URL from picture ID
				$settings = $this->get_settings();
				$base_url = rtrim($settings['base_url'], '/') . '/v2.0/' . $settings['tenant_id'] . '/' . $settings['bc_environment'] . '/api/' . $settings['api_version'];
				$picture_url = $base_url . '/companies(' . $settings['company_id'] . ')/items(' . $bc_item_data['id'] . ')/picture(' . $picture_data[0]['id'] . ')/content';
				error_log('[BC URL Resolution] ✓ Built content URL from picture ID: ' . $picture_url);
			}
		} elseif (is_array($picture_data) && isset($picture_data['content@odata.mediaReadLink'])) {
			error_log('[BC URL Resolution] Processing single picture with content@odata.mediaReadLink');
			// Picture is a single navigation property
			$picture_url = $picture_data['content@odata.mediaReadLink'];
			error_log('[BC URL Resolution] ✓ Found content@odata.mediaReadLink in single picture: ' . $picture_url);
		} elseif (is_array($picture_data) && isset($picture_data['pictureContent@odata.mediaReadLink'])) {
			error_log('[BC URL Resolution] Processing single picture with pictureContent@odata.mediaReadLink');
			// Business Central v22+ with pictureContent structure (single picture)
			$picture_url = $picture_data['pictureContent@odata.mediaReadLink'];
			error_log('[BC URL Resolution] ✓ Found pictureContent@odata.mediaReadLink in single picture: ' . $picture_url);
		}

		if ($picture_url) {
			error_log('[BC URL Resolution] ✓ Successfully resolved picture URL: ' . $picture_url);
		} else {
			error_log('[BC URL Resolution] ✗ Failed to resolve picture URL from data structure');
		}
		
		return $picture_url;
	}

	/**
	 * Set product featured image if different from current
	 */
	private function set_product_featured_image($product_id, $attachment_id) {
		error_log('[BC Featured Image] Setting featured image for product ' . $product_id . ' (attachment ID: ' . $attachment_id . ')');
		
		// Get current featured image
		$current_thumb = get_post_thumbnail_id($product_id);
		error_log('[BC Featured Image] Current featured image ID: ' . ($current_thumb ?: 'none'));
		
		if ((int)$current_thumb !== (int)$attachment_id) {
			// Set new featured image
			$result = set_post_thumbnail($product_id, $attachment_id);
			if ($result) {
				error_log('[BC Featured Image] ✓ Successfully set new featured image for product ' . $product_id . ' (attachment ID: ' . $attachment_id . ')');
			} else {
				error_log('[BC Featured Image] ✗ Failed to set featured image for product ' . $product_id);
			}
		} else {
			error_log('[BC Featured Image] ✓ Image already set as featured image for product ' . $product_id);
		}
	}

	/**
	 * Download image from Business Central and attach to product
	 */
	private function download_and_attach_image($image_url, $token, $product_id) {
		error_log('[BC Image Download] ===== Starting image download for product ' . $product_id . ' =====');
		error_log('[BC Image Download] Download URL: ' . $image_url);
		error_log('[BC Image Download] Token length: ' . strlen($token) . ' characters');
		
		// Download image with authentication and increased timeout
		$response = wp_remote_get($image_url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept' => 'image/*'
			),
			'timeout' => 120, // Increased timeout to 2 minutes
			'stream' => false,
			'redirection' => 5
		));

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			error_log('[BC Image Download] ✗ WP Error during download: ' . $error_message);
			throw new Exception('Failed to download image: ' . $error_message);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$response_headers = wp_remote_retrieve_headers($response);
		error_log('[BC Image Download] HTTP response code: ' . $status_code);
		error_log('[BC Image Download] Response headers: ' . json_encode($response_headers));
		
		if ($status_code !== 200) {
			$response_body = wp_remote_retrieve_body($response);
			error_log('[BC Image Download] ✗ HTTP error response body: ' . $response_body);
			throw new Exception('Image download failed with HTTP ' . $status_code . ' - ' . $response_body);
		}

		$image_data = wp_remote_retrieve_body($response);
		$content_type = wp_remote_retrieve_header($response, 'content-type');
		$content_length = wp_remote_retrieve_header($response, 'content-length');
		
		error_log('[BC Image Download] ✓ Download successful');
		error_log('[BC Image Download] Content type: ' . $content_type);
		error_log('[BC Image Download] Content length header: ' . ($content_length ?: 'not set'));
		error_log('[BC Image Download] Actual image size: ' . strlen($image_data) . ' bytes');
		
		// Validate image data
		if (empty($image_data)) {
			error_log('[BC Image Download] ✗ ERROR: Downloaded image data is empty');
			throw new Exception('Downloaded image data is empty');
		}
		
		// Determine file extension from content type
		$extension = $this->get_extension_from_mime_type($content_type);
		error_log('[BC Image Download] Determined file extension: ' . $extension);
		
		// Create temporary file
		$temp_file = wp_tempnam('bc_image_' . $product_id . '.' . $extension);
		if (!$temp_file) {
			error_log('[BC Image Download] ✗ ERROR: Failed to create temporary file');
			throw new Exception('Failed to create temporary file');
		}
		error_log('[BC Image Download] ✓ Temporary file created: ' . $temp_file);
		
		// Write image data to temp file
		$bytes_written = file_put_contents($temp_file, $image_data);
		if ($bytes_written === false) {
			error_log('[BC Image Download] ✗ ERROR: Failed to write image data to temp file');
			@unlink($temp_file);
			throw new Exception('Failed to write image data to temporary file');
		}
		error_log('[BC Image Download] ✓ Image data written to temp file: ' . $bytes_written . ' bytes');
		
		// Verify temp file
		$temp_file_size = filesize($temp_file);
		error_log('[BC Image Download] Temp file size: ' . $temp_file_size . ' bytes');
		
		if ($temp_file_size !== strlen($image_data)) {
			error_log('[BC Image Download] ⚠️ WARNING: Temp file size mismatch. Expected: ' . strlen($image_data) . ', Actual: ' . $temp_file_size);
		}
		
		// Prepare file array for WordPress media handling
		$file_array = array(
			'name' => 'bc-product-image-' . $product_id . '.' . $extension,
			'type' => $content_type ?: 'image/jpeg',
			'tmp_name' => $temp_file,
			'error' => 0,
			'size' => $temp_file_size
		);
		error_log('[BC Image Download] File array prepared: ' . json_encode($file_array));
		
		// Handle the file upload via WordPress sideload
		error_log('[BC Image Download] Starting WordPress sideload process...');
		$overrides = array('test_form' => false);
		$results = wp_handle_sideload($file_array, $overrides);
		
		if (isset($results['error'])) {
			error_log('[BC Image Download] ✗ ERROR: WordPress sideload failed: ' . $results['error']);
			@unlink($temp_file);
			throw new Exception('File sideload failed: ' . $results['error']);
		}
		
		error_log('[BC Image Download] ✓ WordPress sideload successful');
		error_log('[BC Image Download] Sideload results: ' . json_encode($results));
		
		// Create attachment post
		$attachment = array(
			'post_mime_type' => $results['type'],
			'post_title' => get_the_title($product_id),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		
		error_log('[BC Image Download] Creating attachment post...');
		error_log('[BC Image Download] Attachment data: ' . json_encode($attachment));
		error_log('[BC Image Download] File path: ' . $results['file']);
		
		$attachment_id = wp_insert_attachment($attachment, $results['file'], $product_id);
		
		if (is_wp_error($attachment_id)) {
			$error_message = $attachment_id->get_error_message();
			error_log('[BC Image Download] ✗ ERROR: Failed to create attachment: ' . $error_message);
			throw new Exception('Failed to create attachment: ' . $error_message);
		}
		
		error_log('[BC Image Download] ✓ Attachment post created with ID: ' . $attachment_id);
		
		// Generate attachment metadata
		error_log('[BC Image Download] Generating attachment metadata...');
		if (!function_exists('wp_generate_attachment_metadata')) {
			error_log('[BC Image Download] Loading image.php for metadata generation');
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		
		$attachment_data = wp_generate_attachment_metadata($attachment_id, $results['file']);
		if (is_wp_error($attachment_data)) {
			error_log('[BC Image Download] ⚠️ WARNING: Metadata generation returned error: ' . $attachment_data->get_error_message());
		} else {
			error_log('[BC Image Download] ✓ Metadata generated successfully: ' . json_encode($attachment_data));
		}
		
		$metadata_result = wp_update_attachment_metadata($attachment_id, $attachment_data);
		if (is_wp_error($metadata_result)) {
			error_log('[BC Image Download] ⚠️ WARNING: Failed to update attachment metadata: ' . $metadata_result->get_error_message());
		} else {
			error_log('[BC Image Download] ✓ Attachment metadata updated successfully');
		}
		
		error_log('[BC Image Download] ===== Image download and attachment completed for product ' . $product_id . ' =====');
		return $attachment_id;
	}

	/**
	 * Get file extension from MIME type
	 */
	private function get_extension_from_mime_type($mime_type) {
		$mime_map = array(
			'image/jpeg' => 'jpg',
			'image/jpg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/bmp' => 'bmp',
			'image/tiff' => 'tif'
		);
		
		return isset($mime_map[$mime_type]) ? $mime_map[$mime_type] : 'jpg';
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

		// Process image data if available
		if (isset($bcItem['picture']) && !empty($bcItem['picture'])) {
			$start_time = microtime(true);
			error_log('[BC Product Sync] Processing image data for product ' . $product_id . ' with picture data: ' . json_encode($bcItem['picture']));
			$this->process_product_image($product_id, $bcItem['picture'], $bcItem);
			$end_time = microtime(true);
			$processing_time = round(($end_time - $start_time) * 1000, 2);
			error_log('[BC Product Sync] Image processing completed for product ' . $product_id . ' in ' . $processing_time . 'ms');
		} else {
			error_log('[BC Product Sync] No image data found for product ' . $product_id);
		}

		// Trigger action for product sync
		do_action('bcc_after_product_sync', $product_id, $bcItem);

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

    /**
     * Enhanced Product Sync with MVP Scope
     * Syncs products from Business Central to WooCommerce with incremental support
     */
    public function ajax_enhanced_product_sync() {
        check_ajax_referer('bcc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'full';
        $last_sync_timestamp = isset($_POST['last_sync_timestamp']) ? sanitize_text_field($_POST['last_sync_timestamp']) : '';
        
        try {
            $result = $this->perform_enhanced_product_sync($sync_type, $last_sync_timestamp);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Perform enhanced product sync with MVP scope
     */
    private function perform_enhanced_product_sync($sync_type = 'full', $last_sync_timestamp = '') {
        try {
            // Use the clean BCWoo_Sync class
            $results = BCWoo_Sync::sync_items_with_results($sync_type === 'full');
            
            // Update the last sync timestamp for consistency
            if (!empty($results['sync_timestamp'])) {
                update_option('bcc_last_product_sync_timestamp', $results['sync_timestamp']);
            }
            
            return $results;
            
        } catch (Exception $e) {
            throw new Exception('Enhanced sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Upsert product with exact MVP scope mapping
     */
    private function upsert_product_with_mvp_scope($bc_item, $bc_client) {
        // MVP Scope Mapping:
        // Item number → SKU
        // Display name → Product name  
        // Description → Short/long description
        // Unit price → Regular price
        // Inventory → manage_stock & stock_quantity
        // Item category → Woo category
        // Images (primary + gallery)
        
        $sku = isset($bc_item['number']) ? (string) $bc_item['number'] : '';
        $product_name = isset($bc_item['displayName']) && $bc_item['displayName'] !== '' ? $bc_item['displayName'] : ($sku !== '' ? $sku : 'Product');
        $description = isset($bc_item['description']) ? $bc_item['description'] : '';
        $unit_price = isset($bc_item['unitPrice']) ? floatval($bc_item['unitPrice']) : 0.0;
        $inventory = isset($bc_item['inventory']) ? intval($bc_item['inventory']) : 0;
        $bc_item_id = isset($bc_item['id']) ? $bc_item['id'] : '';
        $item_category_id = isset($bc_item['itemCategoryId']) ? $bc_item['itemCategoryId'] : '';
        $last_modified = isset($bc_item['lastModifiedDateTime']) ? $bc_item['lastModifiedDateTime'] : '';
        $etag = isset($bc_item['@odata.etag']) ? $bc_item['@odata.etag'] : '';
        $blocked = isset($bc_item['blocked']) ? (bool) $bc_item['blocked'] : false;
        
        // Find existing product by SKU or BC ID
        $product_id = 0;
        if ($sku !== '') {
            $product_id = wc_get_product_id_by_sku($sku);
        }
        if (!$product_id && $bc_item_id) {
            $product_id = $this->get_product_id_by_bc_id($bc_item_id);
        }
        
        $action = 'updated';
        if (!$product_id) {
            // Create new product
            $postarr = array(
                'post_title' => $product_name,
                'post_content' => $description,
                'post_excerpt' => $this->truncate_description($description, 150), // Short description
                'post_status' => $blocked ? 'draft' : 'publish',
                'post_type' => 'product'
            );
            
            $product_id = wp_insert_post($postarr, true);
            if (is_wp_error($product_id)) {
                throw new Exception('Failed to create product: ' . $product_id->get_error_message());
            }
            $action = 'created';
        }
        
        // Ensure product type is simple
        wp_set_object_terms($product_id, 'simple', 'product_type', false);
        
        // Update core product data according to MVP scope
        $this->update_product_core_data($product_id, array(
            'sku' => $sku,
            'name' => $product_name,
            'description' => $description,
            'price' => $unit_price,
            'inventory' => $inventory,
            'blocked' => $blocked
        ));
        
        // Update Business Central metadata
        $this->update_product_bc_metadata($product_id, array(
            'bc_item_id' => $bc_item_id,
            'bc_item_number' => $sku,
            'bc_item_etag' => $etag,
            'bc_item_last_modified' => $last_modified,
            'bc_item_category_id' => $item_category_id
        ));
        
        // Assign category if available
        if ($item_category_id) {
            $this->assign_product_category($product_id, $item_category_id);
        }
        
        // Process images according to MVP scope
        if (isset($bc_item['picture']) && !empty($bc_item['picture'])) {
            $this->process_product_images_mvp($product_id, $bc_item['picture'], $bc_item, $bc_client);
        }
        
        // Trigger action for product sync
        do_action('bcc_after_product_sync_mvp', $product_id, $bc_item);
        
        return $action;
    }
    
    /**
     * Update core product data according to MVP scope
     */
    private function update_product_core_data($product_id, $data) {
        // SKU → SKU
        if (!empty($data['sku'])) {
            update_post_meta($product_id, '_sku', $data['sku']);
        }
        
        // Product name → Post title
        if (!empty($data['name'])) {
            wp_update_post(array(
                'ID' => $product_id,
                'post_title' => $data['name']
            ));
        }
        
        // Description → Post content and excerpt
        if (!empty($data['description'])) {
            wp_update_post(array(
                'ID' => $product_id,
                'post_content' => $data['description'],
                'post_excerpt' => $this->truncate_description($data['description'], 150)
            ));
        }
        
        // Unit price → Regular price
        if (isset($data['price']) && $data['price'] > 0) {
            update_post_meta($product_id, '_regular_price', wc_format_decimal($data['price']));
            update_post_meta($product_id, '_price', wc_format_decimal($data['price']));
        }
        
        // Inventory → manage_stock & stock_quantity
        if (isset($data['inventory'])) {
            if ($data['inventory'] > 0 && !$data['blocked']) {
                update_post_meta($product_id, '_manage_stock', 'yes');
                update_post_meta($product_id, '_stock', $data['inventory']);
                update_post_meta($product_id, '_stock_status', 'instock');
            } else {
                update_post_meta($product_id, '_manage_stock', 'no');
                update_post_meta($product_id, '_stock_status', 'outofstock');
            }
        }
        
        // Handle blocked status
        if ($data['blocked']) {
            update_post_meta($product_id, 'catalog_visibility', 'hidden');
            wp_update_post(array(
                'ID' => $product_id,
                'post_status' => 'draft'
            ));
        }
    }
    
    /**
     * Update Business Central metadata
     */
    private function update_product_bc_metadata($product_id, $metadata) {
        foreach ($metadata as $key => $value) {
            if ($value !== '') {
                update_post_meta($product_id, 'business_central_' . $key, $value);
            }
        }
    }
    
    /**
     * Assign product category from Business Central
     */
    private function assign_product_category($product_id, $bc_category_id) {
        $term_id = $this->get_term_id_by_bc_category_id($bc_category_id);
        if ($term_id) {
            wp_set_object_terms($product_id, array(intval($term_id)), 'product_cat', false);
        }
    }
    
    /**
     * Process product images according to MVP scope
     */
    private function process_product_images_mvp($product_id, $picture_data, $bc_item, $bc_client) {
        if (empty($picture_data)) {
            return;
        }
        
        try {
            // Use BCWoo_Client to download the image
            $media_response = $bc_client->download_picture_stream($picture_data);
            
            if (!$media_response) {
                error_log('[BC MVP Image Sync] ERROR: Could not resolve picture data for product ' . $product_id);
                return;
            }
            
            // Process the downloaded image
            $attachment_id = $this->process_downloaded_image($media_response, $product_id);
            
            if ($attachment_id && !is_wp_error($attachment_id)) {
                // Set as featured image
                $this->set_product_featured_image($product_id, $attachment_id);
                
                // Add to product gallery
                $this->add_to_product_gallery($product_id, $attachment_id);
                
                error_log('[BC MVP Image Sync] ✓ Successfully processed image for product ' . $product_id);
            }
        } catch (Exception $e) {
            error_log('[BC MVP Image Sync] ERROR: Failed to process image for product ' . $product_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Process downloaded image from BCWoo_Client response
     */
    private function process_downloaded_image($media_response, $product_id) {
        $status_code = wp_remote_retrieve_response_code($media_response);
        if ($status_code !== 200) {
            throw new Exception('Image download failed with HTTP ' . $status_code);
        }
        
        $image_data = wp_remote_retrieve_body($media_response);
        $content_type = wp_remote_retrieve_header($media_response, 'content-type');
        
        if (empty($image_data)) {
            throw new Exception('Downloaded image data is empty');
        }
        
        // Determine file extension
        $extension = $this->get_extension_from_mime_type($content_type);
        
        // Create temporary file
        $temp_file = wp_tempnam('bc_mvp_image_' . $product_id . '.' . $extension);
        if (!$temp_file) {
            throw new Exception('Failed to create temporary file');
        }
        
        // Write image data to temp file
        $bytes_written = file_put_contents($temp_file, $image_data);
        if ($bytes_written === false) {
            @unlink($temp_file);
            throw new Exception('Failed to write image data to temporary file');
        }
        
        // Prepare file array for WordPress media handling
        $file_array = array(
            'name' => 'bc-mvp-product-' . $product_id . '.' . $extension,
            'type' => $content_type ?: 'image/jpeg',
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => $bytes_written
        );
        
        // Handle the file upload via WordPress sideload
        $overrides = array('test_form' => false);
        $results = wp_handle_sideload($file_array, $overrides);
        
        if (isset($results['error'])) {
            @unlink($temp_file);
            throw new Exception('File sideload failed: ' . $results['error']);
        }
        
        // Create attachment post
        $attachment = array(
            'post_mime_type' => $results['type'],
            'post_title' => get_the_title($product_id),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $results['file'], $product_id);
        
        if (is_wp_error($attachment_id)) {
            throw new Exception('Failed to create attachment: ' . $attachment_id->get_error_message());
        }
        
        // Generate attachment metadata
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $results['file']);
        if (!is_wp_error($attachment_data)) {
            wp_update_attachment_metadata($attachment_id, $attachment_data);
        }
        
        return $attachment_id;
    }
    
    /**
     * Add image to product gallery
     */
    private function add_to_product_gallery($product_id, $attachment_id) {
        $current_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $gallery_ids = array();
        
        if ($current_gallery) {
            $gallery_ids = explode(',', $current_gallery);
        }
        
        // Add new image if not already in gallery
        if (!in_array($attachment_id, $gallery_ids)) {
            $gallery_ids[] = $attachment_id;
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
    }
    
    /**
     * Get product ID by Business Central ID
     */
    private function get_product_id_by_bc_id($bc_id) {
        if (!$bc_id) {
            return 0;
        }
        
        $query = new WP_Query(array(
            'post_type' => 'product',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'business_central_item_id',
                    'value' => $bc_id,
                    'compare' => '='
                )
            )
        ));
        
        if ($query->have_posts()) {
            $product_id = intval($query->posts[0]);
            wp_reset_postdata();
            return $product_id;
        }
        
        return 0;
    }
    
    /**
     * Truncate description for short description
     */
    private function truncate_description($description, $length = 150) {
        if (strlen($description) <= $length) {
            return $description;
        }
        
        $truncated = substr($description, 0, $length);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }
        
        return $truncated . '...';
    }
    
    /**
     * Get incremental sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('bcc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $last_sync = get_option('bcc_last_product_sync_timestamp', '');
        $total_products = $this->count_products_with_bc_metadata();
        
        wp_send_json_success(array(
            'last_sync' => $last_sync,
            'total_products' => $total_products,
            'next_sync_recommended' => !empty($last_sync) ? 'incremental' : 'full'
        ));
    }
    
    /**
     * Count products with Business Central metadata
     */
    private function count_products_with_bc_metadata() {
        $query = new WP_Query(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'business_central_item_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $count = $query->found_posts;
        wp_reset_postdata();
        
        return $count;
    }

    /**
     * Simple sync method using BCWoo_Sync class
     */
    public function ajax_simple_sync() {
        check_ajax_referer('bcc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'full';
        
        try {
            if ($sync_type === 'full') {
                BCWoo_Sync::run_full();
                wp_send_json_success('Full sync completed successfully');
            } else {
                BCWoo_Sync::run_incremental();
                wp_send_json_success('Incremental sync completed successfully');
            }
        } catch (Exception $e) {
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }
}

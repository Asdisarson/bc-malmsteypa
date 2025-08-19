<?php
/**
 * BCWoo_Client - Clean Business Central API Client
 * 
 * This class provides a clean, focused interface for Business Central API interactions.
 * It handles authentication, API requests, and media downloads with proper error handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BCWoo_Client {
    private $opts;
    private $base;

    public function __construct() {
        $this->opts = get_option('bcc_settings', []);
        $tenant = trim($this->opts['tenant_id'] ?? '');
        $env    = rawurlencode(trim($this->opts['bc_environment'] ?? 'Production'));
        $this->base = "https://api.businesscentral.dynamics.com/v2.0/{$tenant}/{$env}/api/v2.0";
    }

    /**
     * Get OAuth 2.0 access token with caching
     */
    private function get_token() {
        $cached = get_transient('bcwoo_token');
        if ($cached) return $cached;

        $tenant = trim($this->opts['tenant_id'] ?? '');
        $resp = wp_remote_post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'body' => [
                'client_id' => $this->opts['client_id'] ?? '',
                'client_secret' => $this->opts['client_secret'] ?? '',
                'scope' => 'https://api.businesscentral.dynamics.com/.default',
                'grant_type' => 'client_credentials'
        ],
            'timeout' => 30
        ]);
        if (is_wp_error($resp)) throw new Exception($resp->get_error_message());
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['access_token'])) throw new Exception('Token fetch failed: '. wp_remote_retrieve_body($resp));
        set_transient('bcwoo_token', $data['access_token'], max(60, (int)$data['expires_in'] - 60));
        return $data['access_token'];
    }

    /**
     * Force refresh OAuth token (clears cache and gets new token)
     */
    public function refresh_token() {
        delete_transient('bcwoo_token');
        return $this->get_token();
    }

    /**
     * Make GET request to Business Central API with token refresh on auth errors
     */
    private function get($path, $query = [], $retry = true) {
        $token = $this->get_token();
        $url = $this->base . $path;
        if ($query) $url .= (strpos($url, '?') ? '&' : '?') . http_build_query($query);

        $resp = wp_remote_get($url, [
            'headers' => ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
            'timeout' => 30
        ]);

        if (is_wp_error($resp)) throw new Exception($resp->get_error_message());
        $code = wp_remote_retrieve_response_code($resp);

        if ($code === 401 && $retry) {
            // Clear token and try once more
            delete_transient('bcwoo_token');
            return $this->get($path, $query, false);
        }

        if ($code >= 400) {
            throw new Exception("BC GET {$path} failed: HTTP {$code} " . wp_remote_retrieve_body($resp));
        }
        return json_decode(wp_remote_retrieve_body($resp), true);
    }

    /**
     * Download media content (images) from Business Central
     */
    private function get_media($mediaUrl) {
        $token = $this->get_token();
        $resp = wp_remote_get($mediaUrl, [
            'headers' => ['Authorization' => "Bearer {$token}"],
            'timeout' => 30
        ]);
        
        if (is_wp_error($resp)) {
            throw new Exception($resp->get_error_message());
        }
        
        if (wp_remote_retrieve_response_code($resp) >= 400) {
            throw new Exception('Media download failed');
        }
        
        return $resp;
    }

    /**
     * List items with optional filtering and pagination
     */
    public function list_items($companyId, $filter = null, $top = 100) {
        $path = "/companies({$companyId})/items";
        $query = ['$top' => $top];
        
        if ($filter) {
            $query['$filter'] = $filter;
        }
        
        return $this->get($path, $query);
    }

    /**
     * Follow nextLink for pagination (nextLink is absolute URL)
     */
    public function list_items_next($nextLink) {
        $token = $this->get_token();
        $resp = wp_remote_get($nextLink, [
            'headers' => [
                'Authorization' => "Bearer {$token}", 
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($resp)) {
            throw new Exception($resp->get_error_message());
        }
        
        if (wp_remote_retrieve_response_code($resp) >= 400) {
            throw new Exception('NextLink failed');
        }
        
        return json_decode(wp_remote_retrieve_body($resp), true);
    }

    /**
     * Get item pictures with expansion
     */
    public function get_item_pictures($companyId, $itemId) {
        return $this->get("/companies({$companyId})/items({$itemId})/picture", []);
    }

    /**
     * Download picture stream using content@odata.mediaReadLink
     */
    public function download_picture_stream($picture) {
        // Handle different picture data structures
        $url = null;
        
        if (is_array($picture) && isset($picture[0])) {
            // Picture is a collection
            if (isset($picture[0]['content@odata.mediaReadLink'])) {
                $url = $picture[0]['content@odata.mediaReadLink'];
            } elseif (isset($picture[0]['pictureContent@odata.mediaReadLink'])) {
                $url = $picture[0]['pictureContent@odata.mediaReadLink'];
            }
        } elseif (is_array($picture) && isset($picture['content@odata.mediaReadLink'])) {
            $url = $picture['content@odata.mediaReadLink'];
        } elseif (is_array($picture) && isset($picture['pictureContent@odata.mediaReadLink'])) {
            $url = $picture['pictureContent@odata.mediaReadLink'];
        }
        
        if (!$url) {
            return null;
        }
        
        return $this->get_media($url);
    }
    
    /**
     * Test connection to Business Central
     */
    public function test_connection() {
        try {
            $this->get('/companies');
            return true;
        } catch (Exception $e) {
            throw new Exception('Connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get company information
     */
    public function get_companies() {
        return $this->get('/companies');
    }
    
    /**
     * Get item categories
     */
    public function get_item_categories($companyId) {
        return $this->get("/companies({$companyId})/itemCategories");
    }
}

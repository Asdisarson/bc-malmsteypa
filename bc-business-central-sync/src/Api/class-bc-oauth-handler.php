<?php

/**
 * OAuth Handler for Business Central Integration
 *
 * Handles OAuth 2.0 authorization code flow for Microsoft Business Central API.
 * Manages authentication, token refresh, and callback processing.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/Api
 */
class BC_OAuth_Handler {

    /**
     * OAuth configuration options.
     *
     * @var array
     */
    private $config;

    /**
     * Current access token.
     *
     * @var string|null
     */
    private $access_token;

    /**
     * Token expiration timestamp.
     *
     * @var int|null
     */
    private $token_expires;

    /**
     * Refresh token for getting new access tokens.
     *
     * @var string|null
     */
    private $refresh_token;

    /**
     * Initialize the OAuth handler.
     *
     * @since 1.0.0
     */
    public function __construct() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Initializing OAuth handler');
        }
        
        $this->init_config();
        $this->load_tokens();
        $this->init_hooks();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: OAuth handler initialized');
            error_log('BC OAuth: Config loaded - Client ID: ' . (!empty($this->config['client_id']) ? 'Set' : 'Not set'));
            error_log('BC OAuth: Config loaded - Client Secret: ' . (!empty($this->config['client_secret']) ? 'Set' : 'Not set'));
        }
    }

    /**
     * Initialize OAuth configuration.
     *
     * @since 1.0.0
     * @access private
     */
    private function init_config() {
        // Get tenant ID from options, default to 'common' if not set
        $tenant_id = get_option('bc_oauth_tenant_id', 'common');
        
        $this->config = array(
            'client_id' => get_option('bc_oauth_client_id', ''),
            'client_secret' => get_option('bc_oauth_client_secret', ''),
            'tenant_id' => $tenant_id,
            'redirect_uri' => admin_url('admin-ajax.php?action=bc_oauth_callback'),
            'authorization_url' => 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/token',
            'scope' => 'https://api.businesscentral.dynamics.com/.default offline_access',
            'response_type' => 'code',
            'state_nonce' => wp_create_nonce('bc_oauth_state'),
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Config initialized');
            error_log('BC OAuth: Client ID from options: ' . (!empty($this->config['client_id']) ? 'Set' : 'Not set'));
            error_log('BC OAuth: Client Secret from options: ' . (!empty($this->config['client_secret']) ? 'Set' : 'Not set'));
            error_log('BC OAuth: Redirect URI: ' . $this->config['redirect_uri']);
        }
    }

    /**
     * Initialize WordPress hooks.
     *
     * @since 1.0.0
     * @access private
     */
    private function init_hooks() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Initializing hooks');
        }
        
        // AJAX actions for OAuth flow
        add_action('wp_ajax_bc_oauth_initiate', array($this, 'initiate_oauth'));
        add_action('wp_ajax_bc_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_bc_oauth_refresh', array($this, 'refresh_access_token'));
        add_action('wp_ajax_bc_oauth_revoke', array($this, 'revoke_tokens'));
        
        // Public AJAX actions (no login required for callback)
        add_action('wp_ajax_nopriv_bc_oauth_callback', array($this, 'handle_oauth_callback'));
        
        // Admin notices for OAuth status
        add_action('admin_notices', array($this, 'display_oauth_notices'));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Hooks initialized');
        }
    }

    /**
     * Initiate OAuth flow by redirecting to Microsoft.
     *
     * @since 1.0.0
     */
    public function initiate_oauth() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: OAuth initiation requested');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bc_oauth_initiate')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Nonce verification failed for OAuth initiation');
            }
            wp_die('Security check failed');
        }

        // Check if we have required configuration
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Configuration incomplete - Client ID: ' . (!empty($this->config['client_id']) ? 'Set' : 'Not set') . ', Client Secret: ' . (!empty($this->config['client_secret']) ? 'Set' : 'Not set'));
            }
            wp_send_json_error('OAuth configuration incomplete. Please configure client ID and secret.');
        }

        // Generate and store state parameter
        $state = wp_generate_password(32, false);
        update_option('bc_oauth_state', $state);
        update_option('bc_oauth_state_timestamp', time());

        // Build authorization URL
        $auth_params = array(
            'client_id' => $this->config['client_id'],
            'response_type' => $this->config['response_type'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $this->config['scope'],
            'state' => $state,
            'response_mode' => 'query',
        );

        $auth_url = add_query_arg($auth_params, $this->config['authorization_url']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Authorization URL generated: ' . $auth_url);
        }

        // Log OAuth initiation
        $this->log('OAuth flow initiated', 'info');

        // Return the authorization URL
        wp_send_json_success(array(
            'auth_url' => $auth_url,
            'message' => 'Redirecting to Microsoft for authorization...'
        ));
    }

    /**
     * Handle OAuth callback from Microsoft.
     *
     * @since 1.0.0
     */
    public function handle_oauth_callback() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: OAuth callback received');
            error_log('BC OAuth: GET parameters: ' . print_r($_GET, true));
        }
        
        // Get callback parameters
        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');
        $error = sanitize_text_field($_GET['error'] ?? '');

        // Check for errors
        if (!empty($error)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Callback error received: ' . $error);
            }
            $this->log('OAuth callback error: ' . $error, 'error');
            $this->redirect_with_message('error', 'OAuth authorization failed: ' . $error);
            return;
        }

        // Validate state parameter
        if (!$this->validate_state($state)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: State validation failed for state: ' . $state);
            }
            $this->log('OAuth state validation failed', 'error');
            $this->redirect_with_message('error', 'OAuth state validation failed. Please try again.');
            return;
        }

        // Check if we have authorization code
        if (empty($code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Authorization code missing from callback');
            }
            $this->log('OAuth callback missing authorization code', 'error');
            $this->redirect_with_message('error', 'Authorization code not received from Microsoft.');
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Exchanging authorization code for tokens');
        }

        // Exchange authorization code for tokens
        $tokens = $this->exchange_code_for_tokens($code);
        if (!$tokens) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Token exchange failed');
            }
            $this->log('Failed to exchange authorization code for tokens', 'error');
            $this->redirect_with_message('error', 'Failed to obtain access tokens. Please try again.');
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Tokens received successfully');
        }

        // Store tokens securely
        $this->store_tokens($tokens);

        // Log successful OAuth completion
        $this->log('OAuth flow completed successfully', 'info');

        // Redirect with success message
        $this->redirect_with_message('success', 'OAuth authorization completed successfully! You can now use Business Central features.');
    }

    /**
     * Exchange authorization code for access and refresh tokens.
     *
     * @since 1.0.0
     * @param string $code Authorization code from Microsoft.
     * @return array|false Token data on success, false on failure.
     */
    private function exchange_code_for_tokens($code) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Starting token exchange');
            error_log('BC OAuth: Token URL: ' . $this->config['token_url']);
            error_log('BC OAuth: Client ID: ' . $this->config['client_id']);
            error_log('BC OAuth: Redirect URI: ' . $this->config['redirect_uri']);
        }
        
        $body = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Token exchange request body: ' . print_r($body, true));
        }

        $response = wp_remote_post($this->config['token_url'], array(
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Token exchange request failed: ' . $response->get_error_message());
            }
            $this->log('Token exchange request failed: ' . $response->get_error_message(), 'error');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Token exchange response code: ' . $response_code);
            error_log('BC OAuth: Token exchange response body: ' . $response_body);
        }

        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Token exchange failed with code ' . $response_code . ': ' . $response_body);
            }
            $this->log('Token exchange failed with code ' . $response_code . ': ' . $response_body, 'error');
            return false;
        }

        $tokens = json_decode($response_body, true);
        
        if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Token response missing required fields: ' . $response_body);
            }
            $this->log('Token response missing required fields: ' . $response_body, 'error');
            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Token exchange successful');
            error_log('BC OAuth: Access token length: ' . strlen($tokens['access_token']));
            error_log('BC OAuth: Refresh token length: ' . strlen($tokens['refresh_token']));
            error_log('BC OAuth: Token expires in: ' . $tokens['expires_in'] . ' seconds');
        }

        return $tokens;
    }

    /**
     * Refresh access token using refresh token.
     *
     * @since 1.0.0
     * @return string|false New access token on success, false on failure.
     */
    public function refresh_access_token() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bc_oauth_refresh')) {
            wp_die('Security check failed');
        }

        if (empty($this->refresh_token)) {
            wp_send_json_error('No refresh token available');
        }

        $body = array(
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $this->refresh_token,
        );

        $response = wp_remote_post($this->config['token_url'], array(
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log('Token refresh failed: ' . $response->get_error_message(), 'error');
            wp_send_json_error('Failed to refresh access token');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $this->log('Token refresh failed with code ' . $response_code . ': ' . $response_body, 'error');
            wp_send_json_error('Failed to refresh access token');
        }

        $tokens = json_decode($response_body, true);
        
        if (!isset($tokens['access_token'])) {
            wp_send_json_error('Invalid token refresh response');
        }

        // Update stored tokens
        $this->store_tokens($tokens);
        
        wp_send_json_success('Access token refreshed successfully');
    }

    /**
     * Revoke all OAuth tokens.
     *
     * @since 1.0.0
     */
    public function revoke_tokens() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bc_oauth_revoke')) {
            wp_die('Security check failed');
        }

        // Clear stored tokens
        delete_option('bc_oauth_access_token');
        delete_option('bc_oauth_refresh_token');
        delete_option('bc_oauth_token_expires');
        delete_option('bc_oauth_state');
        delete_option('bc_oauth_state_timestamp');

        $this->access_token = null;
        $this->refresh_token = null;
        $this->token_expires = null;

        $this->log('OAuth tokens revoked', 'info');
        wp_send_json_success('OAuth tokens revoked successfully');
    }

    /**
     * Get current access token, refreshing if necessary.
     *
     * @since 1.0.0
     * @return string|false Access token on success, false on failure.
     */
    public function get_access_token() {
        // Check if token is expired or will expire soon (5 minutes buffer)
        if ($this->token_expires && (time() + 300) > $this->token_expires) {
            if (!$this->refresh_access_token_internal()) {
                return false;
            }
        }

        return $this->access_token;
    }

    /**
     * Internal method to refresh access token.
     *
     * @since 1.0.0
     * @return bool True on success, false on failure.
     */
    private function refresh_access_token_internal() {
        if (empty($this->refresh_token)) {
            return false;
        }

        $body = array(
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $this->refresh_token,
        );

        $response = wp_remote_post($this->config['token_url'], array(
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log('Internal token refresh failed: ' . $response->get_error_message(), 'error');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $this->log('Internal token refresh failed with code ' . $response_code, 'error');
            return false;
        }

        $tokens = json_decode($response_body, true);
        
        if (!isset($tokens['access_token'])) {
            return false;
        }

        $this->store_tokens($tokens);
        return true;
    }

    /**
     * Validate OAuth state parameter.
     *
     * @since 1.0.0
     * @param string $state State parameter from callback.
     * @return bool True if valid, false otherwise.
     */
    private function validate_state($state) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Validating state parameter: ' . $state);
        }
        
        $stored_state = get_option('bc_oauth_state');
        $state_timestamp = get_option('bc_oauth_state_timestamp');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Stored state: ' . ($stored_state ?: 'Not set'));
            error_log('BC OAuth: State timestamp: ' . ($state_timestamp ? date('Y-m-d H:i:s', $state_timestamp) : 'Not set'));
        }

        // Check if state exists and is not expired (10 minutes)
        if (!$stored_state || !$state_timestamp || (time() - $state_timestamp) > 600) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: State validation failed - State missing or expired');
                if (!$stored_state) error_log('BC OAuth: State validation failed - No stored state');
                if (!$state_timestamp) error_log('BC OAuth: State validation failed - No timestamp');
                if ($state_timestamp && (time() - $state_timestamp) > 600) error_log('BC OAuth: State validation failed - State expired');
            }
            return false;
        }

        // Check if state matches
        if ($state !== $stored_state) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: State validation failed - State mismatch');
                error_log('BC OAuth: Received state: ' . $state);
                error_log('BC OAuth: Stored state: ' . $stored_state);
            }
            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: State validation successful');
        }

        // Clear used state
        delete_option('bc_oauth_state');
        delete_option('bc_oauth_state_timestamp');

        return true;
    }

    /**
     * Store OAuth tokens securely.
     *
     * @since 1.0.0
     * @param array $tokens Token data from Microsoft.
     */
    private function store_tokens($tokens) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Storing tokens');
        }
        
        $this->access_token = $tokens['access_token'];
        $this->refresh_token = $tokens['refresh_token'];
        $this->token_expires = time() + $tokens['expires_in'];

        // Store tokens in WordPress options (consider encrypting in production)
        update_option('bc_oauth_access_token', $this->access_token);
        update_option('bc_oauth_refresh_token', $this->refresh_token);
        update_option('bc_oauth_token_expires', $this->token_expires);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Tokens stored successfully');
            error_log('BC OAuth: Access token stored: ' . (!empty($this->access_token) ? 'Yes' : 'No'));
            error_log('BC OAuth: Refresh token stored: ' . (!empty($this->refresh_token) ? 'Yes' : 'No'));
            error_log('BC OAuth: Token expires at: ' . date('Y-m-d H:i:s', $this->token_expires));
        }
    }

    /**
     * Load stored tokens from WordPress options.
     *
     * @since 1.0.0
     * @access private
     */
    private function load_tokens() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Loading stored tokens');
        }
        
        $this->access_token = get_option('bc_oauth_access_token');
        $this->refresh_token = get_option('bc_oauth_refresh_token');
        $this->token_expires = get_option('bc_oauth_token_expires');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Tokens loaded from options');
            error_log('BC OAuth: Access token loaded: ' . (!empty($this->access_token) ? 'Yes' : 'No'));
            error_log('BC OAuth: Refresh token loaded: ' . (!empty($this->refresh_token) ? 'Yes' : 'No'));
            error_log('BC OAuth: Token expires loaded: ' . (!empty($this->token_expires) ? 'Yes' : 'No'));
            if ($this->token_expires) {
                error_log('BC OAuth: Token expires at: ' . date('Y-m-d H:i:s', $this->token_expires));
                error_log('BC OAuth: Token is expired: ' . (time() > $this->token_expires ? 'Yes' : 'No'));
            }
        }
    }

    /**
     * Check if OAuth is configured and authenticated.
     *
     * @since 1.0.0
     * @return bool True if configured and authenticated, false otherwise.
     */
    public function is_authenticated() {
        $authenticated = !empty($this->access_token) && 
               !empty($this->refresh_token) && 
               $this->token_expires && 
               (time() + 300) < $this->token_expires;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Authentication check - Result: ' . ($authenticated ? 'Yes' : 'No'));
            error_log('BC OAuth: Authentication check - Access token: ' . (!empty($this->access_token) ? 'Set' : 'Not set'));
            error_log('BC OAuth: Authentication check - Refresh token: ' . (!empty($this->refresh_token) ? 'Set' : 'Not set'));
            error_log('BC OAuth: Authentication check - Token expires: ' . (!empty($this->token_expires) ? 'Set' : 'Not set'));
            if ($this->token_expires) {
                error_log('BC OAuth: Authentication check - Token expires at: ' . date('Y-m-d H:i:s', $this->token_expires));
                error_log('BC OAuth: Authentication check - Current time: ' . date('Y-m-d H:i:s'));
                error_log('BC OAuth: Authentication check - Time until expiry: ' . ($this->token_expires - time()) . ' seconds');
            }
        }
        
        return $authenticated;
    }

    /**
     * Check if OAuth is configured.
     *
     * @since 1.0.0
     * @return bool True if configured, false otherwise.
     */
    public function is_configured() {
        $configured = !empty($this->config['client_id']) && !empty($this->config['client_secret']);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Configuration check - Result: ' . ($configured ? 'Yes' : 'No'));
            error_log('BC OAuth: Configuration check - Client ID: ' . (!empty($this->config['client_id']) ? 'Set' : 'Not set'));
            error_log('BC OAuth: Configuration check - Client Secret: ' . (!empty($this->config['client_secret']) ? 'Set' : 'Not set'));
        }
        
        return $configured;
    }

    /**
     * Get OAuth configuration status.
     *
     * @since 1.0.0
     * @return array Configuration status information.
     */
    public function get_status() {
        $status = array(
            'configured' => $this->is_configured(),
            'authenticated' => $this->is_authenticated(),
            'token_expires' => $this->token_expires,
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $this->config['scope'],
            'tenant_id' => $this->config['tenant_id'],
            'authorization_url' => $this->config['authorization_url'],
            'token_url' => $this->config['token_url'],
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Status requested');
            error_log('BC OAuth: Status - Configured: ' . ($status['configured'] ? 'Yes' : 'No'));
            error_log('BC OAuth: Status - Authenticated: ' . ($status['authenticated'] ? 'Yes' : 'No'));
            error_log('BC OAuth: Status - Token expires: ' . ($status['token_expires'] ? date('Y-m-d H:i:s', $status['token_expires']) : 'Not set'));
            error_log('BC OAuth: Status - Redirect URI: ' . $status['redirect_uri']);
            error_log('BC OAuth: Status - Scope: ' . $status['scope']);
        }
        
        return $status;
    }

    /**
     * Redirect with message after OAuth callback.
     *
     * @since 1.0.0
     * @param string $type Message type (success, error, warning).
     * @param string $message Message to display.
     */
    private function redirect_with_message($type, $message) {
        // Store message in transient for display
        set_transient('bc_oauth_message', array(
            'type' => $type,
            'message' => $message
        ), 60);

        // Redirect to admin page
        wp_redirect(admin_url('admin.php?page=bc-business-central-sync'));
        exit;
    }

    /**
     * Display OAuth status notices in admin.
     *
     * @since 1.0.0
     */
    public function display_oauth_notices() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: Displaying OAuth notices');
        }
        
        // Display stored messages
        $message = get_transient('bc_oauth_message');
        if ($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Displaying stored message - Type: ' . $message['type'] . ', Message: ' . $message['message']);
            }
            $class = 'notice-' . $message['type'];
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', 
                   esc_attr($class), 
                   esc_html($message['message']));
            delete_transient('bc_oauth_message');
        }

        // Display OAuth status
        if (!$this->is_configured()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Displaying configuration warning notice');
            }
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>Business Central OAuth:</strong> OAuth is not configured. Please configure your Microsoft Azure app credentials.';
            echo '</p></div>';
        } elseif (!$this->is_authenticated()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC OAuth: Displaying authentication warning notice');
            }
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>Business Central OAuth:</strong> OAuth authentication required. Please complete the authorization flow.';
            echo '</p></div>';
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC OAuth: OAuth notices displayed');
        }
    }

    /**
     * Log messages for debugging.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param string $level Log level (info, warning, error).
     */
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = "BC OAuth [{$level}]: {$message}";
            error_log($log_message);
            
            // Also log to WordPress debug log if available
            if (function_exists('wp_debug_log')) {
                wp_debug_log($log_message, 'bc-oauth');
            }
        }
    }
}

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
        $this->init_config();
        $this->load_tokens();
        $this->init_hooks();
    }

    /**
     * Initialize OAuth configuration.
     *
     * @since 1.0.0
     * @access private
     */
    private function init_config() {
        $this->config = array(
            'client_id' => get_option('bc_oauth_client_id', ''),
            'client_secret' => get_option('bc_oauth_client_secret', ''),
            'redirect_uri' => admin_url('admin-ajax.php?action=bc_oauth_callback'),
            'authorization_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'scope' => 'https://api.businesscentral.dynamics.com/.default offline_access',
            'response_type' => 'code',
            'state_nonce' => wp_create_nonce('bc_oauth_state'),
        );
    }

    /**
     * Initialize WordPress hooks.
     *
     * @since 1.0.0
     * @access private
     */
    private function init_hooks() {
        // AJAX actions for OAuth flow
        add_action('wp_ajax_bc_oauth_initiate', array($this, 'initiate_oauth'));
        add_action('wp_ajax_bc_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_bc_oauth_refresh', array($this, 'refresh_access_token'));
        add_action('wp_ajax_bc_oauth_revoke', array($this, 'revoke_tokens'));
        
        // Public AJAX actions (no login required for callback)
        add_action('wp_ajax_nopriv_bc_oauth_callback', array($this, 'handle_oauth_callback'));
        
        // Admin notices for OAuth status
        add_action('admin_notices', array($this, 'display_oauth_notices'));
    }

    /**
     * Initiate OAuth flow by redirecting to Microsoft.
     *
     * @since 1.0.0
     */
    public function initiate_oauth() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bc_oauth_initiate')) {
            wp_die('Security check failed');
        }

        // Check if we have required configuration
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
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
        // Get callback parameters
        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');
        $error = sanitize_text_field($_GET['error'] ?? '');

        // Check for errors
        if (!empty($error)) {
            $this->log('OAuth callback error: ' . $error, 'error');
            $this->redirect_with_message('error', 'OAuth authorization failed: ' . $error);
            return;
        }

        // Validate state parameter
        if (!$this->validate_state($state)) {
            $this->log('OAuth state validation failed', 'error');
            $this->redirect_with_message('error', 'OAuth state validation failed. Please try again.');
            return;
        }

        // Check if we have authorization code
        if (empty($code)) {
            $this->log('OAuth callback missing authorization code', 'error');
            $this->redirect_with_message('error', 'Authorization code not received from Microsoft.');
            return;
        }

        // Exchange authorization code for tokens
        $tokens = $this->exchange_code_for_tokens($code);
        if (!$tokens) {
            $this->log('Failed to exchange authorization code for tokens', 'error');
            $this->redirect_with_message('error', 'Failed to obtain access tokens. Please try again.');
            return;
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
        $body = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        );

        $response = wp_remote_post($this->config['token_url'], array(
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log('Token exchange request failed: ' . $response->get_error_message(), 'error');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $this->log('Token exchange failed with code ' . $response_code . ': ' . $response_body, 'error');
            return false;
        }

        $tokens = json_decode($response_body, true);
        
        if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
            $this->log('Token response missing required fields: ' . $response_body, 'error');
            return false;
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
        $stored_state = get_option('bc_oauth_state');
        $state_timestamp = get_option('bc_oauth_state_timestamp');

        // Check if state exists and is not expired (10 minutes)
        if (!$stored_state || !$state_timestamp || (time() - $state_timestamp) > 600) {
            return false;
        }

        // Check if state matches
        if ($state !== $stored_state) {
            return false;
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
        $this->access_token = $tokens['access_token'];
        $this->refresh_token = $tokens['refresh_token'];
        $this->token_expires = time() + $tokens['expires_in'];

        // Store tokens in WordPress options (consider encrypting in production)
        update_option('bc_oauth_access_token', $this->access_token);
        update_option('bc_oauth_refresh_token', $this->refresh_token);
        update_option('bc_oauth_token_expires', $this->token_expires);
    }

    /**
     * Load stored tokens from WordPress options.
     *
     * @since 1.0.0
     * @access private
     */
    private function load_tokens() {
        $this->access_token = get_option('bc_oauth_access_token');
        $this->refresh_token = get_option('bc_oauth_refresh_token');
        $this->token_expires = get_option('bc_oauth_token_expires');
    }

    /**
     * Check if OAuth is configured and authenticated.
     *
     * @since 1.0.0
     * @return bool True if configured and authenticated, false otherwise.
     */
    public function is_authenticated() {
        return !empty($this->access_token) && 
               !empty($this->refresh_token) && 
               $this->token_expires && 
               (time() + 300) < $this->token_expires;
    }

    /**
     * Check if OAuth is configured.
     *
     * @since 1.0.0
     * @return bool True if configured, false otherwise.
     */
    public function is_configured() {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }

    /**
     * Get OAuth configuration status.
     *
     * @since 1.0.0
     * @return array Configuration status information.
     */
    public function get_status() {
        return array(
            'configured' => $this->is_configured(),
            'authenticated' => $this->is_authenticated(),
            'token_expires' => $this->token_expires,
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $this->config['scope'],
        );
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
        // Display stored messages
        $message = get_transient('bc_oauth_message');
        if ($message) {
            $class = 'notice-' . $message['type'];
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', 
                   esc_attr($class), 
                   esc_html($message['message']));
            delete_transient('bc_oauth_message');
        }

        // Display OAuth status
        if (!$this->is_configured()) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>Business Central OAuth:</strong> OAuth is not configured. Please configure your Microsoft Azure app credentials.';
            echo '</p></div>';
        } elseif (!$this->is_authenticated()) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>Business Central OAuth:</strong> OAuth authentication required. Please complete the authorization flow.';
            echo '</p></div>';
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
            error_log("BC OAuth [{$level}]: {$message}");
        }
    }
}

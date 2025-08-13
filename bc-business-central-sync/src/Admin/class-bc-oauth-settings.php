<?php

/**
 * OAuth Settings Registration
 *
 * Handles WordPress settings API registration and validation for OAuth configuration.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/Admin
 */
class BC_OAuth_Settings {

    /**
     * Settings group name.
     *
     * @var string
     */
    private $settings_group = 'bc_oauth_settings';

    /**
     * Settings page slug.
     *
     * @var string
     */
    private $settings_page = 'bc-oauth-settings';

    /**
     * Initialize the settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize settings after admin menu is created
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'init_settings'));
        
        // Register form submission handler
        $this->register_oauth_options_page();
    }

    /**
     * Initialize WordPress settings API.
     *
     * @since 1.0.0
     */
    public function init_settings() {
        // Settings are now handled manually in handle_oauth_form_submission()
        // This method is kept for compatibility but no longer registers settings
    }

    /**
     * Add settings page to admin menu.
     *
     * @since 1.0.0
     */
    public function add_settings_page() {
        add_submenu_page(
            'bc-business-central-sync',
            'OAuth Settings',
            'OAuth Settings',
            'manage_woocommerce', // Use same capability as other submenus
            $this->settings_page,
            array($this, 'settings_page_callback')
        );
    }

    /**
     * Settings section callback.
     *
     * @since 1.0.0
     */
    public function settings_section_callback() {
        echo '<p>Configure your Microsoft Azure application credentials for Business Central OAuth integration.</p>';
    }

    /**
     * Client ID field callback.
     *
     * @since 1.0.0
     */
    public function client_id_field_callback() {
        $value = get_option('bc_oauth_client_id', '');
        printf(
            '<input type="text" id="bc_oauth_client_id" name="bc_oauth_client_id" value="%s" class="regular-text" required />',
            esc_attr($value)
        );
        echo '<p class="description">Your Microsoft Azure application (client) ID</p>';
    }

    /**
     * Client secret field callback.
     *
     * @since 1.0.0
     */
    public function client_secret_field_callback() {
        $value = get_option('bc_oauth_client_secret', '');
        printf(
            '<input type="password" id="bc_oauth_client_secret" name="bc_oauth_client_secret" value="%s" class="regular-text" required />',
            esc_attr($value)
        );
        echo '<p class="description">Your Microsoft Azure application client secret</p>';
    }

    /**
     * Tenant ID field callback.
     *
     * @since 1.0.0
     */
    public function tenant_id_field_callback() {
        $value = get_option('bc_oauth_tenant_id', '');
        printf(
            '<input type="text" id="bc_oauth_tenant_id" name="bc_oauth_tenant_id" value="%s" class="regular-text" />',
            esc_attr($value)
        );
        echo '<p class="description">Your Azure AD tenant ID (leave empty to use \'common\' for multi-tenant apps)</p>';
    }

    /**
     * Register OAuth options page with WordPress.
     *
     * @since 1.0.0
     */
    public function register_oauth_options_page() {
        // Handle form submission for OAuth settings
        add_action('admin_init', function() {
            // Debug: Log what's being received
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('OAuth Form Debug: POST data received: ' . print_r($_POST, true));
            }
            
            if (isset($_POST['bc_oauth_action']) && $_POST['bc_oauth_action'] === 'save_settings') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Processing form submission');
                }
                $this->handle_oauth_form_submission();
            }
        });
    }
    
    /**
     * Handle OAuth form submission manually.
     *
     * @since 1.0.0
     */
    private function handle_oauth_form_submission() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('OAuth Form Debug: Form submission received');
            error_log('OAuth Form Debug: POST data: ' . print_r($_POST, true));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bc_oauth_settings_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('OAuth Form Debug: Nonce verification failed');
            }
            wp_die(__('Security check failed.'));
        }
        
        // Handle OAuth client ID
        if (isset($_POST['bc_oauth_client_id'])) {
            $client_id = sanitize_text_field($_POST['bc_oauth_client_id']);
            if ($this->validate_client_id($client_id)) {
                update_option('bc_oauth_client_id', $client_id);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Client ID saved: ' . $client_id);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Client ID validation failed: ' . $client_id);
                }
            }
        }
        
        // Handle OAuth client secret
        if (isset($_POST['bc_oauth_client_secret'])) {
            $client_secret = sanitize_text_field($_POST['bc_oauth_client_secret']);
            if ($this->validate_client_secret($client_secret)) {
                update_option('bc_oauth_client_secret', $client_secret);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Client secret saved (length: ' . strlen($client_secret) . ')');
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Client secret validation failed (length: ' . strlen($client_secret) . ')');
                }
            }
        }
        
        // Handle OAuth tenant ID
        if (isset($_POST['bc_oauth_tenant_id'])) {
            $tenant_id = sanitize_text_field($_POST['bc_oauth_tenant_id']);
            if ($this->validate_tenant_id($tenant_id)) {
                update_option('bc_oauth_tenant_id', $tenant_id);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Tenant ID saved: ' . $tenant_id);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OAuth Form Debug: Tenant ID validation failed: ' . $tenant_id);
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('OAuth Form Debug: Form submission completed');
        }
        
        // Redirect with success message
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=' . $this->settings_page)));
        exit;
    }
    
    /**
     * Validate client ID format.
     *
     * @since 1.0.0
     * @param string $client_id Client ID to validate.
     * @return bool True if valid, false otherwise.
     */
    private function validate_client_id($client_id) {
        if (empty($client_id)) {
            return false;
        }
        
        // Validate GUID format (Microsoft Azure client IDs are GUIDs)
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id);
    }
    
    /**
     * Validate client secret.
     *
     * @since 1.0.0
     * @param string $client_secret Client secret to validate.
     * @return bool True if valid, false otherwise.
     */
    private function validate_client_secret($client_secret) {
        if (empty($client_secret)) {
            return false;
        }
        
        // Client secrets should be at least 16 characters
        if (strlen($client_secret) < 16) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate tenant ID.
     *
     * @since 1.0.0
     * @param string $tenant_id Tenant ID to validate.
     * @return bool True if valid, false otherwise.
     */
    private function validate_tenant_id($tenant_id) {
        // Empty is valid (defaults to 'common')
        if (empty($tenant_id)) {
            return true;
        }
        
        // Validate GUID format for tenant ID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant_id)) {
            return true;
        }
        
        // Allow 'common' for multi-tenant apps
        if ($tenant_id === 'common') {
            return true;
        }
        
        return false;
    }

    /**
     * Settings page callback.
     *
     * @since 1.0.0
     */
    public function settings_page_callback() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Handle form submission directly here
        if (isset($_POST['bc_oauth_action']) && $_POST['bc_oauth_action'] === 'save_settings') {
            $this->handle_oauth_form_submission();
        }

        // Include the admin display template
        require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'templates/bc-oauth-settings-admin-display.php';
    }

    /**
     * Sanitize client ID.
     *
     * @since 1.0.0
     * @param string $input Raw input value.
     * @return string Sanitized value.
     */
    public function sanitize_client_id($input) {
        $input = sanitize_text_field($input);
        
        // Validate GUID format (Microsoft Azure client IDs are GUIDs)
        if (!empty($input) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $input)) {
            add_settings_error(
                'bc_oauth_client_id',
                'bc_oauth_client_id_error',
                'Client ID must be a valid GUID format (e.g., 12345678-1234-1234-1234-123456789012)',
                'error'
            );
            return '';
        }

        return $input;
    }

    /**
     * Sanitize client secret.
     *
     * @since 1.0.0
     * @param string $input Raw input value.
     * @return string Sanitized value.
     */
    public function sanitize_client_secret($input) {
        $input = sanitize_text_field($input);
        
        // Client secrets should not be empty
        if (empty($input)) {
            add_settings_error(
                'bc_oauth_client_secret',
                'bc_oauth_client_secret_error',
                'Client secret cannot be empty',
                'error'
            );
            return '';
        }

        // Client secrets should be at least 16 characters
        if (strlen($input) < 16) {
            add_settings_error(
                'bc_oauth_client_secret',
                'bc_oauth_client_secret_error',
                'Client secret must be at least 16 characters long',
                'error'
            );
            return '';
        }

        return $input;
    }

    /**
     * Get OAuth configuration.
     *
     * @since 1.0.0
     * @return array OAuth configuration.
     */
    public function get_config() {
        return array(
            'client_id' => get_option('bc_oauth_client_id', ''),
            'client_secret' => get_option('bc_oauth_client_secret', ''),
        );
    }

    /**
     * Check if OAuth is configured.
     *
     * @since 1.0.0
     * @return bool True if configured, false otherwise.
     */
    public function is_configured() {
        $config = $this->get_config();
        return !empty($config['client_id']) && !empty($config['client_secret']);
    }

    /**
     * Validate OAuth configuration.
     *
     * @since 1.0.0
     * @return array Validation results.
     */
    public function validate_config() {
        $errors = array();
        $config = $this->get_config();

        if (empty($config['client_id'])) {
            $errors[] = 'Client ID is required';
        } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $config['client_id'])) {
            $errors[] = 'Client ID must be a valid GUID format';
        }

        if (empty($config['client_secret'])) {
            $errors[] = 'Client secret is required';
        } elseif (strlen($config['client_secret']) < 16) {
            $errors[] = 'Client secret must be at least 16 characters long';
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }
}

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
    private $settings_page = 'bc_oauth_settings';

    /**
     * Initialize the settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    /**
     * Initialize WordPress settings API.
     *
     * @since 1.0.0
     */
    public function init_settings() {
        // Register settings
        register_setting(
            $this->settings_group,
            'bc_oauth_client_id',
            array(
                'type' => 'string',
                'description' => 'Microsoft Azure application client ID',
                'sanitize_callback' => array($this, 'sanitize_client_id'),
                'show_in_rest' => false,
            )
        );

        register_setting(
            $this->settings_group,
            'bc_oauth_client_secret',
            array(
                'type' => 'string',
                'description' => 'Microsoft Azure application client secret',
                'sanitize_callback' => array($this, 'sanitize_client_secret'),
                'show_in_rest' => false,
            )
        );

        // Add settings section
        add_settings_section(
            'bc_oauth_main_section',
            'OAuth Configuration',
            array($this, 'settings_section_callback'),
            $this->settings_page
        );

        // Add settings fields
        add_settings_field(
            'bc_oauth_client_id',
            'Client ID',
            array($this, 'client_id_field_callback'),
            $this->settings_page,
            'bc_oauth_main_section'
        );

        add_settings_field(
            'bc_oauth_client_secret',
            'Client Secret',
            array($this, 'client_secret_field_callback'),
            $this->settings_page,
            'bc_oauth_main_section'
        );
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
            'manage_options',
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
     * Settings page callback.
     *
     * @since 1.0.0
     */
    public function settings_page_callback() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
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

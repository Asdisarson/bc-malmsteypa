<?php
/**
 * Business Central Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Business_Central_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Business Central Connector',
            'BC Connector',
            'manage_options',
            'business-central-connector',
            array($this, 'admin_page'),
            'dashicons-admin-generic',
            30
        );

        // Test subpage: Fetch Companies
        add_submenu_page(
            'business-central-connector',
            'BC Connector: Test',
            'Test Page',
            'manage_options',
            'business-central-connector-test',
            array($this, 'admin_test_page')
        );

        // Dokobit management submenus are added from BCC_Dokobit_Admin
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('bcc_settings_group', 'bcc_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'bcc_connection_section',
            'Connection Settings',
            array($this, 'connection_section_callback'),
            'business-central-connector'
        );
        
        // Add settings fields
        $fields = array(
            'base_url' => 'Base URL',
            'callback_url' => 'Callback URL',
            'tenant_id' => 'Tenant ID',
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'company_id' => 'Company ID',
            'bc_environment' => 'BC Environment',
            'api_version' => 'API Version',
            // Dokobit configuration
            'dokobit_api_base' => 'Dokobit API Base URL',
            'dokobit_api_key' => 'Dokobit API Key'
        );
        
        foreach ($fields as $field => $label) {
            add_settings_field(
                'bcc_' . $field,
                $label,
                array($this, 'field_callback'),
                'business-central-connector',
                'bcc_connection_section',
                array('field' => $field)
            );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Load scripts on both main settings page and test page
        $allowed_pages = array('business-central-connector', 'business-central-connector-test');
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if (!in_array($current_page, $allowed_pages, true)) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'bcc-admin-js',
            BCC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            BCC_PLUGIN_VERSION,
            true
        );
        
        // Build OAuth authorization URL for JS redirect
        $settings = get_option('bcc_settings', array());
        $tenant_id = isset($settings['tenant_id']) ? $settings['tenant_id'] : '';
        $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        $redirect_uri = isset($settings['callback_url']) ? $settings['callback_url'] : '';
        $auth_url = '';
        if (!empty($tenant_id) && !empty($client_id) && !empty($redirect_uri)) {
            $params = array(
                'client_id' => $client_id,
                'response_type' => 'code',
                'redirect_uri' => $redirect_uri,
                'scope' => 'https://api.businesscentral.dynamics.com/.default',
                'response_mode' => 'query'
            );
            $auth_url = 'https://login.microsoftonline.com/' . rawurlencode($tenant_id) . '/oauth2/v2.0/authorize?' . http_build_query($params);
        }
        
        wp_localize_script('bcc-admin-js', 'bcc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bcc_nonce'),
            'auth_url' => $auth_url
        ));
        
        wp_enqueue_style(
            'bcc-admin-css',
            BCC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BCC_PLUGIN_VERSION
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        $settings = get_option('bcc_settings', array());
        $connection_status = isset($settings['connection_status']) ? $settings['connection_status'] : 'disconnected';
        ?>
        <div class="wrap">
            <h1>Business Central Connector</h1>
            <?php
            if (isset($_GET['bcc_oauth'])) {
                $notice = sanitize_text_field($_GET['bcc_oauth']);
                if ($notice === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p>Successfully connected via OAuth 2.0.</p></div>';
                } elseif ($notice === 'error') {
                    echo '<div class="notice notice-error is-dismissible"><p>OAuth 2.0 connection failed. Please try again.</p></div>';
                }
            }
            ?>
            <div class="bcc-status-section">
                <h2>Connection Status</h2>
                <div class="bcc-status-indicator status-<?php echo esc_attr($connection_status); ?>">
                    <span class="status-text"><?php echo esc_html(ucfirst($connection_status)); ?></span>
                </div>
            </div>
            
            <div class="bcc-actions-section">
                <h2>Actions</h2>
                <div class="bcc-buttons">
                    <button type="button" class="button button-primary" id="bcc-setup-connection">
                        Setup Connection
                    </button>
                    <button type="button" class="button button-secondary" id="bcc-test-connection">
                        Test Connection
                    </button>
                    <button type="button" class="button button-secondary" id="bcc-refresh-connection">
                        Refresh Status
                    </button>
                </div>
                <div id="bcc-message" class="bcc-message"></div>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('bcc_settings_group');
                do_settings_sections('business-central-connector');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Admin test page callback
     */
    public function admin_test_page() {
        ?>
        <div class="wrap">
            <h1>BC Connector – Test</h1>
            <p>Use this page to test fetching data from Business Central.</p>

            <div class="bcc-actions-section">
                <h2>Customers</h2>
                <div class="bcc-buttons">
                    <button type="button" class="button" id="bcc-fetch-customers">Fetch Customers</button>
                    <button type="button" class="button" id="bcc-save-customers" disabled>Save Customers</button>
                </div>
            </div>

            <div class="bcc-actions-section">
                <h2>Item Categories</h2>
                <div class="bcc-buttons">
                    <button type="button" class="button" id="bcc-fetch-categories">Fetch Categories</button>
                    <button type="button" class="button" id="bcc-save-categories" disabled>Save Categories</button>
                </div>
            </div>

            <div class="bcc-actions-section">
                <h2>Items</h2>
                <div class="bcc-buttons">
                    <button type="button" class="button" id="bcc-fetch-items">Fetch Items</button>
                    <button type="button" class="button" id="bcc-save-items" disabled>Save Items</button>
                </div>
            </div>

            <div id="bcc-message" class="bcc-message"></div>

            <div class="bcc-status-section">
                <h2>Results</h2>
                <div id="bcc-customers-output"></div>
                <div id="bcc-categories-output"></div>
                <div id="bcc-items-output"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Connection section callback
     */
    public function connection_section_callback() {
        echo '<p>Configure your Business Central connection settings below:</p>';
    }
    
    /**
     * Field callback
     */
    public function field_callback($args) {
        $settings = get_option('bcc_settings', array());
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        
        if ($field === 'base_url') {
            $value = 'https://api.businesscentral.dynamics.com/';
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" readonly />';
            echo '<p class="description">This field is fixed and cannot be changed.</p>';
        } elseif ($field === 'callback_url') {
            $value = 'https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback';
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" readonly />';
            echo '<p class="description">This field is fixed and cannot be changed.</p>';
        } elseif ($field === 'api_version') {
            $value = 'v2.0';
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" readonly />';
            echo '<p class="description">This field is fixed and cannot be changed.</p>';
        } elseif ($field === 'client_secret') {
            echo '<input type="password" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        } elseif ($field === 'dokobit_api_base') {
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://gateway-sandbox.dokobit.com" />';
            echo '<p class="description">Base URL of Dokobit API (e.g., sandbox or production gateway).</p>';
        } elseif ($field === 'dokobit_api_key') {
            echo '<input type="password" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '<p class="description">API key/token for Dokobit. Stored in WordPress options.</p>';
        } else {
            echo '<input type="text" id="bcc_' . esc_attr($field) . '" name="bcc_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $fields = array('base_url', 'callback_url', 'tenant_id', 'client_id', 'client_secret', 'company_id', 'bc_environment', 'api_version', 'dokobit_api_base', 'dokobit_api_key');
        
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }
        
        // Set default values
        $sanitized['base_url'] = 'https://api.businesscentral.dynamics.com/';
        $sanitized['callback_url'] = 'https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback';
        $sanitized['api_version'] = 'v2.0';
        
        return $sanitized;
    }
}

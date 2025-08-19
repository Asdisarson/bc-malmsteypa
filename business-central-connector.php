<?php
/**
 * Plugin Name: Business Central Connector
 * Description: Connect WordPress to Microsoft Business Central for seamless data integration
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: business-central-connector
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BCC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BCC_PLUGIN_VERSION', '1.0.0');

// Include required files
require_once BCC_PLUGIN_PATH . 'includes/class-business-central-connector.php';
require_once BCC_PLUGIN_PATH . 'includes/class-business-central-admin.php';
require_once BCC_PLUGIN_PATH . 'includes/class-bcwoo-client.php';
require_once BCC_PLUGIN_PATH . 'includes/class-bcwoo-sync.php';
// Dokobit integration
require_once BCC_PLUGIN_PATH . 'includes/class-bcc-dokobit-api.php';
require_once BCC_PLUGIN_PATH . 'includes/class-bcc-dokobit-database.php';
require_once BCC_PLUGIN_PATH . 'includes/class-bcc-dokobit-shortcode.php';
require_once BCC_PLUGIN_PATH . 'includes/class-bcc-dokobit-admin.php';
require_once BCC_PLUGIN_PATH . 'includes/class-bcc-dokobit.php';

// Initialize the plugin
function bcc_init() {
    $connector = new Business_Central_Connector();
    if (is_admin()) {
        new Business_Central_Admin();
    }
    // Initialize Dokobit integration (shortcodes, ajax, admin submenus)
    BCC_Dokobit::init();
}
add_action('plugins_loaded', 'bcc_init');

// AJAX handlers for admin operations
add_action('wp_ajax_bcc_enhanced_product_sync', array('Business_Central_Connector', 'ajax_enhanced_product_sync'));
add_action('wp_ajax_bcc_get_sync_status', array('Business_Central_Connector', 'ajax_get_sync_status'));
add_action('wp_ajax_bcc_simple_sync', array('Business_Central_Connector', 'ajax_simple_sync'));

// OAuth callback handler for Business Central
add_action('wp_ajax_bc_oauth_callback', array('Business_Central_Connector', 'handle_oauth_callback'));
add_action('wp_ajax_nopriv_bc_oauth_callback', array('Business_Central_Connector', 'handle_oauth_callback'));

// Activation hook
register_activation_hook(__FILE__, 'bcc_activate');
function bcc_activate() {
    // Create default options
    $default_options = array(
        'base_url' => 'https://api.businesscentral.dynamics.com/',
        'callback_url' => 'https://malmsteypa.pineapple.is/wp-admin/admin-ajax.php?action=bc_oauth_callback',
        'tenant_id' => '',
        'client_id' => '',
        'client_secret' => '',
        'company_id' => '',
        'bc_environment' => '',
        'api_version' => 'v2.0',
        'connection_status' => 'disconnected',
        'access_token' => '',
        // Dokobit defaults (empty to force configuration)
        'dokobit_api_base' => '',
        'dokobit_api_key' => ''
    );
    
    add_option('bcc_settings', $default_options);
    // Create Dokobit tables
    if (class_exists('BCC_Dokobit_Database')) {
        BCC_Dokobit_Database::create_tables();
    }
    // Ensure Dokobit role exists
    if (!get_role('dokobit_company_user')) {
        add_role('dokobit_company_user', 'Dokobit Company User', array('read' => true));
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'bcc_deactivate');
function bcc_deactivate() {
    // Clean up if needed
}

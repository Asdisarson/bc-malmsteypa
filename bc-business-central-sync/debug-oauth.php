<?php
/**
 * OAuth Debug Script
 * 
 * This script helps troubleshoot OAuth implementation issues
 * Run this from the WordPress admin or via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If running from command line, try to load WordPress
    if (php_sapi_name() === 'cli') {
        $wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
        if (file_exists($wp_load_path)) {
            require_once $wp_load_path;
        } else {
            echo "Error: WordPress not found. Please run this from within WordPress.\n";
            exit(1);
        }
    } else {
        exit('Direct access not allowed');
    }
}

echo "<h1>OAuth Debug Information</h1>\n";

// Check if OAuth classes exist
echo "<h2>Class Availability</h2>\n";
if (class_exists('BC_OAuth_Handler')) {
    echo "<p style='color: green;'>✓ BC_OAuth_Handler class exists</p>\n";
} else {
    echo "<p style='color: red;'>✗ BC_OAuth_Handler class not found</p>\n";
}

if (class_exists('BC_OAuth_Settings')) {
    echo "<p style='color: green;'>✓ BC_OAuth_Settings class exists</p>\n";
} else {
    echo "<p style='color: red;'>✗ BC_OAuth_Settings class not found</p>\n";
}

// Check OAuth options
echo "<h2>OAuth Options</h2>\n";
$client_id = get_option('bc_oauth_client_id', '');
$client_secret = get_option('bc_oauth_client_secret', '');
$access_token = get_option('bc_oauth_access_token', '');
$refresh_token = get_option('bc_oauth_refresh_token', '');
$token_expires = get_option('bc_oauth_token_expires', '');

echo "<p><strong>Client ID:</strong> " . (!empty($client_id) ? 'Set (' . substr($client_id, 0, 8) . '...)' : 'Not set') . "</p>\n";
echo "<p><strong>Client Secret:</strong> " . (!empty($client_secret) ? 'Set (' . strlen($client_secret) . ' chars)' : 'Not set') . "</p>\n";
echo "<p><strong>Access Token:</strong> " . (!empty($access_token) ? 'Set (' . strlen($access_token) . ' chars)' : 'Not set') . "</p>\n";
echo "<p><strong>Refresh Token:</strong> " . (!empty($refresh_token) ? 'Set (' . strlen($refresh_token) . ' chars)' : 'Not set') . "</p>\n";
echo "<p><strong>Token Expires:</strong> " . (!empty($token_expires) ? date('Y-m-d H:i:s', $token_expires) : 'Not set') . "</p>\n";

// Check OAuth state
echo "<h2>OAuth State</h2>\n";
$state = get_option('bc_oauth_state', '');
$state_timestamp = get_option('bc_oauth_state_timestamp', '');

echo "<p><strong>State:</strong> " . (!empty($state) ? 'Set (' . substr($state, 0, 8) . '...)' : 'Not set') . "</p>\n";
echo "<p><strong>State Timestamp:</strong> " . (!empty($state_timestamp) ? date('Y-m-d H:i:s', $state_timestamp) : 'Not set') . "</p>\n";

if (!empty($state_timestamp)) {
    $state_age = time() - $state_timestamp;
    echo "<p><strong>State Age:</strong> " . $state_age . " seconds (" . round($state_age / 60, 2) . " minutes)</p>\n";
    if ($state_age > 600) {
        echo "<p style='color: orange;'>⚠ State is expired (>10 minutes old)</p>\n";
    }
}

// Test OAuth handler
echo "<h2>OAuth Handler Test</h2>\n";
if (class_exists('BC_OAuth_Handler')) {
    try {
        $oauth_handler = new BC_OAuth_Handler();
        $status = $oauth_handler->get_status();
        
        echo "<p><strong>Configuration Status:</strong> " . ($status['configured'] ? 'Configured' : 'Not Configured') . "</p>\n";
        echo "<p><strong>Authentication Status:</strong> " . ($status['authenticated'] ? 'Authenticated' : 'Not Authenticated') . "</p>\n";
        echo "<p><strong>Redirect URI:</strong> " . esc_html($status['redirect_uri']) . "</p>\n";
        echo "<p><strong>Scope:</strong> " . esc_html($status['scope']) . "</p>\n";
        
        if ($status['token_expires']) {
            echo "<p><strong>Token Expires:</strong> " . date('Y-m-d H:i:s', $status['token_expires']) . "</p>\n";
            $time_until_expiry = $status['token_expires'] - time();
            echo "<p><strong>Time Until Expiry:</strong> " . $time_until_expiry . " seconds (" . round($time_until_expiry / 60, 2) . " minutes)</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error creating OAuth handler: " . esc_html($e->getMessage()) . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Cannot test OAuth handler - class not found</p>\n";
}

// Check AJAX actions
echo "<h2>AJAX Actions</h2>\n";
global $wp_filter;
$ajax_actions = array(
    'wp_ajax_bc_oauth_initiate',
    'wp_ajax_bc_oauth_callback',
    'wp_ajax_bc_oauth_refresh',
    'wp_ajax_bc_oauth_revoke',
    'wp_ajax_nopriv_bc_oauth_callback'
);

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "<p style='color: green;'>✓ {$action} is registered</p>\n";
    } else {
        echo "<p style='color: red;'>✗ {$action} is not registered</p>\n";
    }
}

// Check admin menu
echo "<h2>Admin Menu</h2>\n";
global $menu;
$menu_found = false;
foreach ($menu as $item) {
    if (isset($item[2]) && $item[2] === 'bc-business-central-sync') {
        $menu_found = true;
        echo "<p style='color: green;'>✓ Main menu found: " . esc_html($item[0]) . "</p>\n";
        break;
    }
}

if (!$menu_found) {
    echo "<p style='color: red;'>✗ Main menu not found</p>\n";
}

// Check submenu
global $submenu;
if (isset($submenu['bc-business-central-sync'])) {
    echo "<p style='color: green;'>✓ Submenu exists</p>\n";
    foreach ($submenu['bc-business-central-sync'] as $submenu_item) {
        echo "<p>  - " . esc_html($submenu_item[0]) . " (slug: " . esc_html($submenu_item[2]) . ")</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Submenu not found</p>\n";
}

// Check WordPress debug mode
echo "<h2>WordPress Debug</h2>\n";
echo "<p><strong>WP_DEBUG:</strong> " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . "</p>\n";
echo "<p><strong>WP_DEBUG_LOG:</strong> " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . "</p>\n";
echo "<p><strong>WP_DEBUG_DISPLAY:</strong> " . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'Enabled' : 'Disabled') . "</p>\n";

// Check error log location
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        echo "<p><strong>Debug Log File:</strong> " . esc_html($log_file) . " (exists)</p>\n";
        $log_size = filesize($log_file);
        echo "<p><strong>Log File Size:</strong> " . size_format($log_size) . "</p>\n";
    } else {
        echo "<p><strong>Debug Log File:</strong> " . esc_html($log_file) . " (does not exist)</p>\n";
    }
}

// Check PHP error log
echo "<h2>PHP Error Log</h2>\n";
$php_error_log = ini_get('error_log');
if ($php_error_log) {
    echo "<p><strong>PHP Error Log:</strong> " . esc_html($php_error_log) . "</p>\n";
    if (file_exists($php_error_log)) {
        $log_size = filesize($php_error_log);
        echo "<p><strong>PHP Log Size:</strong> " . size_format($log_size) . "</p>\n";
    }
} else {
    echo "<p><strong>PHP Error Log:</strong> Not configured</p>\n";
}

// Check recent errors
echo "<h2>Recent Errors</h2>\n";
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && file_exists(WP_CONTENT_DIR . '/debug.log')) {
    $log_content = file_get_contents(WP_CONTENT_DIR . '/debug.log');
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20); // Last 20 lines
    
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>\n";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'BC OAuth') !== false || strpos($line, 'OAuth') !== false) {
            echo esc_html($line) . "\n";
        }
    }
    echo "</pre>\n";
} else {
    echo "<p>No debug log available</p>\n";
}

echo "<hr>\n";
echo "<p><em>Debug script completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>

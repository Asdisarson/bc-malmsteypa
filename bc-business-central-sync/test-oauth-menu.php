<?php
/**
 * Test OAuth Menu Script
 * 
 * Run this to check if OAuth menu is properly registered
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

echo "<h1>OAuth Menu Test</h1>\n";

// Check if we're on an admin page
if (!is_admin()) {
    echo "<p style='color: red;'>This script should be run from WordPress admin area.</p>\n";
    echo "<p><a href='" . admin_url('admin.php?page=test-oauth-menu') . "'>Click here to run from admin</a></p>\n";
    return;
}

// Check menu structure
echo "<h2>Admin Menu Structure</h2>\n";
global $menu, $submenu;

// Look for BC Sync main menu
$bc_menu_found = false;
if (!empty($menu)) {
    foreach ($menu as $item) {
        if (isset($item[2]) && $item[2] === 'bc-business-central-sync') {
            $bc_menu_found = true;
            echo "<p style='color: green;'>✓ Main BC Sync menu found: " . esc_html($item[0]) . "</p>\n";
            break;
        }
    }
}

if (!$bc_menu_found) {
    echo "<p style='color: red;'>✗ Main BC Sync menu not found</p>\n";
} else {
    // Check submenu
    echo "<h3>BC Sync Submenus:</h3>\n";
    if (isset($submenu['bc-business-central-sync'])) {
        echo "<ul>\n";
        foreach ($submenu['bc-business-central-sync'] as $submenu_item) {
            echo "<li>" . esc_html($submenu_item[0]) . " (slug: " . esc_html($submenu_item[2]) . ")";
            if ($submenu_item[2] === 'bc-oauth-settings') {
                echo " <strong style='color: green;'>← OAuth Settings Found!</strong>";
            }
            echo "</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: red;'>✗ No submenus found for BC Sync</p>\n";
    }
}

// Check OAuth classes
echo "<h2>OAuth Classes</h2>\n";
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

// Check if OAuth options exist
echo "<h2>OAuth Configuration</h2>\n";
$client_id = get_option('bc_oauth_client_id', '');
$client_secret = get_option('bc_oauth_client_secret', '');

echo "<p><strong>Client ID:</strong> " . (!empty($client_id) ? 'Set (' . substr($client_id, 0, 8) . '...)' : 'Not set') . "</p>\n";
echo "<p><strong>Client Secret:</strong> " . (!empty($client_secret) ? 'Set (' . strlen($client_secret) . ' chars)' : 'Not set') . "</p>\n";

// Test OAuth handler instantiation
echo "<h2>OAuth Handler Test</h2>\n";
try {
    if (class_exists('BC_OAuth_Handler')) {
        $oauth_handler = new BC_OAuth_Handler();
        $status = $oauth_handler->get_status();
        echo "<p style='color: green;'>✓ OAuth Handler instantiated successfully</p>\n";
        echo "<p><strong>Configured:</strong> " . ($status['configured'] ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Authenticated:</strong> " . ($status['authenticated'] ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Redirect URI:</strong> " . esc_html($status['redirect_uri']) . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Cannot test OAuth handler - class not found</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error creating OAuth handler: " . esc_html($e->getMessage()) . "</p>\n";
}

// Check current user permissions
echo "<h2>Current User Permissions</h2>\n";
$current_user = wp_get_current_user();
echo "<p><strong>User:</strong> " . esc_html($current_user->display_name) . "</p>\n";
echo "<p><strong>Roles:</strong> " . esc_html(implode(', ', $current_user->roles)) . "</p>\n";
echo "<p><strong>Can manage WooCommerce:</strong> " . (current_user_can('manage_woocommerce') ? 'Yes' : 'No') . "</p>\n";

echo "<hr>\n";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";

// Add direct link to OAuth settings if found
if ($bc_menu_found && isset($submenu['bc-business-central-sync'])) {
    $oauth_found = false;
    foreach ($submenu['bc-business-central-sync'] as $submenu_item) {
        if ($submenu_item[2] === 'bc-oauth-settings') {
            $oauth_found = true;
            break;
        }
    }
    
    if ($oauth_found) {
        echo "<p><strong><a href='" . admin_url('admin.php?page=bc-oauth-settings') . "' style='color: blue; font-size: 16px;'>→ Go to OAuth Settings Page</a></strong></p>\n";
    }
}
?>

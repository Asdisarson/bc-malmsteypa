<?php
/**
 * Simple Menu Test
 * 
 * Test if OAuth Settings page is registered correctly
 */

// If running from web browser
if (!defined('WP_CLI') && !defined('ABSPATH')) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<style>body { font-family: monospace; line-height: 1.6; }</style>";
}

echo "<h1>Menu Registration Test</h1>\n";

// Check if WordPress is loaded
if (!function_exists('admin_url')) {
    echo "<p style='color: red;'>Error: WordPress not loaded. Please access this file through WordPress.</p>\n";
    exit;
}

// Check if BC_OAuth_Settings class exists
if (class_exists('BC_OAuth_Settings')) {
    echo "<p style='color: green;'>✓ BC_OAuth_Settings class is available</p>\n";
    
    // Try to instantiate it
    try {
        $oauth_settings = new BC_OAuth_Settings();
        echo "<p style='color: green;'>✓ BC_OAuth_Settings instantiated successfully</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error instantiating BC_OAuth_Settings: " . esc_html($e->getMessage()) . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ BC_OAuth_Settings class not found</p>\n";
    
    // Check if autoloader is working
    if (class_exists('BC_Autoloader')) {
        echo "<p>BC_Autoloader exists - checking load paths...</p>\n";
    } else {
        echo "<p style='color: red;'>BC_Autoloader not found</p>\n";
    }
}

// Check WordPress submenu registration
global $submenu;
echo "<h2>Current Admin Submenus</h2>\n";

if (isset($submenu['bc-business-central-sync'])) {
    echo "<p style='color: green;'>✓ BC Sync submenu exists</p>\n";
    echo "<ul>\n";
    foreach ($submenu['bc-business-central-sync'] as $submenu_item) {
        echo "<li>" . esc_html($submenu_item[0]) . " (slug: " . esc_html($submenu_item[2]) . ")";
        if ($submenu_item[2] === 'bc-oauth-settings') {
            echo " <strong style='color: green;'>← Found!</strong>";
        }
        echo "</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p style='color: red;'>✗ BC Sync submenu not found</p>\n";
}

// Check admin menu actions
echo "<h2>Admin Menu Actions</h2>\n";
if (function_exists('has_action')) {
    $admin_menu_actions = array(
        'admin_menu' => has_action('admin_menu'),
        'admin_init' => has_action('admin_init')
    );
    
    foreach ($admin_menu_actions as $action => $count) {
        if ($count > 0) {
            echo "<p style='color: green;'>✓ {$action}: {$count} callbacks</p>\n";
        } else {
            echo "<p style='color: red;'>✗ {$action}: no callbacks</p>\n";
        }
    }
}

echo "<hr>\n";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>

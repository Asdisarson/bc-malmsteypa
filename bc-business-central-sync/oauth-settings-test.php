<?php
/**
 * OAuth Settings Test Script
 * 
 * This script tests OAuth settings saving functionality
 * Add this as a temporary admin page to test settings
 */

// Add temporary admin page for OAuth settings testing
add_action('admin_menu', function() {
    add_submenu_page(
        'bc-business-central-sync',
        'OAuth Settings Test',
        'Settings Test',
        'manage_woocommerce',
        'bc-oauth-settings-test',
        'bc_oauth_settings_test_page'
    );
});

function bc_oauth_settings_test_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Handle test form submission
    if (isset($_POST['test_oauth_save'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'test_oauth_nonce')) {
            $client_id = sanitize_text_field($_POST['test_client_id']);
            $client_secret = sanitize_text_field($_POST['test_client_secret']);
            
            // Validate and save
            $client_id_valid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id);
            $client_secret_valid = strlen($client_secret) >= 16;
            
            if ($client_id_valid) {
                update_option('bc_oauth_client_id', $client_id);
                echo '<div class="notice notice-success"><p>✓ Client ID saved successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>✗ Client ID validation failed (must be GUID format)</p></div>';
            }
            
            if ($client_secret_valid) {
                update_option('bc_oauth_client_secret', $client_secret);
                echo '<div class="notice notice-success"><p>✓ Client Secret saved successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>✗ Client Secret validation failed (must be 16+ characters)</p></div>';
            }
        }
    }
    
    echo '<div class="wrap">';
    echo '<h1>OAuth Settings Test</h1>';
    
    // Current values
    $current_client_id = get_option('bc_oauth_client_id', '');
    $current_client_secret = get_option('bc_oauth_client_secret', '');
    
    echo '<h2>Current Settings</h2>';
    echo '<p><strong>Client ID:</strong> ' . (!empty($current_client_id) ? esc_html($current_client_id) : 'Not set') . '</p>';
    echo '<p><strong>Client Secret:</strong> ' . (!empty($current_client_secret) ? 'Set (' . strlen($current_client_secret) . ' chars)' : 'Not set') . '</p>';
    
    // Test form
    echo '<h2>Test Settings Form</h2>';
    echo '<form method="post" action="">';
    wp_nonce_field('test_oauth_nonce');
    echo '<input type="hidden" name="test_oauth_save" value="1" />';
    
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="test_client_id">Client ID (GUID format)</label></th>';
    echo '<td><input type="text" id="test_client_id" name="test_client_id" value="' . esc_attr($current_client_id) . '" class="regular-text" placeholder="12345678-1234-1234-1234-123456789012" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="test_client_secret">Client Secret (16+ chars)</label></th>';
    echo '<td><input type="text" id="test_client_secret" name="test_client_secret" value="' . esc_attr($current_client_secret) . '" class="regular-text" placeholder="Your-client-secret-here" /></td>';
    echo '</tr>';
    echo '</table>';
    
    echo '<p class="submit"><input type="submit" class="button button-primary" value="Save Test Settings" /></p>';
    echo '</form>';
    
    // Test OAuth handler
    echo '<h2>OAuth Handler Test</h2>';
    if (class_exists('BC_OAuth_Handler')) {
        try {
            global $bc_oauth_handler_instance;
            if (!$bc_oauth_handler_instance) {
                $bc_oauth_handler_instance = new BC_OAuth_Handler();
            }
            $oauth_handler = $bc_oauth_handler_instance;
            
            $status = $oauth_handler->get_status();
            echo '<p><strong>Handler Status:</strong></p>';
            echo '<ul>';
            echo '<li>Configured: ' . ($status['configured'] ? '✓ Yes' : '✗ No') . '</li>';
            echo '<li>Authenticated: ' . ($status['authenticated'] ? '✓ Yes' : '✗ No') . '</li>';
            echo '<li>Redirect URI: ' . esc_html($status['redirect_uri']) . '</li>';
            echo '</ul>';
            
        } catch (Exception $e) {
            echo '<p style="color: red;">Error: ' . esc_html($e->getMessage()) . '</p>';
        }
    } else {
        echo '<p style="color: red;">BC_OAuth_Handler class not found</p>';
    }
    
    // Clear settings button
    echo '<h2>Clear Settings</h2>';
    if (isset($_POST['clear_settings'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'clear_oauth_nonce')) {
            delete_option('bc_oauth_client_id');
            delete_option('bc_oauth_client_secret');
            delete_option('bc_oauth_access_token');
            delete_option('bc_oauth_refresh_token');
            delete_option('bc_oauth_token_expires');
            echo '<div class="notice notice-success"><p>✓ All OAuth settings cleared!</p></div>';
        }
    }
    
    echo '<form method="post" action="">';
    wp_nonce_field('clear_oauth_nonce');
    echo '<input type="hidden" name="clear_settings" value="1" />';
    echo '<p><input type="submit" class="button button-secondary" value="Clear All OAuth Settings" onclick="return confirm(\'Are you sure you want to clear all OAuth settings?\');" /></p>';
    echo '</form>';
    
    echo '</div>';
}
?>

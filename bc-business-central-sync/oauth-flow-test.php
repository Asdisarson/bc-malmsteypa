<?php
/**
 * OAuth Flow Diagnostic Script
 * 
 * This script tests the complete OAuth flow to identify issues
 * Add this as a temporary admin page to test OAuth functionality
 */

// Add temporary admin page for OAuth testing
add_action('admin_menu', function() {
    add_submenu_page(
        'bc-business-central-sync',
        'OAuth Flow Test',
        'OAuth Test',
        'manage_woocommerce',
        'bc-oauth-flow-test',
        'bc_oauth_flow_test_page'
    );
});

function bc_oauth_flow_test_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    echo '<div class="wrap">';
    echo '<h1>OAuth Flow Diagnostic Test</h1>';
    
    // Test 1: Check OAuth Handler Class
    echo '<h2>1. OAuth Handler Class Test</h2>';
    if (class_exists('BC_OAuth_Handler')) {
        echo '<p style="color: green;">✓ BC_OAuth_Handler class exists</p>';
        
        try {
            global $bc_oauth_handler_instance;
            if (!$bc_oauth_handler_instance) {
                $bc_oauth_handler_instance = new BC_OAuth_Handler();
            }
            $oauth_handler = $bc_oauth_handler_instance;
            echo '<p style="color: green;">✓ OAuth Handler instantiated successfully</p>';
            
            // Test configuration
            $status = $oauth_handler->get_status();
            echo '<p><strong>Configured:</strong> ' . ($status['configured'] ? 'Yes' : 'No') . '</p>';
            echo '<p><strong>Authenticated:</strong> ' . ($status['authenticated'] ? 'Yes' : 'No') . '</p>';
            echo '<p><strong>Redirect URI:</strong> ' . esc_html($status['redirect_uri']) . '</p>';
            
        } catch (Exception $e) {
            echo '<p style="color: red;">✗ Error: ' . esc_html($e->getMessage()) . '</p>';
        }
    } else {
        echo '<p style="color: red;">✗ BC_OAuth_Handler class not found</p>';
    }
    
    // Test 2: Check AJAX Actions
    echo '<h2>2. AJAX Actions Test</h2>';
    global $wp_filter;
    $oauth_actions = [
        'wp_ajax_bc_oauth_initiate',
        'wp_ajax_bc_oauth_callback', 
        'wp_ajax_bc_oauth_refresh',
        'wp_ajax_bc_oauth_revoke',
        'wp_ajax_nopriv_bc_oauth_callback'
    ];
    
    foreach ($oauth_actions as $action) {
        if (isset($wp_filter[$action]) && !empty($wp_filter[$action]->callbacks)) {
            echo '<p style="color: green;">✓ ' . $action . ' is registered</p>';
        } else {
            echo '<p style="color: red;">✗ ' . $action . ' is NOT registered</p>';
        }
    }
    
    // Test 3: Check Configuration
    echo '<h2>3. OAuth Configuration Test</h2>';
    $client_id = get_option('bc_oauth_client_id', '');
    $client_secret = get_option('bc_oauth_client_secret', '');
    
    echo '<p><strong>Client ID:</strong> ' . (!empty($client_id) ? 'Set (' . substr($client_id, 0, 8) . '...)' : 'Not set') . '</p>';
    echo '<p><strong>Client Secret:</strong> ' . (!empty($client_secret) ? 'Set (' . strlen($client_secret) . ' chars)' : 'Not set') . '</p>';
    
    if (empty($client_id) || empty($client_secret)) {
        echo '<div class="notice notice-error"><p><strong>Configuration Required:</strong> Please set your OAuth Client ID and Client Secret in the OAuth Settings page.</p></div>';
    }
    
    // Test 4: JavaScript Test
    echo '<h2>4. JavaScript AJAX Test</h2>';
    echo '<button type="button" id="test-oauth-ajax" class="button button-secondary">Test OAuth AJAX Call</button>';
    echo '<div id="ajax-test-result" style="margin-top: 10px; padding: 10px; display: none;"></div>';
    
    // Test 5: Manual OAuth Initiation Test
    if (!empty($client_id) && !empty($client_secret)) {
        echo '<h2>5. Manual OAuth Test</h2>';
        echo '<button type="button" id="manual-oauth-test" class="button button-primary">Start Manual OAuth Test</button>';
        echo '<div id="manual-oauth-result" style="margin-top: 10px; padding: 10px; display: none;"></div>';
    }
    
    echo '</div>';
    
    // Add JavaScript for testing
    ?>
    <script type="text/javascript">
    var ajaxurl = ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>';
    
    jQuery(document).ready(function($) {
        // Test AJAX endpoint
        $('#test-oauth-ajax').on('click', function() {
            var button = $(this);
            var resultDiv = $('#ajax-test-result');
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.show().html('Testing AJAX connection...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bc_oauth_initiate',
                    nonce: '<?php echo wp_create_nonce('bc_oauth_initiate'); ?>'
                },
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response.success) {
                        resultDiv.html('<div style="color: green;"><strong>✓ AJAX Success!</strong><br>' + 
                                     'Auth URL: ' + response.data.auth_url + '</div>');
                    } else {
                        resultDiv.html('<div style="color: orange;"><strong>⚠ AJAX Response (Expected Error):</strong><br>' + 
                                     JSON.stringify(response.data) + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr, status, error);
                    resultDiv.html('<div style="color: red;"><strong>✗ AJAX Error:</strong><br>' + 
                                 'Status: ' + status + '<br>' +
                                 'Error: ' + error + '<br>' +
                                 'Response: ' + xhr.responseText + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test OAuth AJAX Call');
                }
            });
        });
        
        // Manual OAuth test
        $('#manual-oauth-test').on('click', function() {
            var button = $(this);
            var resultDiv = $('#manual-oauth-result');
            
            button.prop('disabled', true).text('Starting OAuth...');
            resultDiv.show().html('Initiating OAuth flow...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bc_oauth_initiate',
                    nonce: '<?php echo wp_create_nonce('bc_oauth_initiate'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.auth_url) {
                        resultDiv.html('<div style="color: green;"><strong>✓ OAuth URL Generated!</strong><br>' +
                                     '<a href="' + response.data.auth_url + '" target="_blank" class="button button-primary">Open OAuth URL</a><br>' +
                                     '<small>URL: ' + response.data.auth_url + '</small></div>');
                    } else {
                        resultDiv.html('<div style="color: red;"><strong>✗ OAuth Failed:</strong><br>' + 
                                     JSON.stringify(response) + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    resultDiv.html('<div style="color: red;"><strong>✗ OAuth Error:</strong><br>' + 
                                 'Status: ' + status + '<br>Error: ' + error + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Start Manual OAuth Test');
                }
            });
        });
    });
    </script>
    <?php
}
?>

<?php
/**
 * OAuth Settings Admin Display
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/admin/partials
 * @since      1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get OAuth handler instance
$oauth_handler = new BC_OAuth_Handler();
$oauth_status = $oauth_handler->get_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="bc-oauth-settings-container">
        <!-- OAuth Configuration Section -->
        <div class="bc-oauth-section">
            <h2>OAuth Configuration</h2>
            <p>Configure your Microsoft Azure application credentials for Business Central integration.</p>
            
            <form method="post" action="options.php" class="bc-oauth-form">
                <?php
                settings_fields('bc_oauth_settings');
                do_settings_sections('bc_oauth_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bc_oauth_client_id">Client ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="bc_oauth_client_id" 
                                   name="bc_oauth_client_id" 
                                   value="<?php echo esc_attr(get_option('bc_oauth_client_id', '')); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">Your Microsoft Azure application (client) ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bc_oauth_client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="bc_oauth_client_secret" 
                                   name="bc_oauth_client_secret" 
                                   value="<?php echo esc_attr(get_option('bc_oauth_client_secret', '')); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">Your Microsoft Azure application client secret</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redirect URI</th>
                        <td>
                            <code><?php echo esc_html($oauth_status['redirect_uri']); ?></code>
                            <p class="description">Use this URL as the redirect URI in your Azure app configuration</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Scope</th>
                        <td>
                            <code><?php echo esc_html($oauth_status['scope']); ?></code>
                            <p class="description">Required permissions for Business Central API access</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save OAuth Settings'); ?>
            </form>
        </div>

        <!-- OAuth Status Section -->
        <div class="bc-oauth-section">
            <h2>OAuth Status</h2>
            
            <div class="bc-oauth-status-grid">
                <div class="bc-oauth-status-item">
                    <strong>Configuration:</strong>
                    <span class="bc-status-indicator <?php echo $oauth_status['configured'] ? 'success' : 'error'; ?>">
                        <?php echo $oauth_status['configured'] ? '✓ Configured' : '✗ Not Configured'; ?>
                    </span>
                </div>
                
                <div class="bc-oauth-status-item">
                    <strong>Authentication:</strong>
                    <span class="bc-status-indicator <?php echo $oauth_status['authenticated'] ? 'success' : 'warning'; ?>">
                        <?php echo $oauth_status['authenticated'] ? '✓ Authenticated' : '⚠ Not Authenticated'; ?>
                    </span>
                </div>
                
                <?php if ($oauth_status['token_expires']): ?>
                <div class="bc-oauth-status-item">
                    <strong>Token Expires:</strong>
                    <span><?php echo esc_html(date('Y-m-d H:i:s', $oauth_status['token_expires'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- OAuth Actions Section -->
        <div class="bc-oauth-section">
            <h2>OAuth Actions</h2>
            
            <?php if ($oauth_status['configured']): ?>
                <?php if (!$oauth_status['authenticated']): ?>
                    <div class="bc-oauth-action">
                        <button type="button" id="bc-oauth-initiate" class="button button-primary">
                            <span class="dashicons dashicons-external"></span>
                            Start OAuth Authorization
                        </button>
                        <p class="description">Click to begin the OAuth authorization flow with Microsoft</p>
                    </div>
                <?php else: ?>
                    <div class="bc-oauth-action">
                        <button type="button" id="bc-oauth-refresh" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            Refresh Access Token
                        </button>
                        <p class="description">Manually refresh the access token if needed</p>
                    </div>
                    
                    <div class="bc-oauth-action">
                        <button type="button" id="bc-oauth-revoke" class="button button-link-delete">
                            <span class="dashicons dashicons-trash"></span>
                            Revoke All Tokens
                        </button>
                        <p class="description">Remove all stored OAuth tokens (requires re-authorization)</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bc-oauth-action">
                    <p class="description">Please configure your OAuth credentials above before proceeding.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- OAuth Instructions Section -->
        <div class="bc-oauth-section">
            <h2>Setup Instructions</h2>
            
            <div class="bc-oauth-instructions">
                <h3>1. Create Azure Application</h3>
                <ol>
                    <li>Go to <a href="https://portal.azure.com" target="_blank">Azure Portal</a></li>
                    <li>Navigate to "Azure Active Directory" → "App registrations"</li>
                    <li>Click "New registration"</li>
                    <li>Enter a name for your application</li>
                    <li>Select "Accounts in this organizational directory only"</li>
                    <li>Click "Register"</li>
                </ol>

                <h3>2. Configure Redirect URI</h3>
                <ol>
                    <li>In your app registration, go to "Authentication"</li>
                    <li>Click "Add a platform" → "Web"</li>
                    <li>Add this redirect URI: <code><?php echo esc_html($oauth_status['redirect_uri']); ?></code></li>
                    <li>Save the changes</li>
                </ol>

                <h3>3. Get Client Credentials</h3>
                <ol>
                    <li>Go to "Certificates & secrets"</li>
                    <li>Click "New client secret"</li>
                    <li>Add a description and select expiration</li>
                    <li>Copy the generated secret value</li>
                    <li>Go to "Overview" and copy the Application (client) ID</li>
                </ol>

                <h3>4. Configure API Permissions</h3>
                <ol>
                    <li>Go to "API permissions"</li>
                    <li>Click "Add a permission"</li>
                    <li>Select "Business Central" → "Delegated permissions"</li>
                    <li>Select the required permissions (e.g., "Company.Read.All", "Item.Read.All")</li>
                    <li>Click "Add permissions"</li>
                    <li>Click "Grant admin consent" for your organization</li>
                </ol>

                <h3>5. Complete Setup</h3>
                <ol>
                    <li>Enter the Client ID and Client Secret above</li>
                    <li>Save the settings</li>
                    <li>Click "Start OAuth Authorization" to begin</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // OAuth Initiate
    $('#bc-oauth-initiate').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Starting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bc_oauth_initiate',
                nonce: '<?php echo wp_create_nonce('bc_oauth_initiate'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to Microsoft for authorization
                    window.location.href = response.data.auth_url;
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Failed to initiate OAuth flow. Please try again.');
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    // OAuth Refresh
    $('#bc-oauth-refresh').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Refreshing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bc_oauth_refresh',
                nonce: '<?php echo wp_create_nonce('bc_oauth_refresh'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Token refreshed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Failed to refresh token. Please try again.');
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    // OAuth Revoke
    $('#bc-oauth-revoke').on('click', function() {
        if (!confirm('Are you sure you want to revoke all OAuth tokens? This will require re-authorization.')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Revoking...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bc_oauth_revoke',
                nonce: '<?php echo wp_create_nonce('bc_oauth_revoke'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Tokens revoked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Failed to revoke tokens. Please try again.');
                button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<style>
.bc-oauth-settings-container {
    max-width: 800px;
}

.bc-oauth-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.bc-oauth-section h2 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.bc-oauth-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.bc-oauth-status-item {
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 4px solid #ddd;
}

.bc-status-indicator.success {
    color: #46b450;
}

.bc-status-indicator.error {
    color: #dc3232;
}

.bc-status-indicator.warning {
    color: #ffb900;
}

.bc-oauth-action {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.bc-oauth-action button {
    margin-right: 10px;
}

.bc-oauth-action .dashicons {
    margin-right: 5px;
    vertical-align: middle;
}

.bc-oauth-instructions {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
}

.bc-oauth-instructions h3 {
    margin-top: 20px;
    margin-bottom: 10px;
    color: #23282d;
}

.bc-oauth-instructions h3:first-child {
    margin-top: 0;
}

.bc-oauth-instructions ol {
    margin-left: 20px;
}

.bc-oauth-instructions li {
    margin-bottom: 8px;
}

.bc-oauth-instructions code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #ddd;
}

.bc-oauth-form .form-table th {
    width: 200px;
}
</style>

<?php
/**
 * Main BC Sync Admin Display
 * 
 * This is the comprehensive overview page with settings, OAuth, and testing
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle OAuth settings form submission
if (isset($_POST['bc_oauth_action']) && $_POST['bc_oauth_action'] === 'save_settings') {
    // Check nonce
    if (wp_verify_nonce($_POST['_wpnonce'], 'bc_oauth_settings_nonce')) {
        // Check permissions
        if (current_user_can('manage_woocommerce')) {
            $saved = false;
            $errors = array();
            
            // Save Client ID
            if (isset($_POST['bc_oauth_client_id'])) {
                $client_id = sanitize_text_field($_POST['bc_oauth_client_id']);
                if (!empty($client_id)) {
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id)) {
                        update_option('bc_oauth_client_id', $client_id);
                        $saved = true;
                    } else {
                        $errors[] = 'Client ID must be in GUID format (e.g., 12345678-1234-1234-1234-123456789012)';
                    }
                }
            }
            
            // Save Client Secret
            if (isset($_POST['bc_oauth_client_secret'])) {
                $client_secret = sanitize_text_field($_POST['bc_oauth_client_secret']);
                if (!empty($client_secret)) {
                    if (strlen($client_secret) >= 16) {
                        update_option('bc_oauth_client_secret', $client_secret);
                        $saved = true;
                    } else {
                        $errors[] = 'Client Secret must be at least 16 characters long';
                    }
                }
            }
            
            if ($saved && empty($errors)) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ OAuth settings saved successfully!</p></div>';
            } elseif (!empty($errors)) {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Validation errors:<br>' . implode('<br>', $errors) . '</p></div>';
            }
        }
    }
}

// Get OAuth handler instance
global $bc_oauth_handler_instance;
if (!$bc_oauth_handler_instance && class_exists('BC_OAuth_Handler')) {
    $bc_oauth_handler_instance = new BC_OAuth_Handler();
}
$oauth_status = $bc_oauth_handler_instance ? $bc_oauth_handler_instance->get_status() : array('configured' => false, 'authenticated' => false);
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?> - Overview</h1>
	
	<!-- Status Overview -->
	<div class="bc-status-overview">
		<h2><?php _e( 'System Status', 'bc-business-central-sync' ); ?></h2>
		
		<div class="bc-status-grid">
			<div class="bc-status-card">
				<h3>🔗 WooCommerce</h3>
				<?php if ( class_exists( 'WooCommerce' ) ): ?>
					<span class="status-good">✅ Active (v<?php echo WC_VERSION; ?>)</span>
				<?php else: ?>
					<span class="status-bad">❌ Not installed</span>
				<?php endif; ?>
			</div>
			
			<div class="bc-status-card">
				<h3>🔐 OAuth Configuration</h3>
				<?php if ( $oauth_status['configured'] ): ?>
					<span class="status-good">✅ Configured</span>
				<?php else: ?>
					<span class="status-bad">❌ Not configured</span>
				<?php endif; ?>
			</div>
			
			<div class="bc-status-card">
				<h3>🔑 OAuth Authentication</h3>
				<?php if ( $oauth_status['authenticated'] ): ?>
					<span class="status-good">✅ Authenticated</span>
				<?php else: ?>
					<span class="status-warning">⚠️ Not authenticated</span>
				<?php endif; ?>
			</div>
			
			<div class="bc-status-card">
				<h3>📊 Simple Pricing</h3>
				<?php if ( class_exists( 'BC_Simple_Pricing' ) ): ?>
					<span class="status-good">✅ Active</span>
				<?php else: ?>
					<span class="status-bad">❌ Not found</span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- OAuth Configuration -->
	<div class="bc-oauth-config">
		<h2><?php _e( 'OAuth Configuration', 'bc-business-central-sync' ); ?></h2>
		<p>Configure your Microsoft Azure application credentials for Business Central integration.</p>
		
		<form method="post" action="" class="bc-oauth-form">
			<?php wp_nonce_field('bc_oauth_settings_nonce'); ?>
			<input type="hidden" name="bc_oauth_action" value="save_settings" />
			
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
							   placeholder="12345678-1234-1234-1234-123456789012" />
						<p class="description">Your Microsoft Azure application (client) ID in GUID format</p>
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
							   placeholder="Your Azure client secret" />
						<p class="description">Your Microsoft Azure application client secret (16+ characters)</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Redirect URI</th>
					<td>
						<code><?php echo esc_html($oauth_status['redirect_uri'] ?? admin_url('admin-ajax.php?action=bc_oauth_callback')); ?></code>
						<p class="description">Use this URL in your Azure app configuration</p>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Save OAuth Settings">
			</p>
		</form>
	</div>

	<!-- OAuth Actions -->
	<div class="bc-oauth-actions">
		<h2><?php _e( 'OAuth Authentication', 'bc-business-central-sync' ); ?></h2>
		
		<?php if ($oauth_status['configured']): ?>
			<?php if (!$oauth_status['authenticated']): ?>
				<div class="bc-oauth-action">
					<button type="button" id="bc-oauth-initiate" class="button button-primary">
						<span class="dashicons dashicons-external"></span>
						Start OAuth Authorization
					</button>
					<p class="description">Click to begin OAuth authorization with Microsoft</p>
				</div>
			<?php else: ?>
				<div class="bc-oauth-action">
					<p class="oauth-success">✅ <strong>OAuth is authenticated and ready!</strong></p>
					<?php if (isset($oauth_status['token_expires'])): ?>
						<p class="description">Token expires: <?php echo date('Y-m-d H:i:s', $oauth_status['token_expires']); ?></p>
					<?php endif; ?>
				</div>
				
				<div class="bc-oauth-action">
					<button type="button" id="bc-oauth-refresh" class="button button-secondary">
						<span class="dashicons dashicons-update"></span>
						Refresh Access Token
					</button>
					<button type="button" id="bc-oauth-revoke" class="button button-link-delete" style="margin-left: 10px;">
						<span class="dashicons dashicons-trash"></span>
						Revoke Tokens
					</button>
				</div>
			<?php endif; ?>
		<?php else: ?>
			<div class="bc-oauth-action">
				<p class="oauth-warning">⚠️ Please configure your OAuth credentials above before proceeding.</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Testing & Actions -->
	<div class="bc-testing-actions">
		<h2><?php _e( 'Testing & Synchronization', 'bc-business-central-sync' ); ?></h2>
		
		<div class="bc-action-buttons">
			<button type="button" id="bc-test-connection" class="button button-secondary">
				<span class="dashicons dashicons-admin-links"></span>
				<?php _e( 'Test API Connection', 'bc-business-central-sync' ); ?>
			</button>
			
			<button type="button" id="bc-sync-products" class="button button-primary">
				<span class="dashicons dashicons-products"></span>
				<?php _e( 'Sync Products', 'bc-business-central-sync' ); ?>
			</button>
			
			<button type="button" id="bc-sync-pricelists" class="button button-primary">
				<span class="dashicons dashicons-money-alt"></span>
				<?php _e( 'Sync Pricelists', 'bc-business-central-sync' ); ?>
			</button>
		</div>
		
		<div id="bc-action-status" class="bc-action-status" style="display: none;">
			<div class="bc-status-message"></div>
			<div class="bc-status-progress" style="display: none;">
				<div class="spinner is-active"></div>
				<span><?php _e( 'Processing...', 'bc-business-central-sync' ); ?></span>
			</div>
		</div>
	</div>

	<!-- System Information -->
	<div class="bc-system-info">
		<h2><?php _e( 'System Information', 'bc-business-central-sync' ); ?></h2>
		
		<table class="widefat">
			<tr>
				<th><?php _e( 'WordPress Version:', 'bc-business-central-sync' ); ?></th>
				<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
			</tr>
			<tr>
				<th><?php _e( 'WooCommerce Version:', 'bc-business-central-sync' ); ?></th>
				<td>
					<?php 
					if ( class_exists( 'WooCommerce' ) ) {
						echo esc_html( WC_VERSION );
					} else {
						echo __( 'Not installed', 'bc-business-central-sync' );
					}
					?>
				</td>
			</tr>
			<tr>
				<th><?php _e( 'PHP Version:', 'bc-business-central-sync' ); ?></th>
				<td><?php echo esc_html( PHP_VERSION ); ?></td>
			</tr>
			<tr>
				<th><?php _e( 'Plugin Version:', 'bc-business-central-sync' ); ?></th>
				<td><?php echo esc_html( defined( 'BC_BUSINESS_CENTRAL_SYNC_VERSION' ) ? BC_BUSINESS_CENTRAL_SYNC_VERSION : '1.0.0' ); ?></td>
			</tr>
			<tr>
				<th><?php _e( 'Current User:', 'bc-business-central-sync' ); ?></th>
				<td><?php echo esc_html( wp_get_current_user()->display_name ); ?> (<?php echo esc_html( implode( ', ', wp_get_current_user()->roles ) ); ?>)</td>
			</tr>
		</table>
	</div>
</div>

<style>
.bc-status-overview,
.bc-oauth-config,
.bc-oauth-actions,
.bc-testing-actions,
.bc-system-info {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.bc-status-overview h2,
.bc-oauth-config h2,
.bc-oauth-actions h2,
.bc-testing-actions h2,
.bc-system-info h2 {
	margin-top: 0;
	color: #23282d;
	border-bottom: 1px solid #eee;
	padding-bottom: 10px;
}

.bc-status-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
	margin-top: 15px;
}

.bc-status-card {
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
	border-left: 4px solid #ddd;
	text-align: center;
}

.bc-status-card h3 {
	margin: 0 0 10px 0;
	font-size: 14px;
}

.status-good {
	color: #46b450;
	font-weight: bold;
}

.status-bad {
	color: #dc3232;
	font-weight: bold;
}

.status-warning {
	color: #ffb900;
	font-weight: bold;
}

.bc-action-buttons {
	margin-bottom: 15px;
}

.bc-action-buttons .button {
	margin-right: 10px;
	margin-bottom: 5px;
}

.bc-action-buttons .dashicons {
	margin-right: 5px;
	vertical-align: middle;
}

.bc-action-status {
	margin-top: 15px;
	padding: 15px;
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.bc-status-message {
	margin-bottom: 10px;
	font-weight: bold;
}

.bc-status-progress {
	display: flex;
	align-items: center;
	gap: 10px;
}

.bc-oauth-action {
	margin-bottom: 15px;
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

.oauth-success {
	color: #46b450;
	margin: 0;
}

.oauth-warning {
	color: #ffb900;
	margin: 0;
}

.notice {
	margin: 15px 0;
}

.notice p {
	margin: 0;
}
</style>

<script type="text/javascript">
// Ensure ajaxurl is available
var ajaxurl = ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>';

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
	
	// Test Connection
	$('#bc-test-connection').on('click', function() {
		showStatus('Testing connection...', 'info');
		showProgress();
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'bc_test_connection',
				nonce: '<?php echo wp_create_nonce( 'bc_sync_nonce' ); ?>'
			},
			success: function(response) {
				hideProgress();
				if (response.success) {
					showStatus('Connection successful!', 'success');
				} else {
					showStatus('Connection failed: ' + response.data, 'error');
				}
			},
			error: function() {
				hideProgress();
				showStatus('Connection test failed.', 'error');
			}
		});
	});
	
	// Sync Products
	$('#bc-sync-products').on('click', function() {
		showStatus('Syncing products...', 'info');
		showProgress();
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'bc_sync_products',
				nonce: '<?php echo wp_create_nonce( 'bc_sync_nonce' ); ?>'
			},
			success: function(response) {
				hideProgress();
				if (response.success) {
					showStatus('Products synced successfully!', 'success');
				} else {
					showStatus('Product sync failed: ' + response.data, 'error');
				}
			},
			error: function() {
				hideProgress();
				showStatus('Product sync failed.', 'error');
			}
		});
	});
	
	// Sync Pricelists
	$('#bc-sync-pricelists').on('click', function() {
		showStatus('Syncing pricelists...', 'info');
		showProgress();
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'bc_sync_pricelists',
				nonce: '<?php echo wp_create_nonce( 'bc_sync_nonce' ); ?>'
			},
			success: function(response) {
				hideProgress();
				if (response.success) {
					showStatus('Pricelists synced successfully!', 'success');
				} else {
					showStatus('Pricelist sync failed: ' + response.data, 'error');
				}
			},
			error: function() {
				hideProgress();
				showStatus('Pricelist sync failed.', 'error');
			}
		});
	});
	
	function showStatus(message, type) {
		var statusDiv = $('#bc-action-status');
		var messageDiv = statusDiv.find('.bc-status-message');
		
		statusDiv.show();
		messageDiv.text(message);
		
		messageDiv.removeClass('bc-status-success bc-status-error bc-status-info');
		
		switch(type) {
			case 'success':
				messageDiv.addClass('bc-status-success').css('color', 'green');
				break;
			case 'error':
				messageDiv.addClass('bc-status-error').css('color', 'red');
				break;
			case 'info':
				messageDiv.addClass('bc-status-info').css('color', 'blue');
				break;
		}
	}
	
	function showProgress() {
		$('#bc-action-status .bc-status-progress').show();
	}
	
	function hideProgress() {
		$('#bc-action-status .bc-status-progress').hide();
	}
});
</script>

<?php
/**
 * Simple Admin Display for Business Central Sync
 * 
 * This provides a basic admin interface without HPOS complexity.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Permissions are now checked at the method level in the admin class
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- Permission Status -->
	<div class="bc-permission-status">
		<h2><?php _e( 'Permission Status', 'bc-business-central-sync' ); ?></h2>
		<?php
		$current_user = wp_get_current_user();
		echo '<p><strong>Current User:</strong> ' . esc_html( $current_user->display_name ) . ' (' . esc_html( $current_user->user_email ) . ')</p>';
		echo '<p><strong>User Role:</strong> ' . esc_html( implode( ', ', $current_user->roles ) ) . '</p>';
		echo '<p><strong>Can Manage WooCommerce:</strong> ' . ( current_user_can( 'manage_woocommerce' ) ? '✅ Yes' : '❌ No' ) . '</p>';
		echo '<p><strong>Can Manage Options:</strong> ' . ( current_user_can( 'manage_options' ) ? '✅ Yes' : '❌ No' ) . '</p>';
		?>
	</div>

	<!-- Simple Status Section -->
	<div class="bc-simple-status">
		<h2><?php _e( 'Business Central Sync Status', 'bc-business-central-sync' ); ?></h2>
		
		<?php
		// Check if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-success"><p>' . __( '✅ WooCommerce is active and compatible.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( '❌ WooCommerce is not active. Please install and activate WooCommerce.', 'bc-business-central-sync' ) . '</p></div>';
		}
		
		// Check if simple pricing is available
		if ( class_exists( 'BC_Simple_Pricing' ) ) {
			echo '<div class="notice notice-success"><p>' . __( '✅ Simple pricing system is active.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( '❌ Simple pricing system not found.', 'bc-business-central-sync' ) . '</p></div>';
		}
		?>
	</div>

	<!-- Basic Settings -->
	<div class="bc-basic-settings">
		<h2><?php _e( 'Basic Settings', 'bc-business-central-sync' ); ?></h2>
		
		<form method="post" action="options.php">
			<?php
			settings_fields( 'bc-business-central-sync' );
			do_settings_sections( 'bc-business-central-sync' );
			?>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bc_sync_enabled"><?php _e( 'Enable Sync', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<select name="bc_sync_enabled" id="bc_sync_enabled">
							<option value="no" <?php selected( get_option( 'bc_sync_enabled' ), 'no' ); ?>><?php _e( 'No', 'bc-business-central-sync' ); ?></option>
							<option value="yes" <?php selected( get_option( 'bc_sync_enabled' ), 'yes' ); ?>><?php _e( 'Yes', 'bc-business-central-sync' ); ?></option>
						</select>
						<p class="description"><?php _e( 'Enable or disable automatic product synchronization.', 'bc-business-central-sync' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="bc_api_url"><?php _e( 'API Base URL', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="url" name="bc_api_url" id="bc_api_url" 
							   value="<?php echo esc_attr( get_option( 'bc_api_url' ) ); ?>" 
							   class="regular-text" />
						<p class="description"><?php _e( 'Business Central API base URL (e.g., https://api.businesscentral.dynamics.com/v2.0/your-environment)', 'bc-business-central-sync' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="bc_company_id"><?php _e( 'Company ID', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="text" name="bc_company_id" id="bc_company_id" 
							   value="<?php echo esc_attr( get_option( 'bc_company_id' ) ); ?>" 
							   class="regular-text" />
						<p class="description"><?php _e( 'Your Business Central company ID.', 'bc-business-central-sync' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="bc_client_id"><?php _e( 'Client ID', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="text" name="bc_client_id" id="bc_client_id" 
							   value="<?php echo esc_attr( get_option( 'bc_client_id' ) ); ?>" 
							   class="regular-text" />
						<p class="description"><?php _e( 'Azure AD application client ID.', 'bc-business-central-sync' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="bc_client_secret"><?php _e( 'Client Secret', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="password" name="bc_client_secret" id="bc_client_secret" 
							   value="<?php echo esc_attr( get_option( 'bc_client_secret' ) ); ?>" 
							   class="regular-text" />
						<p class="description"><?php _e( 'Azure AD application client secret.', 'bc-business-central-sync' ); ?></p>
					</td>
				</tr>
			</table>
			
			<?php submit_button( __( 'Save Settings', 'bc-business-central-sync' ) ); ?>
		</form>
	</div>

	<!-- Simple Actions -->
	<div class="bc-simple-actions">
		<h2><?php _e( 'Quick Actions', 'bc-business-central-sync' ); ?></h2>
		
		<div class="bc-action-buttons">
			<button type="button" id="bc-test-connection" class="button button-secondary">
				<?php _e( 'Test Connection', 'bc-business-central-sync' ); ?>
			</button>
			
			<button type="button" id="bc-sync-products" class="button button-primary">
				<?php _e( 'Sync Products Now', 'bc-business-central-sync' ); ?>
			</button>
			
			<button type="button" id="bc-sync-pricelists" class="button button-primary">
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

	<!-- Simple Info -->
	<div class="bc-simple-info">
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
		</table>
	</div>
</div>

<style>
.bc-simple-status,
.bc-basic-settings,
.bc-simple-actions,
.bc-simple-info {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.bc-action-buttons {
	margin-bottom: 15px;
}

.bc-action-buttons .button {
	margin-right: 10px;
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

.notice {
	margin: 15px 0;
}

.notice p {
	margin: 0;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
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
		
		// Remove existing classes
		messageDiv.removeClass('bc-status-success bc-status-error bc-status-info');
		
		// Add appropriate class
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

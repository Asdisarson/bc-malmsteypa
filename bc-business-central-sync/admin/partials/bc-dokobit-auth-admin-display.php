<?php
/**
 * Dokobit Authentication Admin Page
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bc-dokobit-admin-container">
		<div class="bc-dokobit-admin-section">
			<h2><?php _e( 'Dokobit API Configuration', 'bc-business-central-sync' ); ?></h2>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'bc_dokobit_options' );
				do_settings_sections( 'bc_dokobit_options' );
				?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bc_dokobit_api_endpoint"><?php _e( 'API Endpoint', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="url" id="bc_dokobit_api_endpoint" name="bc_dokobit_api_endpoint" 
								   value="<?php echo esc_attr( get_option( 'bc_dokobit_api_endpoint', 'https://developers.dokobit.com' ) ); ?>" 
								   class="regular-text" />
							<p class="description"><?php _e( 'Dokobit API endpoint URL', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bc_dokobit_api_key"><?php _e( 'API Key', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="text" id="bc_dokobit_api_key" name="bc_dokobit_api_key" 
								   value="<?php echo esc_attr( get_option( 'bc_dokobit_api_key', '' ) ); ?>" 
								   class="regular-text" />
							<p class="description"><?php _e( 'Your Dokobit API access token', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Save Settings', 'bc-business-central-sync' ) ); ?>
			</form>
		</div>
		
		<div class="bc-dokobit-admin-section">
			<h2><?php _e( 'Test Connection', 'bc-business-central-sync' ); ?></h2>
			<p><?php _e( 'Test your Dokobit API connection to ensure everything is working correctly.', 'bc-business-central-sync' ); ?></p>
			
			<button type="button" id="bc-dokobit-test-connection" class="button button-secondary">
				<?php _e( 'Test Connection', 'bc-business-central-sync' ); ?>
			</button>
			
			<div id="bc-dokobit-test-result" class="bc-dokobit-test-result" style="display: none;"></div>
		</div>
		
		<div class="bc-dokobit-admin-section">
			<h2><?php _e( 'Quick Actions', 'bc-business-central-sync' ); ?></h2>
			
			<div class="bc-dokobit-quick-actions">
				<a href="<?php echo admin_url( 'admin.php?page=bc-dokobit-companies' ); ?>" class="button button-primary">
					<?php _e( 'Manage Companies', 'bc-business-central-sync' ); ?>
				</a>
				
				<a href="<?php echo admin_url( 'admin.php?page=bc-dokobit-user-phones' ); ?>" class="button button-primary">
					<?php _e( 'Manage User Phones', 'bc-business-central-sync' ); ?>
				</a>
			</div>
		</div>
		
		<div class="bc-dokobit-admin-section">
			<h2><?php _e( 'Shortcode Usage', 'bc-business-central-sync' ); ?></h2>
			<p><?php _e( 'Use these shortcodes to display authentication forms and company information on your site:', 'bc-business-central-sync' ); ?></p>
			
			<div class="bc-dokobit-shortcodes">
				<div class="bc-shortcode-item">
					<strong><?php _e( 'Login Form:', 'bc-business-central-sync' ); ?></strong>
					<code>[bc_dokobit_login]</code>
					<p class="description"><?php _e( 'Displays the Dokobit phone authentication form', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-shortcode-item">
					<strong><?php _e( 'Company Info:', 'bc-business-central-sync' ); ?></strong>
					<code>[bc_dokobit_company]</code>
					<p class="description"><?php _e( 'Shows the authenticated user\'s company information', 'bc-business-central-sync' ); ?></p>
				</div>
			</div>
		</div>
		
		<div class="bc-dokobit-admin-section">
			<h2><?php _e( 'System Status', 'bc-business-central-sync' ); ?></h2>
			
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong><?php _e( 'API Endpoint:', 'bc-business-central-sync' ); ?></strong></td>
						<td><?php echo esc_html( get_option( 'bc_dokobit_api_endpoint', 'Not set' ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e( 'API Key:', 'bc-business-central-sync' ); ?></strong></td>
						<td><?php echo get_option( 'bc_dokobit_api_key' ) ? '✓ Set' : '✗ Not set'; ?></td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Companies Count:', 'bc-business-central-sync' ); ?></strong></td>
						<td><?php echo count( BC_Dokobit_Database::get_companies() ); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e( 'User Phones Count:', 'bc-business-central-sync' ); ?></strong></td>
						<td><?php echo count( BC_Dokobit_Database::get_user_phones() ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<style>
.bc-dokobit-admin-container {
	max-width: 1200px;
}

.bc-dokobit-admin-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.bc-dokobit-admin-section h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.bc-dokobit-quick-actions {
	display: flex;
	gap: 10px;
}

.bc-dokobit-shortcodes {
	display: grid;
	gap: 20px;
}

.bc-shortcode-item {
	background: #f9f9f9;
	padding: 15px;
	border-radius: 4px;
	border-left: 4px solid #007cba;
}

.bc-shortcode-item code {
	background: #fff;
	padding: 5px 10px;
	border-radius: 3px;
	font-size: 14px;
}

.bc-dokobit-test-result {
	margin-top: 15px;
	padding: 10px;
	border-radius: 4px;
}

.bc-dokobit-test-result.success {
	background: #d4edda;
	border: 1px solid #c3e6cb;
	color: #155724;
}

.bc-dokobit-test-result.error {
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('#bc-dokobit-test-connection').on('click', function() {
		var button = $(this);
		var resultDiv = $('#bc-dokobit-test-result');
		
		button.prop('disabled', true).text('Testing...');
		resultDiv.hide();
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'bc_dokobit_test_connection',
				nonce: '<?php echo wp_create_nonce( 'bc_dokobit_test_nonce' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					resultDiv.removeClass('error').addClass('success')
						.html('<strong>Success:</strong> ' + response.data.message)
						.show();
				} else {
					resultDiv.removeClass('success').addClass('error')
						.html('<strong>Error:</strong> ' + response.data.message)
						.show();
				}
			},
			error: function() {
				resultDiv.removeClass('success').addClass('error')
					.html('<strong>Error:</strong> Connection test failed')
					.show();
			},
			complete: function() {
				button.prop('disabled', false).text('<?php _e( 'Test Connection', 'bc-business-central-sync' ); ?>');
			}
		});
	});
});
</script>

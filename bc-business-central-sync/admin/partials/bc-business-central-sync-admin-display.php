<?php
/**
 * Admin area display template.
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
	
	<div class="bc-sync-container">
		<div class="bc-sync-settings">
			<h2>Business Central Connection Settings</h2>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'bc-business-central-sync' );
				do_settings_sections( 'bc-business-central-sync' );
				?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bc_sync_enabled">Enable Sync</label>
						</th>
						<td>
							<select name="bc_sync_enabled" id="bc_sync_enabled">
								<option value="no" <?php selected( get_option( 'bc_sync_enabled' ), 'no' ); ?>>No</option>
								<option value="yes" <?php selected( get_option( 'bc_sync_enabled' ), 'yes' ); ?>>Yes</option>
							</select>
							<p class="description">Enable or disable automatic product synchronization.</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bc_api_url">API Base URL</label>
						</th>
						<td>
							<input type="url" name="bc_api_url" id="bc_api_url" 
								   value="<?php echo esc_attr( get_option( 'bc_api_url' ) ); ?>" 
								   class="regular-text" />
							<p class="description">Business Central API base URL (e.g., https://api.businesscentral.dynamics.com/v2.0/your-environment)</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bc_company_id">Company ID</label>
						</th>
						<td>
							<input type="text" name="bc_company_id" id="bc_company_id" 
								   value="<?php echo esc_attr( get_option( 'bc_company_id' ) ); ?>" 
								   class="regular-text" />
							<p class="description">Your Business Central company ID.</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bc_client_id">Client ID</label>
						</th>
						<td>
							<input type="text" name="bc_client_id" id="bc_client_id" 
								   value="<?php echo esc_attr( get_option( 'bc_client_id' ) ); ?>" 
								   class="regular-text" />
							<p class="description">Azure AD application client ID.</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bc_client_secret">Client Secret</label>
						</th>
						<td>
							<input type="password" name="bc_client_secret" id="bc_client_secret" 
								   value="<?php echo esc_attr( get_option( 'bc_client_secret' ) ); ?>" 
								   class="regular-text" />
							<p class="description">Azure AD application client secret.</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bc_sync_interval">Sync Interval</label>
						</th>
						<td>
							<select name="bc_sync_interval" id="bc_sync_interval">
								<option value="hourly" <?php selected( get_option( 'bc_sync_interval' ), 'hourly' ); ?>>Hourly</option>
								<option value="daily" <?php selected( get_option( 'bc_sync_interval' ), 'daily' ); ?>>Daily</option>
								<option value="weekly" <?php selected( get_option( 'bc_sync_interval' ), 'weekly' ); ?>>Weekly</option>
							</select>
							<p class="description">How often to automatically sync products from Business Central.</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		
		<div class="bc-sync-actions">
			<h2>Manual Sync Actions</h2>
			
			<div class="bc-sync-buttons">
				<button type="button" id="bc-test-connection" class="button button-secondary">
					Test Connection
				</button>
				
				<button type="button" id="bc-sync-products" class="button button-primary">
					Sync Products Now
				</button>
				
				<button type="button" id="bc-sync-pricelists" class="button button-primary">
					Sync Pricelists
				</button>
				
				<button type="button" id="bc-sync-customers" class="button button-primary">
					Sync Customer Companies
				</button>
			</div>
			
			<div id="bc-sync-status" class="bc-sync-status" style="display: none;">
				<div class="bc-sync-message"></div>
				<div class="bc-sync-progress" style="display: none;">
					<div class="spinner is-active"></div>
					<span>Processing...</span>
				</div>
			</div>
		</div>
		
		<div class="bc-sync-info">
			<h2>Sync Information</h2>
			
			<table class="widefat">
				<tr>
					<th>Last Product Sync:</th>
					<td>
						<?php 
						$last_sync = get_option( 'bc_last_sync' );
						echo $last_sync ? esc_html( $last_sync ) : 'Never';
						?>
					</td>
				</tr>
				<tr>
					<th>Last Pricelist Sync:</th>
					<td>
						<?php 
						$last_pricelist_sync = get_option( 'bc_last_pricelist_sync' );
						echo $last_pricelist_sync ? esc_html( $last_pricelist_sync ) : 'Never';
						?>
					</td>
				</tr>
				<tr>
					<th>Last Customer Sync:</th>
					<td>
						<?php 
						$last_customer_sync = get_option( 'bc_last_customer_sync' );
						echo $last_customer_sync ? esc_html( $last_customer_sync ) : 'Never';
						?>
					</td>
				</tr>
				<tr>
					<th>Sync Status:</th>
					<td>
						<?php 
						$sync_enabled = get_option( 'bc_sync_enabled' );
						echo $sync_enabled === 'yes' ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>';
						?>
					</td>
				</tr>
				<tr>
					<th>Next Scheduled Sync:</th>
					<td>
						<?php
						$next_sync = wp_next_scheduled( 'bc_sync_products_cron' );
						echo $next_sync ? esc_html( date( 'Y-m-d H:i:s', $next_sync ) ) : 'Not scheduled';
						?>
					</td>
				</tr>
			</table>
		</div>
		
		<div class="bc-sync-pricelists">
			<h2>Pricelist Information</h2>
			
			<?php
			global $wpdb;
			$pricelists_table = $wpdb->prefix . 'bc_pricelists';
			$pricelist_lines_table = $wpdb->prefix . 'bc_pricelist_lines';
			$customer_companies_table = $wpdb->prefix . 'bc_customer_companies';
			
			$pricelists_count = $wpdb->get_var( "SELECT COUNT(*) FROM $pricelists_table" );
			$lines_count = $wpdb->get_var( "SELECT COUNT(*) FROM $pricelist_lines_table" );
			$customers_count = $wpdb->get_var( "SELECT COUNT(*) FROM $customer_companies_table" );
			?>
			
			<table class="widefat">
				<tr>
					<th>Total Pricelists:</th>
					<td><?php echo esc_html( $pricelists_count ?: 0 ); ?></td>
				</tr>
				<tr>
					<th>Total Pricelist Lines:</th>
					<td><?php echo esc_html( $lines_count ?: 0 ); ?></td>
				</tr>
				<tr>
					<th>Total Customer Companies:</th>
					<td><?php echo esc_html( $customers_count ?: 0 ); ?></td>
				</tr>
			</table>
		</div>
	</div>
</div>

<style>
.bc-sync-container {
	margin-top: 20px;
}

.bc-sync-settings,
.bc-sync-actions,
.bc-sync-info {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.bc-sync-buttons {
	margin: 15px 0;
}

.bc-sync-buttons .button {
	margin-right: 10px;
}

.bc-sync-status {
	margin-top: 15px;
	padding: 15px;
	border-radius: 4px;
}

.bc-sync-status.success {
	background-color: #d4edda;
	border: 1px solid #c3e6cb;
	color: #155724;
}

.bc-sync-status.error {
	background-color: #f8d7da;
	border: 1px solid #f5c6cb;
	color: #721c24;
}

.bc-sync-progress {
	margin-top: 10px;
}

.bc-sync-progress .spinner {
	float: none;
	margin: 0 10px 0 0;
}
</style>

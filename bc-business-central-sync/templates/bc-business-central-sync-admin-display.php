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
	
	<!-- HPOS Status Section -->
	<div class="bc-hpos-status-section">
		<h2><?php _e( 'HPOS (High-Performance Order Storage) Status', 'bc-business-central-sync' ); ?></h2>
		<?php
		if ( class_exists( 'BC_HPOS_Compatibility' ) ) {
			$hpos_compatibility = new BC_HPOS_Compatibility();
			$hpos_status = $hpos_compatibility->get_hpos_status();
			$recommendations = $hpos_compatibility->get_migration_recommendations();
			?>
			<div class="bc-hpos-status-grid">
				<div class="bc-hpos-status-card">
					<h3><?php _e( 'HPOS Status', 'bc-business-central-sync' ); ?></h3>
					<div class="bc-status-indicator <?php echo $hpos_status['enabled'] ? 'enabled' : 'disabled'; ?>">
						<span class="dashicons <?php echo $hpos_status['enabled'] ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
						<?php echo $hpos_status['enabled'] ? __( 'Enabled', 'bc-business-central-sync' ) : __( 'Disabled', 'bc-business-central-sync' ); ?>
					</div>
				</div>
				
				<div class="bc-hpos-status-card">
					<h3><?php _e( 'Usage', 'bc-business-central-sync' ); ?></h3>
					<div class="bc-usage-bar">
						<div class="bc-usage-fill" style="width: <?php echo esc_attr( $hpos_status['usage_percentage'] ); ?>%"></div>
						<span class="bc-usage-text"><?php echo esc_html( $hpos_status['usage_percentage'] ); ?>%</span>
					</div>
					<p class="description"><?php _e( 'Orders using HPOS', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<?php if ( $hpos_status['enabled'] && ! empty( $hpos_status['performance_metrics'] ) ) : ?>
				<div class="bc-hpos-status-card">
					<h3><?php _e( 'Performance', 'bc-business-central-sync' ); ?></h3>
					<div class="bc-performance-metrics">
						<p><strong><?php _e( 'Query Time:', 'bc-business-central-sync' ); ?></strong> <?php echo esc_html( $hpos_status['performance_metrics']['query_time'] ); ?>ms</p>
						<p><strong><?php _e( 'Memory:', 'bc-business-central-sync' ); ?></strong> <?php echo esc_html( size_format( $hpos_status['performance_metrics']['memory_usage'] ) ); ?></p>
						<?php if ( $hpos_status['performance_metrics']['cache_hit_rate'] > 0 ) : ?>
						<p><strong><?php _e( 'Cache Hit Rate:', 'bc-business-central-sync' ); ?></strong> <?php echo esc_html( $hpos_status['performance_metrics']['cache_hit_rate'] ); ?>%</p>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
			
			<?php if ( ! empty( $recommendations ) ) : ?>
			<div class="bc-hpos-recommendations">
				<h3><?php _e( 'Recommendations', 'bc-business-central-sync' ); ?></h3>
				<ul class="bc-recommendations-list">
					<?php foreach ( $recommendations as $recommendation ) : ?>
					<li><?php echo esc_html( $recommendation ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
			
			<div class="bc-hpos-actions">
				<?php if ( $hpos_status['available'] && ! $hpos_status['enabled'] ) : ?>
				<p class="bc-hpos-notice notice notice-warning">
					<?php _e( 'HPOS is available but not enabled. Enable it in WooCommerce → Settings → Advanced → Features to improve performance.', 'bc-business-central-sync' ); ?>
				</p>
				<?php endif; ?>
				
				<?php if ( $hpos_status['enabled'] && $hpos_status['usage_percentage'] < 100 ) : ?>
				<p class="bc-hpos-notice notice notice-info">
					<?php _e( 'HPOS is enabled but some orders are still using the traditional storage. Consider migrating all orders for optimal performance.', 'bc-business-central-sync' ); ?>
				</p>
				<?php endif; ?>
			</div>
			<?php
		} else {
			echo '<p class="notice notice-error">' . __( 'HPOS compatibility class not found.', 'bc-business-central-sync' ) . '</p>';
		}
		?>
	</div>

	<!-- Existing Dashboard Content -->
	<div class="bc-dashboard-content">
		<h2><?php _e( 'Business Central Sync Dashboard', 'bc-business-central-sync' ); ?></h2>
		
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

/* New styles for HPOS Status */
.bc-hpos-status-section {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.bc-hpos-status-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 15px;
}

.bc-hpos-status-card {
	background: #f9f9f9;
	padding: 15px;
	border: 1px solid #eee;
	border-radius: 4px;
	text-align: center;
}

.bc-hpos-status-card h3 {
	margin-top: 0;
	margin-bottom: 10px;
	color: #333;
}

.bc-status-indicator {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 10px;
	border-radius: 50%;
	width: 60px;
	height: 60px;
	margin: 0 auto 10px;
	background-color: #e0f2f7;
	border: 2px solid #4facfe;
}

.bc-status-indicator.enabled {
	background-color: #d4edda;
	border: 2px solid #28a745;
}

.bc-status-indicator.disabled {
	background-color: #f8d7da;
	border: 2px solid #dc3545;
}

.bc-status-indicator .dashicons {
	font-size: 24px;
	color: #4facfe;
}

.bc-status-indicator.enabled .dashicons {
	color: #28a745;
}

.bc-status-indicator.disabled .dashicons {
	color: #dc3545;
}

.bc-usage-bar {
	background-color: #e0f2f7;
	border-radius: 5px;
	overflow: hidden;
	margin-bottom: 10px;
}

.bc-usage-fill {
	height: 10px;
	background-color: #4facfe;
	border-radius: 5px;
	transition: width 0.3s ease-in-out;
}

.bc-usage-text {
	font-size: 14px;
	font-weight: bold;
	color: #333;
}

.bc-performance-metrics p {
	margin: 5px 0;
	font-size: 0.9em;
	color: #555;
}

.bc-performance-metrics strong {
	color: #333;
}

.bc-hpos-recommendations {
	margin-top: 20px;
	padding-top: 15px;
	border-top: 1px solid #eee;
}

.bc-hpos-recommendations h3 {
	margin-top: 0;
	margin-bottom: 10px;
	color: #333;
}

.bc-recommendations-list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.bc-recommendations-list li {
	margin-bottom: 5px;
	padding-left: 20px;
	position: relative;
}

.bc-recommendations-list li:before {
	content: "•";
	color: #4facfe;
	position: absolute;
	left: 0;
}

.bc-hpos-notice {
	margin-top: 15px;
	padding: 10px;
	border-radius: 4px;
}

.bc-hpos-notice.notice-warning {
	background-color: #fffbe6;
	border: 1px solid #ffe564;
	color: #856404;
}

.bc-hpos-notice.notice-info {
	background-color: #e0f7fa;
	border: 1px solid #4facfe;
	color: #007bff;
}
</style>

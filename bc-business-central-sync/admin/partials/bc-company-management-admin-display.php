<?php
/**
 * Company Management Admin Page
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Handle form submissions
if ( isset( $_POST['action'] ) ) {
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bc_company_action' ) ) {
		wp_die( __( 'Security check failed', 'bc-business-central-sync' ) );
	}
	
	if ( $_POST['action'] === 'fetch_companies' ) {
		// Fetch companies from Business Central
		$bc_api = new BC_Business_Central_API();
		try {
			$companies = $bc_api->get_companies();
			$company_manager = new BC_Company_Manager();
			$sync_results = $company_manager->sync_companies( $companies );
			
			if ( $sync_results['errors'] === 0 ) {
				echo '<div class="notice notice-success"><p>' . 
					sprintf( __( 'Successfully synced %d companies. Created: %d, Updated: %d', 'bc-business-central-sync' ), 
						count( $companies ), 
						$sync_results['created'], 
						$sync_results['updated'] 
					) . '</p></div>';
			} else {
				echo '<div class="notice notice-warning"><p>' . 
					sprintf( __( 'Synced with warnings. Created: %d, Updated: %d, Errors: %d', 'bc-business-central-sync' ), 
						$sync_results['created'], 
						$sync_results['updated'], 
						$sync_results['errors'] 
					) . '</p></div>';
			}
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>' . 
				sprintf( __( 'Error fetching companies: %s', 'bc-business-central-sync' ), $e->getMessage() ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'update_company_pricelist' ) {
		// Update company pricelist assignment
		$company_id = intval( $_POST['company_id'] );
		$pricelist_id = intval( $_POST['pricelist_id'] );
		
		$company_manager = new BC_Company_Manager();
		try {
			$company_manager->assign_pricelist_to_company( $company_id, $pricelist_id );
			echo '<div class="notice notice-success"><p>' . __( 'Company pricelist updated successfully', 'bc-business-central-sync' ) . '</p></div>';
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>' . 
				sprintf( __( 'Error updating company pricelist: %s', 'bc-business-central-sync' ), $e->getMessage() ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'delete_company' ) {
		// Delete company
		$company_id = intval( $_POST['company_id'] );
		$company_manager = new BC_Company_Manager();
		
		try {
			if ( $company_manager->delete_company( $company_id ) ) {
				echo '<div class="notice notice-success"><p>' . __( 'Company deleted successfully', 'bc-business-central-sync' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . __( 'Cannot delete company with associated users', 'bc-business-central-sync' ) . '</p></div>';
			}
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>' . 
				sprintf( __( 'Error deleting company: %s', 'bc-business-central-sync' ), $e->getMessage() ) . '</p></div>';
		}
	}
}

// Get existing companies and pricelists
$company_manager = new BC_Company_Manager();
$pricelist_manager = new BC_Pricelist_Manager();
$companies = $company_manager->get_all_companies();
$pricelists = $pricelist_manager->get_all_pricelists();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bc-company-admin-container">
		<!-- Fetch Companies Section -->
		<div class="bc-company-admin-section">
			<h2><?php _e( 'Fetch Companies from Business Central', 'bc-business-central-sync' ); ?></h2>
			<p class="description"><?php _e( 'Click the button below to retrieve all companies from Business Central and sync them with your local database.', 'bc-business-central-sync' ); ?></p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'bc_company_action' ); ?>
				<input type="hidden" name="action" value="fetch_companies">
				<?php submit_button( __( 'Fetch Companies', 'bc-business-central-sync' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		
		<!-- Existing Companies Section -->
		<div class="bc-company-admin-section">
			<h2><?php _e( 'Existing Companies', 'bc-business-central-sync' ); ?></h2>
			
			<?php if ( $companies ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'Company Name', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Assigned Pricelist', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Users Count', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Last Sync', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Actions', 'bc-business-central-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $companies as $company ) : ?>
							<tr>
								<td><?php echo esc_html( $company->company_name ); ?></td>
								<td>
									<form method="post" action="" class="inline-form">
										<?php wp_nonce_field( 'bc_company_action' ); ?>
										<input type="hidden" name="action" value="update_company_pricelist">
										<input type="hidden" name="company_id" value="<?php echo esc_attr( $company->id ); ?>">
										
										<select name="pricelist_id" onchange="this.form.submit()">
											<option value=""><?php _e( '-- Select Pricelist --', 'bc-business-central-sync' ); ?></option>
											<?php foreach ( $pricelists as $pricelist ) : ?>
												<option value="<?php echo esc_attr( $pricelist->id ); ?>" 
														<?php selected( $company->pricelist_id, $pricelist->id ); ?>>
													<?php echo esc_html( $pricelist->name . ' (' . $pricelist->code . ')' ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</form>
								</td>
								<td><?php echo esc_html( $company_manager->get_company_users_count( $company->id ) ); ?></td>
								<td><?php echo esc_html( $company->last_sync ?: __( 'Never', 'bc-business-central-sync' ) ); ?></td>
								<td>
									<form method="post" action="" style="display: inline;">
										<?php wp_nonce_field( 'bc_company_action' ); ?>
										<input type="hidden" name="action" value="delete_company">
										<input type="hidden" name="company_id" value="<?php echo esc_attr( $company->id ); ?>">
										<button type="submit" class="button button-small button-link-delete" 
												onclick="return confirm('<?php _e( 'Are you sure you want to delete this company?', 'bc-business-central-sync' ); ?>')">
											<?php _e( 'Delete', 'bc-business-central-sync' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php _e( 'No companies found. Fetch companies from Business Central first.', 'bc-business-central-sync' ); ?></p>
			<?php endif; ?>
		</div>
		
		<!-- Company Statistics Section -->
		<?php if ( $companies ) : ?>
		<div class="bc-company-admin-section">
			<h2><?php _e( 'Company Statistics', 'bc-business-central-sync' ); ?></h2>
			
			<div class="bc-stats-grid">
				<div class="bc-stat-box">
					<h3><?php echo count( $companies ); ?></h3>
					<p><?php _e( 'Total Companies', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-stat-box">
					<h3><?php echo $company_manager->get_companies_with_pricelist_count(); ?></h3>
					<p><?php _e( 'Companies with Pricelist', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-stat-box">
					<h3><?php echo $company_manager->get_total_users_count(); ?></h3>
					<p><?php _e( 'Total Users', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-stat-box">
					<h3><?php echo $company_manager->get_companies_without_pricelist_count(); ?></h3>
					<p><?php _e( 'Companies without Pricelist', 'bc-business-central-sync' ); ?></p>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<style>
.bc-company-admin-container {
	max-width: 1200px;
}

.bc-company-admin-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.bc-company-admin-section h2 {
	margin-top: 0;
	border-bottom: 1px solid #eee;
	padding-bottom: 10px;
}

.bc-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.bc-stat-box {
	background: #f8f9fa;
	border: 1px solid #dee2e6;
	border-radius: 8px;
	padding: 20px;
	text-align: center;
}

.bc-stat-box h3 {
	margin: 0 0 10px 0;
	font-size: 2em;
	color: #0073aa;
}

.bc-stat-box p {
	margin: 0;
	color: #666;
	font-weight: 500;
}

.inline-form {
	display: inline;
}

.inline-form select {
	min-width: 200px;
}
</style>

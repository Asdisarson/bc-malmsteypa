<?php
/**
 * Dokobit Companies Admin Page
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
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bc_dokobit_company_action' ) ) {
		wp_die( __( 'Security check failed', 'bc-business-central-sync' ) );
	}
	
	if ( $_POST['action'] === 'add_company' && ! empty( $_POST['company_name'] ) ) {
		BC_Dokobit_Database::add_company( sanitize_text_field( $_POST['company_name'] ) );
		echo '<div class="notice notice-success"><p>' . __( 'Company added successfully.', 'bc-business-central-sync' ) . '</p></div>';
	} elseif ( $_POST['action'] === 'delete_company' && ! empty( $_POST['company_id'] ) ) {
		if ( BC_Dokobit_Database::delete_company( intval( $_POST['company_id'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'Company deleted successfully.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( 'Cannot delete company with associated users.', 'bc-business-central-sync' ) . '</p></div>';
		}
	}
}

$companies = BC_Dokobit_Database::get_companies();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bc-companies-admin-container">
		<div class="bc-companies-admin-section">
			<h2><?php _e( 'Add New Company', 'bc-business-central-sync' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'bc_dokobit_company_action' ); ?>
				<input type="hidden" name="action" value="add_company">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="company_name"><?php _e( 'Company Name', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="text" name="company_name" id="company_name" class="regular-text" required>
							<p class="description"><?php _e( 'Enter the name of the company', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Add Company', 'bc-business-central-sync' ) ); ?>
			</form>
		</div>
		
		<div class="bc-companies-admin-section">
			<h2><?php _e( 'Existing Companies', 'bc-business-central-sync' ); ?></h2>
			<?php if ( $companies ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'ID', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Company Name', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Users Count', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Created At', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Actions', 'bc-business-central-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $companies as $company ) : ?>
							<tr>
								<td><?php echo esc_html( $company->id ); ?></td>
								<td><?php echo esc_html( $company->company_name ); ?></td>
								<td><?php echo esc_html( BC_Dokobit_Database::get_company_users_count( $company->id ) ); ?></td>
								<td><?php echo esc_html( $company->created_at ); ?></td>
								<td>
									<form method="post" action="" style="display: inline;">
										<?php wp_nonce_field( 'bc_dokobit_company_action' ); ?>
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
				<p><?php _e( 'No companies found.', 'bc-business-central-sync' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.bc-companies-admin-container {
	max-width: 1200px;
}

.bc-companies-admin-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.bc-companies-admin-section h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.bc-companies-admin-section table {
	margin-top: 15px;
}

.bc-companies-admin-section th {
	font-weight: 600;
}
</style>

<?php
/**
 * User Management Admin Display for Business Central Sync
 * 
 * This allows admins to assign users to companies and manage customer relationships.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current action
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;

// Permissions are now checked at the method level in the admin class

// Get available companies
global $wpdb;
$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
$companies = $wpdb->get_results( "SELECT id, name, customer_number FROM $companies_table ORDER BY name ASC" );

// Handle form submissions
if ( isset( $_POST['bc_action'] ) && wp_verify_nonce( $_POST['bc_nonce'], 'bc_user_management' ) ) {
	$action_type = sanitize_text_field( $_POST['bc_action'] );
	
	if ( $action_type === 'assign_company' ) {
		$user_id = intval( $_POST['user_id'] );
		$company_id = intval( $_POST['company_id'] );
		
		// Get company customer number
		$company = $wpdb->get_row( $wpdb->prepare( "SELECT customer_number FROM $companies_table WHERE id = %d", $company_id ) );
		
		if ( $company ) {
			update_user_meta( $user_id, '_bc_customer_number', $company->customer_number );
			update_user_meta( $user_id, '_bc_company_id', $company_id );
			
			echo '<div class="notice notice-success"><p>' . __( 'Company assigned successfully!', 'bc-business-central-sync' ) . '</p></div>';
		}
	} elseif ( $action_type === 'remove_company' ) {
		$user_id = intval( $_POST['user_id'] );
		
		delete_user_meta( $user_id, '_bc_customer_number' );
		delete_user_meta( $user_id, '_bc_company_id' );
		
		echo '<div class="notice notice-success"><p>' . __( 'Company assignment removed successfully!', 'bc-business-central-sync' ) . '</p></div>';
	}
}

// Get users with company assignments
$users_with_companies = get_users( array(
	'meta_key' => '_bc_customer_number',
	'meta_value' => '',
	'meta_compare' => '!=',
	'orderby' => 'display_name',
	'order' => 'ASC'
) );

// Get all users for assignment
$all_users = get_users( array(
	'orderby' => 'display_name',
	'order' => 'ASC'
) );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- Navigation Tabs -->
	<nav class="nav-tab-wrapper">
		<a href="?page=bc-user-management&action=list" class="nav-tab <?php echo $action === 'list' ? 'nav-tab-active' : ''; ?>">
			<?php _e( 'User Company Assignments', 'bc-business-central-sync' ); ?>
		</a>
		<a href="?page=bc-user-management&action=assign" class="nav-tab <?php echo $action === 'assign' ? 'nav-tab-active' : ''; ?>">
			<?php _e( 'Assign Company to User', 'bc-business-central-sync' ); ?>
		</a>
		<a href="?page=bc-user-management&action=bulk" class="nav-tab <?php echo $action === 'bulk' ? 'nav-tab-active' : ''; ?>">
			<?php _e( 'Bulk Assignments', 'bc-business-central-sync' ); ?>
		</a>
	</nav>
	
	<?php if ( $action === 'list' ) : ?>
		<!-- User Company Assignments List -->
		<div class="bc-user-assignments">
			<h2><?php _e( 'Current User Company Assignments', 'bc-business-central-sync' ); ?></h2>
			
			<?php if ( empty( $users_with_companies ) ) : ?>
				<p><?php _e( 'No users have been assigned to companies yet.', 'bc-business-central-sync' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'User', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Email', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Company', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Customer Number', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Actions', 'bc-business-central-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $users_with_companies as $user ) : 
							$customer_number = get_user_meta( $user->ID, '_bc_customer_number', true );
							$company_id = get_user_meta( $user->ID, '_bc_company_id', true );
							
							// Find company name
							$company_name = '';
							foreach ( $companies as $company ) {
								if ( $company->customer_number === $customer_number ) {
									$company_name = $company->name;
									break;
								}
							}
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $user->display_name ); ?></strong>
								<div class="row-actions">
									<span class="edit">
										<a href="?page=bc-user-management&action=edit&user_id=<?php echo $user->ID; ?>">
											<?php _e( 'Edit', 'bc-business-central-sync' ); ?>
										</a>
									</span>
								</div>
							</td>
							<td><?php echo esc_html( $user->user_email ); ?></td>
							<td><?php echo esc_html( $company_name ); ?></td>
							<td><code><?php echo esc_html( $customer_number ); ?></code></td>
							<td>
								<form method="post" style="display: inline;">
									<?php wp_nonce_field( 'bc_user_management', 'bc_nonce' ); ?>
									<input type="hidden" name="bc_action" value="remove_company">
									<input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
									<button type="submit" class="button button-small button-link-delete" 
											onclick="return confirm('<?php _e( 'Are you sure you want to remove this company assignment?', 'bc-business-central-sync' ); ?>')">
										<?php _e( 'Remove', 'bc-business-central-sync' ); ?>
									</button>
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		
	<?php elseif ( $action === 'assign' ) : ?>
		<!-- Assign Company to User -->
		<div class="bc-assign-company">
			<h2><?php _e( 'Assign Company to User', 'bc-business-central-sync' ); ?></h2>
			
			<form method="post" class="bc-assign-form">
				<?php wp_nonce_field( 'bc_user_management', 'bc_nonce' ); ?>
				<input type="hidden" name="bc_action" value="assign_company">
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="user_id"><?php _e( 'Select User', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="user_id" id="user_id" required>
								<option value=""><?php _e( '-- Select User --', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $all_users as $user ) : 
									$has_company = get_user_meta( $user->ID, '_bc_customer_number', true );
									if ( ! $has_company ) :
								?>
								<option value="<?php echo $user->ID; ?>">
									<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
								</option>
								<?php endif; endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select a user to assign a company to. Only users without company assignments are shown.', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="company_id"><?php _e( 'Select Company', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="company_id" id="company_id" required>
								<option value=""><?php _e( '-- Select Company --', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $companies as $company ) : ?>
								<option value="<?php echo $company->id; ?>">
									<?php echo esc_html( $company->name . ' (' . $company->customer_number . ')' ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the company to assign to the user.', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Assign Company', 'bc-business-central-sync' ) ); ?>
			</form>
		</div>
		
	<?php elseif ( $action === 'bulk' ) : ?>
		<!-- Bulk Company Assignments -->
		<div class="bc-bulk-assignments">
			<h2><?php _e( 'Bulk Company Assignments', 'bc-business-central-sync' ); ?></h2>
			
			<form method="post" class="bc-bulk-form">
				<?php wp_nonce_field( 'bc_user_management', 'bc_nonce' ); ?>
				<input type="hidden" name="bc_action" value="bulk_assign">
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bulk_company_id"><?php _e( 'Select Company', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="bulk_company_id" id="bulk_company_id" required>
								<option value=""><?php _e( '-- Select Company --', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $companies as $company ) : ?>
								<option value="<?php echo $company->id; ?>">
									<?php echo esc_html( $company->name . ' (' . $company->customer_number . ')' ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the company to assign to multiple users.', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bulk_users"><?php _e( 'Select Users', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="bulk_users[]" id="bulk_users" multiple size="10" required>
								<?php foreach ( $all_users as $user ) : 
									$has_company = get_user_meta( $user->ID, '_bc_customer_number', true );
									if ( ! $has_company ) :
								?>
								<option value="<?php echo $user->ID; ?>">
									<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
								</option>
								<?php endif; endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Hold Ctrl/Cmd to select multiple users. Only users without company assignments are shown.', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Bulk Assign Company', 'bc-business-central-sync' ) ); ?>
			</form>
		</div>
		
	<?php elseif ( $action === 'edit' && $user_id ) : ?>
		<!-- Edit User Company Assignment -->
		<?php
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			echo '<div class="notice notice-error"><p>' . __( 'User not found.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			$current_customer_number = get_user_meta( $user_id, '_bc_customer_number', true );
			$current_company_id = get_user_meta( $user_id, '_bc_company_id', true );
		?>
		<div class="bc-edit-assignment">
			<h2><?php printf( __( 'Edit Company Assignment for %s', 'bc-business-central-sync' ), esc_html( $user->display_name ) ); ?></h2>
			
			<form method="post" class="bc-edit-form">
				<?php wp_nonce_field( 'bc_user_management', 'bc_nonce' ); ?>
				<input type="hidden" name="bc_action" value="update_company">
				<input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="edit_company_id"><?php _e( 'Company', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="edit_company_id" id="edit_company_id" required>
								<option value=""><?php _e( '-- Select Company --', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $companies as $company ) : 
									$selected = ( $company->id == $current_company_id ) ? 'selected' : '';
								?>
								<option value="<?php echo $company->id; ?>" <?php echo $selected; ?>>
									<?php echo esc_html( $company->name . ' (' . $company->customer_number . ')' ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the company to assign to this user.', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Update Company Assignment', 'bc-business-central-sync' ) ); ?>
				<a href="?page=bc-user-management&action=list" class="button button-secondary"><?php _e( 'Cancel', 'bc-business-central-sync' ); ?></a>
			</form>
		</div>
		<?php } ?>
	<?php endif; ?>
</div>

<style>
.bc-user-assignments,
.bc-assign-company,
.bc-bulk-assignments,
.bc-edit-assignment {
	background: #fff;
	padding: 20px;
	margin-top: 20px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.bc-assign-form,
.bc-bulk-form,
.bc-edit-form {
	max-width: 600px;
}

.nav-tab-wrapper {
	margin-bottom: 20px;
}

#bulk_users {
	width: 100%;
	max-width: 400px;
}

.form-table th {
	width: 200px;
}

.row-actions {
	visibility: hidden;
}

tr:hover .row-actions {
	visibility: visible;
}

.notice {
	margin: 20px 0;
}
</style>

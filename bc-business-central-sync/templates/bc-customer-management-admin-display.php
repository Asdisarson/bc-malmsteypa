<?php
/**
 * Customer Management Admin Page
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
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bc_customer_action' ) ) {
		wp_die( __( 'Security check failed', 'bc-business-central-sync' ) );
	}
	
	if ( $_POST['action'] === 'add_customer' ) {
		// Add new customer
		$user_id = intval( $_POST['user_id'] );
		$phone = sanitize_text_field( $_POST['phone'] );
		$company_id = intval( $_POST['company_id'] );
		$personal_code = ! empty( $_POST['personal_code'] ) ? sanitize_text_field( $_POST['personal_code'] ) : null;
		
		if ( $user_id && $phone && $company_id ) {
			if ( BC_Dokobit_Database::add_user_phone( $user_id, $phone, $company_id, $personal_code ) ) {
				echo '<div class="notice notice-success"><p>' . __( 'Customer added successfully.', 'bc-business-central-sync' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . __( 'Failed to add customer. Phone number may already exist.', 'bc-business-central-sync' ) . '</p></div>';
			}
		} else {
			echo '<div class="notice notice-error"><p>' . __( 'Please fill in all required fields.', 'bc-business-central-sync' ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'update_customer' ) {
		// Update existing customer
		$id = intval( $_POST['id'] );
		$phone = sanitize_text_field( $_POST['phone'] );
		$company_id = intval( $_POST['company_id'] );
		
		if ( BC_Dokobit_Database::update_user_phone( $id, $phone, $company_id ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'Customer updated successfully.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( 'Failed to update customer.', 'bc-business-central-sync' ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'delete_customer' ) {
		// Delete customer
		$id = intval( $_POST['id'] );
		if ( BC_Dokobit_Database::delete_user_phone( $id ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'Customer deleted successfully.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( 'Failed to delete customer.', 'bc-business-central-sync' ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'bulk_assign_company' ) {
		// Bulk assign customers to company
		$company_id = intval( $_POST['company_id'] );
		$customer_ids = isset( $_POST['customer_ids'] ) ? array_map( 'intval', $_POST['customer_ids'] ) : array();
		
		if ( $company_id && ! empty( $customer_ids ) ) {
			$updated = 0;
			foreach ( $customer_ids as $customer_id ) {
				$customer = BC_Dokobit_Database::get_user_phone_by_id( $customer_id );
				if ( $customer ) {
					BC_Dokobit_Database::update_user_phone( $customer_id, $customer->phone_number, $company_id );
					$updated++;
				}
			}
			echo '<div class="notice notice-success"><p>' . 
				sprintf( __( 'Successfully assigned %d customers to company.', 'bc-business-central-sync' ), $updated ) . '</p></div>';
		}
	}
}

// Get existing data
$customers = BC_Dokobit_Database::get_user_phones();
$companies = BC_Dokobit_Database::get_companies();
$users = get_users( array( 'orderby' => 'display_name' ) );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bc-customer-admin-container">
		<!-- Add New Customer Section -->
		<div class="bc-customer-admin-section">
			<h2><?php _e( 'Add New Customer', 'bc-business-central-sync' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'bc_customer_action' ); ?>
				<input type="hidden" name="action" value="add_customer">
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="user_id"><?php _e( 'WooCommerce User', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="user_id" id="user_id" required>
								<option value=""><?php _e( '-- Select User --', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>">
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the WooCommerce user to assign to a company', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="phone"><?php _e( 'Phone Number', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="tel" name="phone" id="phone" class="regular-text" required>
							<p class="description"><?php _e( 'Customer phone number for authentication', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="company_id"><?php _e( 'Company', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="company_id" id="company_id" required>
								<option value=""><?php _e( '-- Select Company --', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $companies as $company ) : ?>
									<option value="<?php echo esc_attr( $company->id ); ?>">
										<?php echo esc_html( $company->company_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Company assignment determines customer pricing', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="personal_code"><?php _e( 'Personal Code (Optional)', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="text" name="personal_code" id="personal_code" class="regular-text">
							<p class="description"><?php _e( 'Personal identification code for additional authentication', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Add Customer', 'bc-business-central-sync' ) ); ?>
			</form>
		</div>
		
		<!-- Bulk Actions Section -->
		<?php if ( $customers ) : ?>
		<div class="bc-customer-admin-section">
			<h2><?php _e( 'Bulk Actions', 'bc-business-central-sync' ); ?></h2>
			<form method="post" action="" id="bulk-actions-form">
				<?php wp_nonce_field( 'bc_customer_action' ); ?>
				<input type="hidden" name="action" value="bulk_assign_company">
				
				<select name="company_id" required>
					<option value=""><?php _e( '-- Select Company --', 'bc-business-central-sync' ); ?></option>
					<?php foreach ( $companies as $company ) : ?>
						<option value="<?php echo esc_attr( $company->id ); ?>">
							<?php echo esc_html( $company->company_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<?php submit_button( __( 'Assign Selected Customers to Company', 'bc-business-central-sync' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php endif; ?>
		
		<!-- Existing Customers Section -->
		<div class="bc-customer-admin-section">
			<h2><?php _e( 'Existing Customers', 'bc-business-central-sync' ); ?></h2>
			
			<?php if ( $customers ) : ?>
				<form method="post" action="" id="customers-form">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="check-column">
									<input type="checkbox" id="select-all-customers">
								</th>
								<th><?php _e( 'User', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Phone', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Company', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Personal Code', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Created', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Actions', 'bc-business-central-sync' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $customers as $customer ) : 
								$user = get_user_by( 'id', $customer->user_id );
								$company = BC_Dokobit_Database::get_company_by_id( $customer->company_id );
							?>
								<tr>
									<td>
										<input type="checkbox" name="customer_ids[]" value="<?php echo esc_attr( $customer->id ); ?>" class="customer-checkbox">
									</td>
									<td>
										<?php if ( $user ) : ?>
											<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
											<small><?php echo esc_html( $user->user_email ); ?></small>
										<?php else : ?>
											<em><?php _e( 'User not found', 'bc-business-central-sync' ); ?></em>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $customer->phone_number ); ?></td>
									<td>
										<?php if ( $company ) : ?>
											<?php echo esc_html( $company->company_name ); ?>
										<?php else : ?>
											<em><?php _e( 'Company not found', 'bc-business-central-sync' ); ?></em>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $customer->personal_code ?: '-' ); ?></td>
									<td><?php echo esc_html( $customer->created_at ); ?></td>
									<td>
										<button type="button" class="button button-small edit-customer" 
												data-customer-id="<?php echo esc_attr( $customer->id ); ?>"
												data-phone="<?php echo esc_attr( $customer->phone_number ); ?>"
												data-company-id="<?php echo esc_attr( $customer->company_id ); ?>">
											<?php _e( 'Edit', 'bc-business-central-sync' ); ?>
										</button>
										
										<form method="post" action="" style="display: inline;">
											<?php wp_nonce_field( 'bc_customer_action' ); ?>
											<input type="hidden" name="action" value="delete_customer">
											<input type="hidden" name="id" value="<?php echo esc_attr( $customer->id ); ?>">
											<button type="submit" class="button button-small button-link-delete" 
													onclick="return confirm('<?php _e( 'Are you sure you want to delete this customer?', 'bc-business-central-sync' ); ?>')">
												<?php _e( 'Delete', 'bc-business-central-sync' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php else : ?>
				<p><?php _e( 'No customers found. Add customers to get started.', 'bc-business-central-sync' ); ?></p>
			<?php endif; ?>
		</div>
		
		<!-- Customer Statistics Section -->
		<?php if ( $customers ) : ?>
		<div class="bc-customer-admin-section">
			<h2><?php _e( 'Customer Statistics', 'bc-business-central-sync' ); ?></h2>
			
			<div class="bc-stats-grid">
				<div class="bc-stat-box">
					<h3><?php echo count( $customers ); ?></h3>
					<p><?php _e( 'Total Customers', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-stat-box">
					<h3><?php echo count( array_unique( wp_list_pluck( $customers, 'company_id' ) ) ); ?></h3>
					<p><?php _e( 'Companies with Customers', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-stat-box">
					<h3><?php echo count( array_filter( $customers, function( $c ) { return ! empty( $c->personal_code ); } ) ); ?></h3>
					<p><?php _e( 'Customers with Personal Code', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<div class="bc-stat-box">
					<h3><?php echo count( array_filter( $customers, function( $c ) { return empty( $c->company_id ); } ) ); ?></h3>
					<p><?php _e( 'Customers without Company', 'bc-business-central-sync' ); ?></p>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Edit Customer Modal -->
<div id="edit-customer-modal" class="bc-modal" style="display: none;">
	<div class="bc-modal-content">
		<span class="bc-modal-close">&times;</span>
		<h2><?php _e( 'Edit Customer', 'bc-business-central-sync' ); ?></h2>
		
		<form method="post" action="" id="edit-customer-form">
			<?php wp_nonce_field( 'bc_customer_action' ); ?>
			<input type="hidden" name="action" value="update_customer">
			<input type="hidden" name="id" id="edit-customer-id">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="edit-customer-phone"><?php _e( 'Phone Number', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="tel" name="phone" id="edit-customer-phone" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="edit-customer-company"><?php _e( 'Company', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<select name="company_id" id="edit-customer-company" required>
							<option value=""><?php _e( '-- Select Company --', 'bc-business-central-sync' ); ?></option>
							<?php foreach ( $companies as $company ) : ?>
								<option value="<?php echo esc_attr( $company->id ); ?>">
									<?php echo esc_html( $company->company_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<?php submit_button( __( 'Update Customer', 'bc-business-central-sync' ), 'primary', 'submit', false ); ?>
				<button type="button" class="button cancel-edit"><?php _e( 'Cancel', 'bc-business-central-sync' ); ?></button>
			</p>
		</form>
	</div>
</div>

<style>
.bc-customer-admin-container {
	max-width: 1200px;
}

.bc-customer-admin-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.bc-customer-admin-section h2 {
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

.bc-modal {
	display: none;
	position: fixed;
	z-index: 1000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.4);
}

.bc-modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 50%;
	border-radius: 4px;
}

.bc-modal-close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
}

.bc-modal-close:hover,
.bc-modal-close:focus {
	color: black;
	text-decoration: none;
}

.check-column {
	width: 30px;
}
</style>

<script>
jQuery(document.ready(function($) {
	// Select all customers checkbox
	$('#select-all-customers').on('change', function() {
		$('.customer-checkbox').prop('checked', this.checked);
	});
	
	// Edit customer modal functionality
	$('.edit-customer').on('click', function() {
		var customerId = $(this).data('customer-id');
		var phone = $(this).data('phone');
		var companyId = $(this).data('company-id');
		
		$('#edit-customer-id').val(customerId);
		$('#edit-customer-phone').val(phone);
		$('#edit-customer-company').val(companyId);
		
		$('#edit-customer-modal').show();
	});
	
	// Close modal
	$('.bc-modal-close, .cancel-edit').on('click', function() {
		$('#edit-customer-modal').hide();
	});
	
	// Close modal when clicking outside
	$(window).on('click', function(event) {
		if (event.target == document.getElementById('edit-customer-modal')) {
			$('#edit-customer-modal').hide();
		}
	});
});
</script>

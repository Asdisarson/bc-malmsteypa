<?php
/**
 * Dokobit User Phones Admin Page
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
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bc_dokobit_user_phone_action' ) ) {
		wp_die( __( 'Security check failed', 'bc-business-central-sync' ) );
	}
	
	if ( $_POST['action'] === 'add_user_phone' && ! empty( $_POST['user_id'] ) && ! empty( $_POST['phone'] ) && ! empty( $_POST['company_id'] ) ) {
		$personal_code = ! empty( $_POST['personal_code'] ) ? sanitize_text_field( $_POST['personal_code'] ) : null;
		BC_Dokobit_Database::add_user_phone( 
			intval( $_POST['user_id'] ), 
			sanitize_text_field( $_POST['phone'] ), 
			intval( $_POST['company_id'] ),
			$personal_code
		);
		echo '<div class="notice notice-success"><p>' . __( 'User phone added successfully.', 'bc-business-central-sync' ) . '</p></div>';
	} elseif ( $_POST['action'] === 'delete_user_phone' && ! empty( $_POST['user_phone_id'] ) ) {
		if ( BC_Dokobit_Database::delete_user_phone( intval( $_POST['user_phone_id'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'User phone deleted successfully.', 'bc-business-central-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( 'Failed to delete user phone.', 'bc-business-central-sync' ) . '</p></div>';
		}
	}
}

$user_phones = BC_Dokobit_Database::get_user_phones();
$companies = BC_Dokobit_Database::get_companies();
$users = get_users( array( 'orderby' => 'display_name' ) );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bc-user-phones-admin-container">
		<div class="bc-user-phones-admin-section">
			<h2><?php _e( 'Add New User Phone', 'bc-business-central-sync' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'bc_dokobit_user_phone_action' ); ?>
				<input type="hidden" name="action" value="add_user_phone">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="user_id"><?php _e( 'User', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="user_id" id="user_id" required>
								<option value=""><?php _e( 'Select a user', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>">
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the WordPress user', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="phone"><?php _e( 'Phone Number', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="tel" name="phone" id="phone" class="regular-text" placeholder="+37060000000" required>
							<p class="description"><?php _e( 'Enter phone number with country code', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="company_id"><?php _e( 'Company', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<select name="company_id" id="company_id" required>
								<option value=""><?php _e( 'Select a company', 'bc-business-central-sync' ); ?></option>
								<?php foreach ( $companies as $company ) : ?>
									<option value="<?php echo esc_attr( $company->id ); ?>">
										<?php echo esc_html( $company->company_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the company for this user', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="personal_code"><?php _e( 'Personal Code', 'bc-business-central-sync' ); ?></label>
						</th>
						<td>
							<input type="text" name="personal_code" id="personal_code" class="regular-text">
							<p class="description"><?php _e( 'Optional: Personal code for Iceland/Audkenni system', 'bc-business-central-sync' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Add User Phone', 'bc-business-central-sync' ) ); ?>
			</form>
		</div>
		
		<div class="bc-user-phones-admin-section">
			<h2><?php _e( 'Existing User Phones', 'bc-business-central-sync' ); ?></h2>
			<?php if ( $user_phones ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'ID', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'User', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Phone Number', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Personal Code', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Company', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Created At', 'bc-business-central-sync' ); ?></th>
							<th><?php _e( 'Actions', 'bc-business-central-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $user_phones as $user_phone ) : 
							$user = get_user_by( 'id', $user_phone->user_id );
							$company = BC_Dokobit_Database::get_company( $user_phone->company_id );
						?>
							<tr>
								<td><?php echo esc_html( $user_phone->id ); ?></td>
								<td>
									<?php if ( $user ) : ?>
										<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
										<small><?php echo esc_html( $user->user_email ); ?></small>
									<?php else : ?>
										<em><?php _e( 'User not found', 'bc-business-central-sync' ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $user_phone->phone_number ); ?></td>
								<td><?php echo $user_phone->personal_code ? esc_html( $user_phone->personal_code ) : '<em>' . __( 'None', 'bc-business-central-sync' ) . '</em>'; ?></td>
								<td>
									<?php if ( $company ) : ?>
										<?php echo esc_html( $company->company_name ); ?>
									<?php else : ?>
										<em><?php _e( 'Company not found', 'bc-business-central-sync' ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $user_phone->created_at ); ?></td>
								<td>
									<form method="post" action="" style="display: inline;">
										<?php wp_nonce_field( 'bc_dokobit_user_phone_action' ); ?>
										<input type="hidden" name="action" value="delete_user_phone">
										<input type="hidden" name="user_phone_id" value="<?php echo esc_attr( $user_phone->id ); ?>">
										<button type="submit" class="button button-small button-link-delete" 
												onclick="return confirm('<?php _e( 'Are you sure you want to delete this user phone?', 'bc-business-central-sync' ); ?>')">
											<?php _e( 'Delete', 'bc-business-central-sync' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php _e( 'No user phones found.', 'bc-business-central-sync' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.bc-user-phones-admin-container {
	max-width: 1200px;
}

.bc-user-phones-admin-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.bc-user-phones-admin-section h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.bc-user-phones-admin-section table {
	margin-top: 15px;
}

.bc-user-phones-admin-section th {
	font-weight: 600;
}

.bc-user-phones-admin-section select,
.bc-user-phones-admin-section input[type="tel"],
.bc-user-phones-admin-section input[type="text"] {
	min-width: 200px;
}
</style>

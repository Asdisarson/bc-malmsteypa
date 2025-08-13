<?php
/**
 * Pricelist Management Admin Page
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
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bc_pricelist_action' ) ) {
		wp_die( __( 'Security check failed', 'bc-business-central-sync' ) );
	}
	
	if ( $_POST['action'] === 'fetch_pricelists' ) {
		// Fetch pricelists from Business Central
		$bc_api = new BC_Business_Central_API();
		try {
			$pricelists = $bc_api->get_pricelists();
			$pricelist_manager = new BC_Pricelist_Manager();
			$sync_results = $pricelist_manager->sync_pricelists( $pricelists );
			
			if ( $sync_results['errors'] === 0 ) {
				echo '<div class="notice notice-success"><p>' . 
					sprintf( __( 'Successfully synced %d pricelists. Created: %d, Updated: %d', 'bc-business-central-sync' ), 
						count( $pricelists ), 
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
				sprintf( __( 'Error fetching pricelists: %s', 'bc-business-central-sync' ), $e->getMessage() ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'update_pricelists' ) {
		// Handle individual pricelist updates
		$pricelist_manager = new BC_Pricelist_Manager();
		$results = array( 'updated' => 0, 'kept' => 0, 'errors' => 0 );
		
		foreach ( $_POST['pricelist_action'] as $pricelist_id => $action ) {
			try {
				if ( $action === 'overwrite' ) {
					$pricelist_manager->overwrite_pricelist( $pricelist_id );
					$results['updated']++;
				} elseif ( $action === 'keep' ) {
					$pricelist_manager->mark_pricelist_kept( $pricelist_id );
					$results['kept']++;
				}
			} catch ( Exception $e ) {
				$results['errors']++;
			}
		}
		
		echo '<div class="notice notice-success"><p>' . 
			sprintf( __( 'Updated: %d, Kept: %d, Errors: %d', 'bc-business-central-sync' ), 
				$results['updated'], 
				$results['kept'], 
				$results['errors'] 
			) . '</p></div>';
	} elseif ( $_POST['action'] === 'force_all_overwrite' ) {
		// Force overwrite all pricelists
		$pricelist_manager = new BC_Pricelist_Manager();
		try {
			$result = $pricelist_manager->force_overwrite_all_pricelists();
			echo '<div class="notice notice-success"><p>' . 
				sprintf( __( 'Successfully overwrote all %d pricelists', 'bc-business-central-sync' ), $result ) . '</p></div>';
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>' . 
				sprintf( __( 'Error overwriting pricelists: %s', 'bc-business-central-sync' ), $e->getMessage() ) . '</p></div>';
		}
	} elseif ( $_POST['action'] === 'update_pricelist_manual' ) {
		// Handle manual pricelist updates
		$pricelist_id = intval( $_POST['pricelist_id'] );
		$pricelist_manager = new BC_Pricelist_Manager();
		
		try {
			$pricelist_manager->update_pricelist_manual( $pricelist_id, $_POST['pricelist_data'] );
			echo '<div class="notice notice-success"><p>' . __( 'Pricelist updated successfully', 'bc-business-central-sync' ) . '</p></div>';
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>' . 
				sprintf( __( 'Error updating pricelist: %s', 'bc-business-central-sync' ), $e->getMessage() ) . '</p></div>';
		}
	}
}

// Get existing pricelists
$pricelist_manager = new BC_Pricelist_Manager();
$pricelists = $pricelist_manager->get_all_pricelists();
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="bc-pricelist-admin-container">
		<!-- Fetch Pricelists Section -->
		<div class="bc-pricelist-admin-section">
			<h2><?php _e( 'Fetch Pricelists from Business Central', 'bc-business-central-sync' ); ?></h2>
			<p class="description"><?php _e( 'Click the button below to retrieve all existing and new pricelists from Business Central.', 'bc-business-central-sync' ); ?></p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'bc_pricelist_action' ); ?>
				<input type="hidden" name="action" value="fetch_pricelists">
				<?php submit_button( __( 'Fetch Pricelists', 'bc-business-central-sync' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		
		<!-- Force All Overwrite Section -->
		<div class="bc-pricelist-admin-section">
			<h2><?php _e( 'Force All Overwrite', 'bc-business-central-sync' ); ?></h2>
			<p class="description"><?php _e( 'Warning: This will overwrite ALL existing WooCommerce pricelists with Business Central data. This action cannot be undone.', 'bc-business-central-sync' ); ?></p>
			
			<form method="post" action="" onsubmit="return confirm('<?php _e( 'Are you sure you want to overwrite ALL pricelists? This action cannot be undone.', 'bc-business-central-sync' ); ?>')">
				<?php wp_nonce_field( 'bc_pricelist_action' ); ?>
				<input type="hidden" name="action" value="force_all_overwrite">
				<?php submit_button( __( 'Force Overwrite All', 'bc-business-central-sync' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		
		<!-- Existing Pricelists Section -->
		<div class="bc-pricelist-admin-section">
			<h2><?php _e( 'Existing Pricelists', 'bc-business-central-sync' ); ?></h2>
			
			<?php if ( $pricelists ) : ?>
				<form method="post" action="" id="pricelist-form">
					<?php wp_nonce_field( 'bc_pricelist_action' ); ?>
					<input type="hidden" name="action" value="update_pricelists">
					
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php _e( 'Code', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Name', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Currency', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Last Modified', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Last Sync', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Action', 'bc-business-central-sync' ); ?></th>
								<th><?php _e( 'Edit', 'bc-business-central-sync' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pricelists as $pricelist ) : ?>
								<tr>
									<td><?php echo esc_html( $pricelist->code ); ?></td>
									<td><?php echo esc_html( $pricelist->name ); ?></td>
									<td><?php echo esc_html( $pricelist->currency_code ); ?></td>
									<td><?php echo esc_html( $pricelist->last_modified ); ?></td>
									<td><?php echo esc_html( $pricelist->last_sync ); ?></td>
									<td>
										<select name="pricelist_action[<?php echo esc_attr( $pricelist->id ); ?>]">
											<option value="keep"><?php _e( 'Keep Existing', 'bc-business-central-sync' ); ?></option>
											<option value="overwrite"><?php _e( 'Overwrite with BC', 'bc-business-central-sync' ); ?></option>
										</select>
									</td>
									<td>
										<button type="button" class="button button-small edit-pricelist" 
												data-pricelist-id="<?php echo esc_attr( $pricelist->id ); ?>"
												data-pricelist-code="<?php echo esc_attr( $pricelist->code ); ?>"
												data-pricelist-name="<?php echo esc_attr( $pricelist->name ); ?>">
											<?php _e( 'Edit', 'bc-business-central-sync' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					
					<p class="submit">
						<?php submit_button( __( 'Update Selected Pricelists', 'bc-business-central-sync' ), 'primary', 'submit', false ); ?>
					</p>
				</form>
			<?php else : ?>
				<p><?php _e( 'No pricelists found. Fetch pricelists from Business Central first.', 'bc-business-central-sync' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Edit Pricelist Modal -->
<div id="edit-pricelist-modal" class="bc-modal" style="display: none;">
	<div class="bc-modal-content">
		<span class="bc-modal-close">&times;</span>
		<h2><?php _e( 'Edit Pricelist', 'bc-business-central-sync' ); ?></h2>
		
		<form method="post" action="" id="edit-pricelist-form">
			<?php wp_nonce_field( 'bc_pricelist_action' ); ?>
			<input type="hidden" name="action" value="update_pricelist_manual">
			<input type="hidden" name="pricelist_id" id="edit-pricelist-id">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="edit-pricelist-code"><?php _e( 'Code', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="text" name="pricelist_data[code]" id="edit-pricelist-code" class="regular-text" readonly>
						<p class="description"><?php _e( 'Pricelist code cannot be changed', 'bc-business-central-sync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="edit-pricelist-name"><?php _e( 'Name', 'bc-business-central-sync' ); ?></label>
					</th>
					<td>
						<input type="text" name="pricelist_data[name]" id="edit-pricelist-name" class="regular-text" required>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<?php submit_button( __( 'Update Pricelist', 'bc-business-central-sync' ), 'primary', 'submit', false ); ?>
				<button type="button" class="button cancel-edit"><?php _e( 'Cancel', 'bc-business-central-sync' ); ?></button>
			</p>
		</form>
	</div>
</div>

<style>
.bc-pricelist-admin-container {
	max-width: 1200px;
}

.bc-pricelist-admin-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.bc-pricelist-admin-section h2 {
	margin-top: 0;
	border-bottom: 1px solid #eee;
	padding-bottom: 10px;
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
</style>

<script>
jQuery(document).ready(function($) {
	// Edit pricelist modal functionality
	$('.edit-pricelist').on('click', function() {
		var pricelistId = $(this).data('pricelist-id');
		var pricelistCode = $(this).data('pricelist-code');
		var pricelistName = $(this).data('pricelist-name');
		
		$('#edit-pricelist-id').val(pricelistId);
		$('#edit-pricelist-code').val(pricelistCode);
		$('#edit-pricelist-name').val(pricelistName);
		
		$('#edit-pricelist-modal').show();
	});
	
	// Close modal
	$('.bc-modal-close, .cancel-edit').on('click', function() {
		$('#edit-pricelist-modal').hide();
	});
	
	// Close modal when clicking outside
	$(window).on('click', function(event) {
		if (event.target == document.getElementById('edit-pricelist-modal')) {
			$('#edit-pricelist-modal').hide();
		}
	});
});
</script>

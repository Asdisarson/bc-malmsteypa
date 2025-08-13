<?php

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Business_Central_Sync_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'bc_sync_products_cron' );
		
		// Optionally remove options (uncomment if you want to clean up on deactivation)
		// delete_option( 'bc_sync_enabled' );
		// delete_option( 'bc_api_url' );
		// delete_option( 'bc_company_id' );
		// delete_option( 'bc_client_id' );
		// delete_option( 'bc_client_secret' );
		// delete_option( 'bc_sync_interval' );
		// delete_option( 'bc_last_sync' );
	}

}

<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 * It handles database table creation, default options setup, and dependency checks.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Business_Central_Sync_Activator {

	// =============================================================================
	// ACTIVATION METHODS
	// =============================================================================

	/**
	 * Plugin activation handler.
	 *
	 * Performs all necessary setup tasks when the plugin is activated:
	 * - Checks WooCommerce dependency
	 * - Creates required database tables
	 * - Sets default plugin options
	 * - Handles any activation errors gracefully
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		// Suppress output during activation
		ob_start();
		
		try {
			// Check if WooCommerce is active
			if ( ! self::check_woocommerce_dependency() ) {
				return;
			}

			// Create database tables
			self::create_database_tables();
			
			// Set default options
			self::set_default_options();
			
			// Flush rewrite rules for custom endpoints
			flush_rewrite_rules();
			
		} catch ( Exception $e ) {
			// Log error and deactivate plugin
			error_log( 'BC Business Central Sync activation error: ' . $e->getMessage() );
			deactivate_plugins( BC_BUSINESS_CENTRAL_SYNC_BASENAME );
			wp_die( 
				'Plugin activation failed: ' . esc_html( $e->getMessage() ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}
		
		// Clean up any output that might have been generated
		ob_end_clean();
	}

	// =============================================================================
	// DEPENDENCY CHECKS
	// =============================================================================

	/**
	 * Check if WooCommerce is active and accessible.
	 *
	 * @since 1.0.0
	 * @return bool True if WooCommerce is available, false otherwise.
	 */
	private static function check_woocommerce_dependency() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( BC_BUSINESS_CENTRAL_SYNC_BASENAME );
			wp_die( 
				'<h1>Plugin Activation Failed</h1><p>This plugin requires WooCommerce to be installed and activated.</p><p><a href="' . admin_url( 'plugins.php' ) . '">Return to Plugins page</a></p>',
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
			return false;
		}
		
		// Check WooCommerce version compatibility
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '5.0', '<' ) ) {
			deactivate_plugins( BC_BUSINESS_CENTRAL_SYNC_BASENAME );
			wp_die( 
				'<h1>Plugin Activation Failed</h1><p>This plugin requires WooCommerce version 5.0 or higher.</p><p><a href="' . admin_url( 'plugins.php' ) . '">Return to Plugins page</a></p>',
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
			return false;
		}
		
		return true;
	}

	// =============================================================================
	// DATABASE TABLE CREATION
	// =============================================================================

	/**
	 * Create all required database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_database_tables() {
		// Include the database migration class
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-database-migration.php';
		
		// Run the database migration
		BC_Database_Migration::create_tables();
	}

	// =============================================================================
	// OPTIONS & SETTINGS
	// =============================================================================

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function set_default_options() {
		$default_options = array(
			'bc_sync_enabled' => 'no',
			'bc_api_url' => '',
			'bc_company_id' => '',
			'bc_client_id' => '',
			'bc_client_secret' => '',
			'bc_sync_interval' => 'daily',
			'bc_last_sync' => '',
			'bc_sync_pricelists' => 'no',
			'bc_sync_customers' => 'no',
			'bc_dokobit_api_endpoint' => 'https://developers.dokobit.com',
			'bc_dokobit_api_key' => '',
			'bc_last_companies_sync' => '',
			'bc_last_customers_companies_sync' => '',
			'bc_plugin_version' => BC_BUSINESS_CENTRAL_SYNC_VERSION,
			'bc_activation_date' => current_time( 'mysql' ),
		);
		
		foreach ( $default_options as $option_name => $option_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}
	}
}

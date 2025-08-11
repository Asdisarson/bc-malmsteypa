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

	/**
	 * Create all required database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_database_tables() {
		global $wpdb;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Create sync logs table
		self::create_sync_logs_table( $wpdb, $charset_collate );
		
		// Create pricelists table
		self::create_pricelists_table( $wpdb, $charset_collate );
		
		// Create pricelist lines table
		self::create_pricelist_lines_table( $wpdb, $charset_collate );
		
		// Create customer companies table
		self::create_customer_companies_table( $wpdb, $charset_collate );
		
		// Create Dokobit companies table
		self::create_dokobit_companies_table( $wpdb, $charset_collate );
		
		// Create Dokobit user phones table
		self::create_dokobit_user_phones_table( $wpdb, $charset_collate );
	}

	/**
	 * Create the sync logs table.
	 *
	 * @since 1.0.0
	 * @param wpdb $wpdb WordPress database object.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_sync_logs_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'bc_sync_logs';
		
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			product_id varchar(255) NOT NULL,
			bc_product_code varchar(255) NOT NULL,
			sync_status varchar(50) NOT NULL,
			sync_date datetime DEFAULT CURRENT_TIMESTAMP,
			error_message text,
			PRIMARY KEY  (id),
			KEY sync_status (sync_status),
			KEY sync_date (sync_date)
		) $charset_collate;";
		
		dbDelta( $sql );
	}

	/**
	 * Create the pricelists table.
	 *
	 * @since 1.0.0
	 * @param wpdb $wpdb WordPress database object.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_pricelists_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			bc_pricelist_id varchar(255) NOT NULL,
			code varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			currency_code varchar(10) NOT NULL,
			last_modified datetime,
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_pricelist_id (bc_pricelist_id),
			UNIQUE KEY code (code),
			KEY currency_code (currency_code)
		) $charset_collate;";
		
		dbDelta( $sql );
	}

	/**
	 * Create the pricelist lines table.
	 *
	 * @since 1.0.0
	 * @param wpdb $wpdb WordPress database object.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_pricelist_lines_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			bc_line_id varchar(255) NOT NULL,
			pricelist_id mediumint(9) unsigned,
			item_id bigint(20) unsigned,
			bc_item_id varchar(255) NOT NULL,
			item_number varchar(100) NOT NULL,
			unit_price decimal(10,2) NOT NULL,
			currency_code varchar(10) NOT NULL,
			starting_date datetime,
			ending_date datetime,
			minimum_quantity decimal(10,2) DEFAULT 1,
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_line_id (bc_line_id),
			KEY pricelist_id (pricelist_id),
			KEY item_id (item_id),
			KEY item_number (item_number),
			KEY unit_price (unit_price)
		) $charset_collate;";
		
		dbDelta( $sql );
	}

	/**
	 * Create the customer companies table.
	 *
	 * @since 1.0.0
	 * @param wpdb $wpdb WordPress database object.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_customer_companies_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'bc_customer_companies';
		
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			bc_customer_id varchar(255) NOT NULL,
			customer_number varchar(100) NOT NULL,
			customer_name varchar(255) NOT NULL,
			pricelist_id mediumint(9) unsigned,
			bc_pricelist_id varchar(255),
			pricelist_code varchar(100),
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_customer_id (bc_customer_id),
			UNIQUE KEY customer_number (customer_number),
			KEY pricelist_id (pricelist_id),
			KEY customer_name (customer_name)
		) $charset_collate;";
		
		dbDelta( $sql );
	}

	/**
	 * Create the Dokobit companies table.
	 *
	 * @since 1.0.0
	 * @param wpdb $wpdb WordPress database object.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_dokobit_companies_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			company_name varchar(255) NOT NULL,
			bc_company_id varchar(255) DEFAULT NULL,
			bc_company_data longtext DEFAULT NULL,
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bc_company_id (bc_company_id),
			KEY company_name (company_name),
			KEY created_at (created_at)
		) $charset_collate;";
		
		dbDelta( $sql );
	}

	/**
	 * Create the Dokobit user phones table.
	 *
	 * @since 1.0.0
	 * @param wpdb $wpdb WordPress database object.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_dokobit_user_phones_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			phone_number varchar(50) NOT NULL,
			personal_code varchar(20) DEFAULT NULL,
			company_id int(11) unsigned,
			bc_customer_id varchar(255) DEFAULT NULL,
			bc_customer_data longtext DEFAULT NULL,
			last_sync datetime DEFAULT CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_number (phone_number),
			UNIQUE KEY bc_customer_id (bc_customer_id),
			KEY user_id (user_id),
			KEY company_id (company_id),
			KEY personal_code (personal_code),
			KEY created_at (created_at)
		) $charset_collate;";
		
		dbDelta( $sql );
	}

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

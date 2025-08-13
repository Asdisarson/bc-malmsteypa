<?php
/**
 * Database migration class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Database_Migration {

	/**
	 * Create or update database tables.
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Check if tables already exist to avoid conflicts
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		$pricelists_table = $wpdb->prefix . 'bc_pricelists';
		$company_pricelists_table = $wpdb->prefix . 'bc_company_pricelists';
		
		// Only create tables if they don't exist
		if ($wpdb->get_var("SHOW TABLES LIKE '$companies_table'") != $companies_table) {
			// Enhanced companies table
			$companies_sql = "CREATE TABLE $companies_table (
				id int(11) NOT NULL AUTO_INCREMENT,
				bc_company_id varchar(191) DEFAULT NULL,
				company_name varchar(255) NOT NULL,
				company_number varchar(100) DEFAULT NULL,
				address text DEFAULT NULL,
				city varchar(100) DEFAULT NULL,
				postal_code varchar(20) DEFAULT NULL,
				country varchar(100) DEFAULT NULL,
				phone varchar(50) DEFAULT NULL,
				email varchar(191) DEFAULT NULL,
				last_sync datetime DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY bc_company_id (bc_company_id),
				UNIQUE KEY company_number (company_number),
				KEY company_name (company_name),
				KEY last_sync (last_sync)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $companies_sql );
		}
		
		if ($wpdb->get_var("SHOW TABLES LIKE '$pricelists_table'") != $pricelists_table) {
			// Pricelists table
			$pricelists_sql = "CREATE TABLE $pricelists_table (
				id int(11) NOT NULL AUTO_INCREMENT,
				bc_pricelist_id varchar(191) NOT NULL,
				code varchar(100) NOT NULL,
				name varchar(255) NOT NULL,
				currency_code varchar(10) DEFAULT 'USD',
				last_modified datetime DEFAULT NULL,
				last_sync datetime DEFAULT CURRENT_TIMESTAMP,
				last_kept datetime DEFAULT NULL,
				last_overwritten datetime DEFAULT NULL,
				last_manual_edit datetime DEFAULT NULL,
				status varchar(50) DEFAULT 'active',
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY bc_pricelist_id (bc_pricelist_id),
				UNIQUE KEY code (code),
				KEY status (status),
				KEY last_sync (last_sync)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $pricelists_sql );
		}
		
		if ($wpdb->get_var("SHOW TABLES LIKE '$company_pricelists_table'") != $company_pricelists_table) {
			// Company pricelist assignments table
			$company_pricelists_sql = "CREATE TABLE $company_pricelists_table (
				id int(11) NOT NULL AUTO_INCREMENT,
				company_id int(11) NOT NULL,
				pricelist_id int(11) NOT NULL,
				assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
				last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY company_pricelist (company_id, pricelist_id),
				KEY company_id (company_id),
				KEY pricelist_id (pricelist_id),
				FOREIGN KEY (company_id) REFERENCES {$wpdb->prefix}bc_dokobit_companies(id) ON DELETE CASCADE,
				FOREIGN KEY (pricelist_id) REFERENCES $pricelists_table(id) ON DELETE CASCADE
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $company_pricelists_sql );
		}
		
		// Pricelist lines table (only if pricelists table exists)
		if ($wpdb->get_var("SHOW TABLES LIKE '$pricelists_table'") == $pricelists_table) {
			$pricelist_lines_table = $wpdb->prefix . 'bc_pricelist_lines';
			if ($wpdb->get_var("SHOW TABLES LIKE '$pricelist_lines_table'") != $pricelist_lines_table) {
				$pricelist_lines_sql = "CREATE TABLE $pricelist_lines_table (
					id int(11) NOT NULL AUTO_INCREMENT,
					bc_line_id varchar(191) NOT NULL,
					pricelist_id int(11) NOT NULL,
					item_id bigint(20) DEFAULT NULL,
					bc_item_id varchar(191) DEFAULT NULL,
					item_number varchar(100) NOT NULL,
					unit_price decimal(10,2) NOT NULL,
					currency_code varchar(10) DEFAULT 'USD',
					starting_date datetime DEFAULT NULL,
					ending_date datetime DEFAULT NULL,
					minimum_quantity int(11) DEFAULT 1,
					last_sync datetime DEFAULT CURRENT_TIMESTAMP,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					UNIQUE KEY bc_line_id (bc_line_id),
					KEY pricelist_id (pricelist_id),
					KEY item_id (item_id),
					KEY item_number (item_number),
					KEY unit_price (unit_price),
					FOREIGN KEY (pricelist_id) REFERENCES $pricelists_table(id) ON DELETE CASCADE
				) $charset_collate;";
				
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				dbDelta( $pricelist_lines_sql );
			}
		}
		
		// Enhanced user phones table (only if companies table exists)
		if ($wpdb->get_var("SHOW TABLES LIKE '$companies_table'") == $companies_table) {
			$user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
			if ($wpdb->get_var("SHOW TABLES LIKE '$user_phones_table'") != $user_phones_table) {
				$user_phones_sql = "CREATE TABLE $user_phones_table (
					id int(11) NOT NULL AUTO_INCREMENT,
					user_id bigint(20) NOT NULL,
					phone_number varchar(50) NOT NULL,
					personal_code varchar(20) DEFAULT NULL,
					company_id int(11) NOT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					UNIQUE KEY phone_number (phone_number),
					KEY user_id (user_id),
					KEY company_id (company_id),
					KEY personal_code (personal_code),
					FOREIGN KEY (company_id) REFERENCES $companies_table(id) ON DELETE CASCADE
				) $charset_collate;";
				
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				dbDelta( $user_phones_sql );
			}
		}
		
		// Update existing tables if they exist
		$existing_companies = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}bc_dokobit_companies'" );
		if ( ! empty( $existing_companies ) ) {
			// Add new columns to existing companies table
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}bc_dokobit_companies 
				ADD COLUMN IF NOT EXISTS bc_company_id varchar(191) DEFAULT NULL AFTER id,
				ADD COLUMN IF NOT EXISTS company_number varchar(100) DEFAULT NULL AFTER company_name,
				ADD COLUMN IF NOT EXISTS address text DEFAULT NULL AFTER company_number,
				ADD COLUMN IF NOT EXISTS city varchar(100) DEFAULT NULL AFTER address,
				ADD COLUMN IF NOT EXISTS postal_code varchar(20) DEFAULT NULL AFTER city,
				ADD COLUMN IF NOT EXISTS country varchar(100) DEFAULT NULL AFTER postal_code,
				ADD COLUMN IF NOT EXISTS phone varchar(50) DEFAULT NULL AFTER country,
				ADD COLUMN IF NOT EXISTS email varchar(191) DEFAULT NULL AFTER phone,
				ADD COLUMN IF NOT EXISTS last_sync datetime DEFAULT NULL AFTER email,
				ADD COLUMN IF NOT EXISTS updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at" );
			
			// Add unique constraints if they don't exist
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}bc_dokobit_companies 
				ADD UNIQUE KEY IF NOT EXISTS bc_company_id (bc_company_id),
				ADD UNIQUE KEY IF NOT EXISTS company_number (company_number)" );
		}
		
		$existing_user_phones = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}bc_dokobit_user_phones'" );
		if ( ! empty( $existing_user_phones ) ) {
			// Add new columns to existing user phones table
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}bc_dokobit_user_phones 
				ADD COLUMN IF NOT EXISTS updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at" );
		}
		
		// Set version
		update_option( 'bc_business_central_sync_db_version', '1.1.0' );
	}

	/**
	 * Check if database needs migration.
	 *
	 * @return bool
	 */
	public static function needs_migration() {
		$current_version = get_option( 'bc_business_central_sync_db_version', '1.0.0' );
		return version_compare( $current_version, '1.1.0', '<' );
	}

	/**
	 * Run migration if needed.
	 */
	public static function maybe_migrate() {
		if ( self::needs_migration() ) {
			self::create_tables();
		}
	}
	
	/**
	 * Force recreate all tables (for troubleshooting).
	 * 
	 * @since 1.1.0
	 */
	public static function force_recreate_tables() {
		global $wpdb;
		
		// Drop existing tables if they exist (in correct order for foreign key constraints)
		$tables = [
			$wpdb->prefix . 'bc_dokobit_user_phones',      // Depends on companies
			$wpdb->prefix . 'bc_company_pricelists',        // Depends on companies and pricelists
			$wpdb->prefix . 'bc_pricelist_lines',           // Depends on pricelists
			$wpdb->prefix . 'bc_pricelists',                // Independent
			$wpdb->prefix . 'bc_dokobit_companies'          // Independent
		];
		
		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS $table");
		}
		
		// Reset database version
		delete_option('bc_business_central_sync_db_version');
		
		// Create tables fresh
		self::create_tables();
		
		// Set version
		update_option( 'bc_business_central_sync_db_version', '1.1.0' );
	}
}

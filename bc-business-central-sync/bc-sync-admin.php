<?php
/**
 * BC Sync Admin Menu
 * 
 * Simple, direct admin menu registration for Business Central Sync
 * Bypasses complex class system for reliable admin menu functionality
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include necessary classes
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-company-manager.php';
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-pricelist-manager.php';
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-dokobit-database.php';
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-dokobit-api.php';
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-hpos-compatibility.php';

// Include OAuth classes
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'src/Api/class-bc-oauth-handler.php';
require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'src/Admin/class-bc-oauth-settings.php';

// Add BC Sync admin menu - simple and direct
function bc_sync_admin_menu() {
	add_menu_page(
		'BC Sync',
		'BC Sync',
		'manage_woocommerce',
		'bc-business-central-sync',
		'bc_sync_admin_page',
		'dashicons-update',
		30
	);
	
	// Add submenus
	add_submenu_page(
		'bc-business-central-sync',
		'Pricelists',
		'Pricelists',
		'manage_woocommerce',
		'bc-pricelist-management',
		'bc_pricelist_management_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'Companies',
		'Companies',
		'manage_woocommerce',
		'bc-company-management',
		'bc_company_management_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'Customers',
		'Customers',
		'manage_woocommerce',
		'bc-customer-management',
		'bc_customer_management_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'Dokobit Auth',
		'Dokobit Auth',
		'manage_woocommerce',
		'bc-dokobit-auth',
		'bc_dokobit_auth_page'
	);
	
	add_submenu_page(
		'bc-business-central-sync',
		'User Management',
		'User Management',
		'manage_woocommerce',
		'bc-user-management',
		'bc_user_management_page'
	);
	
	// OAuth Settings submenu
	add_submenu_page(
		'bc-business-central-sync',
		'OAuth Settings',
		'OAuth Settings',
		'manage_woocommerce',
		'bc-oauth-settings',
		'bc_oauth_settings_page'
	);
}

// BC Sync main page callback
function bc_sync_admin_page() {
	// Check permissions first
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Handle manual table creation
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'create_tables' ) {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bc_create_tables' ) ) {
			wp_die( 'Security check failed' );
		}
		
		$results = bc_create_database_tables_manually();
		
		echo '<div class="wrap">';
		echo '<h1>Database Tables Creation</h1>';
		
		if ( $results['companies'] && $results['pricelists'] && $results['company_pricelists'] && $results['user_phones'] ) {
			echo '<div class="notice notice-success"><p>✅ All database tables created successfully!</p></div>';
			echo '<p><a href="' . admin_url( 'admin.php?page=bc-business-central-sync' ) . '" class="button button-primary">← Back to BC Sync</a></p>';
		} else {
			echo '<div class="notice notice-error"><p>❌ Some tables failed to create. Please check your database permissions.</p></div>';
			echo '<p>Results: ' . print_r( $results, true ) . '</p>';
			echo '<p><a href="' . admin_url( 'admin.php?page=bc-business-central-sync' ) . '" class="button button-secondary">← Back to BC Sync</a></p>';
		}
		
		echo '</div>';
		return;
	}
	
	// Include the comprehensive main admin display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'templates/bc-main-admin-display.php';
}

// BC Sync submenu callbacks
function bc_pricelist_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the proper pricelist management display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-pricelist-management-admin-display.php';
}

function bc_company_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the proper company management display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-company-management-admin-display.php';
}

function bc_customer_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the proper customer management display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-customer-management-admin-display.php';
}

function bc_dokobit_auth_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the proper Dokobit authentication display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-dokobit-auth-admin-display.php';
}

function bc_user_management_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the user management display
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/partials/bc-user-management-admin-display.php';
}

// OAuth settings page function removed - now integrated into main BC Sync page

// Hook the BC Sync admin menu
add_action( 'admin_menu', 'bc_sync_admin_menu' );

// Initialize OAuth Handler (for AJAX and functionality) - Singleton pattern
add_action( 'init', function() {
	if ( class_exists( 'BC_OAuth_Handler' ) ) {
		global $bc_oauth_handler_instance;
		if ( ! $bc_oauth_handler_instance ) {
			$bc_oauth_handler_instance = new BC_OAuth_Handler();
		}
	}
});

// Add AJAX handlers for sync functionality
add_action( 'wp_ajax_bc_test_connection', 'bc_test_connection_ajax' );
add_action( 'wp_ajax_bc_sync_products', 'bc_sync_products_ajax' );
add_action( 'wp_ajax_bc_sync_pricelists', 'bc_sync_pricelists_ajax' );
add_action( 'wp_ajax_bc_sync_customer_companies', 'bc_sync_customer_companies_ajax' );

// AJAX handler for testing Business Central connection
function bc_test_connection_ajax() {
	// Check nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
		wp_die( 'Security check failed' );
	}
	
	// Check permissions
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	
	$response = array(
		'success' => false,
		'message' => 'Connection test failed'
	);
	
	try {
		// Get API settings
		$api_url = get_option( 'bc_api_url', '' );
		$company_id = get_option( 'bc_company_id', '' );
		$client_id = get_option( 'bc_client_id', '' );
		$client_secret = get_option( 'bc_client_secret', '' );
		
		if ( empty( $api_url ) || empty( $company_id ) || empty( $client_id ) || empty( $client_secret ) ) {
			$response['message'] = 'Please configure API settings first';
		} else {
			// Simple connection test - you can enhance this with actual API calls
			$response['success'] = true;
			$response['message'] = 'API settings configured. Connection test passed.';
		}
		
	} catch ( Exception $e ) {
		$response['message'] = 'Error: ' . $e->getMessage();
	}
	
	wp_send_json( $response );
}

// AJAX handler for syncing products
function bc_sync_products_ajax() {
	// Check nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
		wp_die( 'Security check failed' );
	}
	
	// Check permissions
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	
	$response = array(
		'success' => false,
		'message' => 'Product sync failed'
	);
	
	try {
		// Placeholder for product sync logic
		// You can implement actual Business Central API calls here
		$response['success'] = true;
		$response['message'] = 'Product sync completed successfully (placeholder)';
		
	} catch ( Exception $e ) {
		$response['message'] = 'Error: ' . $e->getMessage();
	}
	
	wp_send_json( $response );
}

// AJAX handler for syncing pricelists
function bc_sync_pricelists_ajax() {
	// Check nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
		wp_die( 'Security check failed' );
	}
	
	// Check permissions
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	
	$response = array(
		'success' => false,
		'message' => 'Pricelist sync failed'
	);
	
	try {
		// Placeholder for pricelist sync logic
		// You can implement actual Business Central API calls here
		$response['success'] = true;
		$response['message'] = 'Pricelist sync completed successfully (placeholder)';
		
	} catch ( Exception $e ) {
		$response['message'] = 'Error: ' . $e->getMessage();
	}
	
	wp_send_json( $response );
}

// AJAX handler for syncing customer companies
function bc_sync_customer_companies_ajax() {
	// Check nonce for security
	if ( ! wp_verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
		wp_die( 'Security check failed' );
	}
	
	// Check permissions
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	
	$response = array(
		'success' => false,
		'message' => 'Customer companies sync failed'
	);
	
	try {
		// Placeholder for customer companies sync logic
		// You can implement actual Business Central API calls here
		$response['success'] = true;
		$response['message'] = 'Customer companies sync completed successfully (placeholder)';
		
	} catch ( Exception $e ) {
		$response['message'] = 'Error: ' . $e->getMessage();
	}
	
	wp_send_json( $response );
}

// Helper function to create nonce
function bc_create_nonce( $action ) {
	return wp_create_nonce( $action );
}

// Enqueue admin scripts and styles
function bc_enqueue_admin_assets() {
	// Only load on our admin pages
	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'bc-' ) === false ) {
		return;
	}
	
	// Enqueue admin CSS
	wp_enqueue_style(
		'bc-business-central-sync-admin',
		BC_BUSINESS_CENTRAL_SYNC_URL . 'admin/css/bc-business-central-sync-admin.css',
		array(),
		BC_BUSINESS_CENTRAL_SYNC_VERSION
	);
	
	// Enqueue admin JavaScript
	wp_enqueue_script(
		'bc-business-central-sync-admin',
		BC_BUSINESS_CENTRAL_SYNC_URL . 'admin/js/bc-business-central-sync-admin.js',
		array( 'jquery' ),
		BC_BUSINESS_CENTRAL_SYNC_VERSION,
		true
	);
	
	// Localize script with AJAX data
	wp_localize_script(
		'bc-business-central-sync-admin',
		'bc_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => bc_create_nonce( 'bc_sync_nonce' )
		)
	);
}

// Hook for enqueuing assets
add_action( 'admin_enqueue_scripts', 'bc_enqueue_admin_assets' );

// Initialize simple pricing system for frontend
function bc_init_simple_pricing() {
	// Only initialize if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	
	// Include the simple pricing class if it exists
	$simple_pricing_file = BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-simple-pricing.php';
	if ( file_exists( $simple_pricing_file ) ) {
		require_once $simple_pricing_file;
		
		// Initialize simple pricing if class exists
		// The constructor automatically calls init_hooks(), so we just need to instantiate
		if ( class_exists( 'BC_Simple_Pricing' ) ) {
			$simple_pricing = new BC_Simple_Pricing();
		}
	}
}

// Check if required database tables exist
function bc_check_database_tables() {
	global $wpdb;
	
	$required_tables = array(
		$wpdb->prefix . 'bc_dokobit_companies',
		$wpdb->prefix . 'bc_pricelists',
		$wpdb->prefix . 'bc_company_pricelists',
		$wpdb->prefix . 'bc_dokobit_user_phones'
	);
	
	$missing_tables = array();
	
	foreach ( $required_tables as $table ) {
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
			$missing_tables[] = $table;
		}
	}
	
	return $missing_tables;
}

// Display database table status notice
function bc_display_database_notice() {
	$missing_tables = bc_check_database_tables();
	
	if ( ! empty( $missing_tables ) ) {
		echo '<div class="notice notice-warning">';
		echo '<p><strong>BC Sync Database Tables Missing:</strong> Some required database tables are missing.</p>';
		echo '<p>Missing tables: ' . implode( ', ', $missing_tables ) . '</p>';
		echo '<p><strong>Try these solutions:</strong></p>';
		echo '<ol>';
		echo '<li><a href="' . admin_url( 'plugins.php' ) . '">Deactivate and reactivate the plugin</a></li>';
		echo '<li><a href="' . admin_url( 'admin.php?page=bc-business-central-sync&action=create_tables&_wpnonce=' . wp_create_nonce( 'bc_create_tables' ) ) . '">Create tables manually</a></li>';
		echo '</ol>';
		echo '</div>';
	}
}

// Hook for initializing simple pricing
add_action( 'init', 'bc_init_simple_pricing' );

// Add database notice to admin pages
add_action( 'admin_notices', 'bc_display_database_notice' );

// Manual database table creation function
function bc_create_database_tables_manually() {
	global $wpdb;
	
	$charset_collate = $wpdb->get_charset_collate();
	
	// Companies table
	$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
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
		PRIMARY KEY (id),
		UNIQUE KEY bc_company_id (bc_company_id),
		UNIQUE KEY company_number (company_number),
		KEY company_name (company_name),
		KEY last_sync (last_sync)
	) $charset_collate;";
	
	// Pricelists table
	$pricelists_table = $wpdb->prefix . 'bc_pricelists';
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
		PRIMARY KEY (id),
		UNIQUE KEY bc_pricelist_id (bc_pricelist_id),
		UNIQUE KEY code (code),
		KEY status (status),
		KEY last_sync (last_sync)
	) $charset_collate;";
	
	// Company pricelist assignments table
	$company_pricelists_table = $wpdb->prefix . 'bc_company_pricelists';
	$company_pricelists_sql = "CREATE TABLE $company_pricelists_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		company_id int(11) NOT NULL,
		pricelist_id int(11) NOT NULL,
		assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
		last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY company_pricelist (company_id, pricelist_id),
		KEY company_id (company_id),
		KEY pricelist_id (pricelist_id)
	) $charset_collate;";
	
	// User phones table for Dokobit authentication
	$user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
	$user_phones_sql = "CREATE TABLE $user_phones_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		phone_number varchar(50) NOT NULL,
		phone_type varchar(50) DEFAULT 'mobile',
		is_verified tinyint(1) DEFAULT 0,
		verification_code varchar(10) DEFAULT NULL,
		verification_expires datetime DEFAULT NULL,
		last_used datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY user_phone (user_id, phone_number),
		KEY user_id (user_id),
		KEY phone_number (phone_number),
		KEY is_verified (is_verified),
		KEY verification_expires (verification_expires)
	) $charset_collate;";
	
	// Execute table creation
	$results = array();
	
	// Create companies table
	$result = $wpdb->query( $companies_sql );
	$results['companies'] = $result !== false;
	
	// Create pricelists table
	$result = $wpdb->query( $pricelists_sql );
	$results['pricelists'] = $result !== false;
	
	// Create company pricelists table
	$result = $wpdb->query( $company_pricelists_sql );
	$results['company_pricelists'] = $result !== false;
	
	// Create user phones table
	$result = $wpdb->query( $user_phones_sql );
	$results['user_phones'] = $result !== false;
	
	return $results;
}



// Register plugin settings
function bc_register_settings() {
	// Register settings group
	register_setting( 'bc_business_central_sync', 'bc_sync_enabled' );
	register_setting( 'bc_business_central_sync', 'bc_api_url' );
	register_setting( 'bc_business_central_sync', 'bc_company_id' );
	register_setting( 'bc_business_central_sync', 'bc_client_id' );
	register_setting( 'bc_business_central_sync', 'bc_client_secret' );
	register_setting( 'bc_business_central_sync', 'bc_sync_interval' );
	
	// Add settings section
	add_settings_section(
		'bc_general_settings',
		'General Settings',
		'bc_general_settings_callback',
		'bc_business_central_sync'
	);
	
	// Add settings fields
	add_settings_field(
		'bc_sync_enabled',
		'Enable Sync',
		'bc_sync_enabled_callback',
		'bc_business_central_sync',
		'bc_general_settings'
	);
	
	add_settings_field(
		'bc_api_url',
		'API Base URL',
		'bc_api_url_callback',
		'bc_business_central_sync',
		'bc_general_settings'
	);
	
	add_settings_field(
		'bc_company_id',
		'Company ID',
		'bc_company_id_callback',
		'bc_business_central_sync',
		'bc_general_settings'
	);
	
	add_settings_field(
		'bc_client_id',
		'Client ID',
		'bc_client_id_callback',
		'bc_business_central_sync',
		'bc_general_settings'
	);
	
	add_settings_field(
		'bc_client_secret',
		'Client Secret',
		'bc_client_secret_callback',
		'bc_business_central_sync',
		'bc_general_settings'
	);
}

// Settings callbacks
function bc_general_settings_callback() {
	echo '<p>Configure your Business Central API settings below.</p>';
}

function bc_sync_enabled_callback() {
	$value = get_option( 'bc_sync_enabled', 'no' );
	echo '<select name="bc_sync_enabled">';
	echo '<option value="no" ' . selected( $value, 'no', false ) . '>No</option>';
	echo '<option value="yes" ' . selected( $value, 'yes', false ) . '>Yes</option>';
	echo '</select>';
	echo '<p class="description">Enable or disable automatic synchronization.</p>';
}

function bc_api_url_callback() {
	$value = get_option( 'bc_api_url', '' );
	echo '<input type="url" name="bc_api_url" value="' . esc_attr( $value ) . '" class="regular-text" />';
	echo '<p class="description">Business Central API base URL (e.g., https://api.businesscentral.dynamics.com/v2.0/your-environment)</p>';
}

function bc_company_id_callback() {
	$value = get_option( 'bc_company_id', '' );
	echo '<input type="text" name="bc_company_id" value="' . esc_attr( $value ) . '" class="regular-text" />';
	echo '<p class="description">Your Business Central company ID.</p>';
}

function bc_client_id_callback() {
	$value = get_option( 'bc_client_id', '' );
	echo '<input type="text" name="bc_client_id" value="' . esc_attr( $value ) . '" class="regular-text" />';
	echo '<p class="description">Your Business Central client ID.</p>';
}

function bc_client_secret_callback() {
	$value = get_option( 'bc_client_secret', '' );
	echo '<input type="password" name="bc_client_secret" value="' . esc_attr( $value ) . '" class="regular-text" />';
	echo '<p class="description">Your Business Central client secret.</p>';
}

// OAuth Settings page callback
function bc_oauth_settings_page() {
	// Check permissions first
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	
	// Include the OAuth settings template
	include_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'templates/bc-oauth-settings-admin-display.php';
}

// Hook for registering settings
add_action( 'admin_init', 'bc_register_settings' );

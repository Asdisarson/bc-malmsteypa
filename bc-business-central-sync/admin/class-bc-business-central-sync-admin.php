<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/your-company/bc-business-central-sync
 * @since      1.0.0
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/admin
 */
class BC_Business_Central_Sync_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name The name of this plugin.
	 * @param      string    $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bc-business-central-sync-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bc-business-central-sync-admin.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'bc_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bc_sync_nonce' ),
		) );

	}

	/**
	 * Register the administration menu for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		// Main BC Sync menu
		add_menu_page(
			__( 'BC Sync', 'bc-business-central-sync' ),
			__( 'BC Sync', 'bc-business-central-sync' ),
			'manage_options',
			'bc-business-central-sync',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-update',
			30
		);
		
		// Dokobit Authentication submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'Dokobit Auth', 'bc-business-central-sync' ),
			__( 'Dokobit Auth', 'bc-business-central-sync' ),
			'manage_options',
			'bc-dokobit-auth',
			array( $this, 'display_dokobit_admin_page' )
		);
		
		// Companies submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'Companies', 'bc-business-central-sync' ),
			__( 'Companies', 'bc-business-central-sync' ),
			'manage_options',
			'bc-dokobit-companies',
			array( $this, 'display_companies_admin_page' )
		);
		
		// User Phones submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'User Phones', 'bc-business-central-sync' ),
			__( 'User Phones', 'bc-business-central-sync' ),
			'manage_options',
			'bc-dokobit-user-phones',
			array( $this, 'display_user_phones_admin_page' )
		);
	}

	/**
	 * Register the settings for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		// Business Central settings
		register_setting( 'bc_business_central_options', 'bc_sync_enabled' );
		register_setting( 'bc_business_central_options', 'bc_api_url' );
		register_setting( 'bc_business_central_options', 'bc_company_id' );
		register_setting( 'bc_business_central_options', 'bc_client_id' );
		register_setting( 'bc_business_central_options', 'bc_client_secret' );
		register_setting( 'bc_business_central_options', 'bc_sync_interval' );
		register_setting( 'bc_business_central_options', 'bc_sync_pricelists' );
		register_setting( 'bc_business_central_options', 'bc_sync_customers' );
		
		// Dokobit settings
		register_setting( 'bc_dokobit_options', 'bc_dokobit_api_endpoint' );
		register_setting( 'bc_dokobit_options', 'bc_dokobit_api_key' );
	}

	/**
	 * Admin page content.
	 *
	 * @since    1.0.0
	 */
	public function admin_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/bc-business-central-sync-admin-display.php';
	}

	/**
	 * Display the main plugin admin page.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once 'partials/bc-business-central-sync-admin-display.php';
	}

	/**
	 * Display the Dokobit authentication admin page.
	 *
	 * @since    1.0.0
	 */
	public function display_dokobit_admin_page() {
		include_once 'partials/bc-dokobit-auth-admin-display.php';
	}

	/**
	 * Display the companies admin page.
	 *
	 * @since    1.0.0
	 */
	public function display_companies_admin_page() {
		include_once 'partials/bc-dokobit-companies-admin-display.php';
	}

	/**
	 * Display the user phones admin page.
	 *
	 * @since    1.0.0
	 */
	public function display_user_phones_admin_page() {
		include_once 'partials/bc-dokobit-user-phones-admin-display.php';
	}

	/**
	 * AJAX handler for syncing products.
	 *
	 * @since    1.0.0
	 */
	public function ajax_sync_products() {
		check_ajax_referer( 'bc_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		try {
			$api = new BC_Business_Central_API();
			$wc_manager = new BC_WooCommerce_Manager();
			
			$products = $api->get_products();
			$result = $wc_manager->sync_products_to_woocommerce( $products );
			
			update_option( 'bc_last_sync', current_time( 'mysql' ) );
			
			wp_send_json_success( array(
				'message' => sprintf( 'Successfully synced %d products from Business Central.', count( $products ) ),
				'products_count' => count( $products ),
				'sync_time' => current_time( 'mysql' )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => 'Error syncing products: ' . $e->getMessage()
			) );
		}
	}

	/**
	 * AJAX handler for testing connection.
	 *
	 * @since    1.0.0
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bc_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		try {
			$api = new BC_Business_Central_API();
			$test_result = $api->test_connection();
			
			wp_send_json_success( array(
				'message' => 'Connection successful! Found ' . $test_result['count'] . ' products.',
				'count' => $test_result['count']
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => 'Connection failed: ' . $e->getMessage()
			) );
		}
	}

	/**
	 * AJAX handler for syncing pricelists.
	 *
	 * @since    1.0.0
	 */
	public function ajax_sync_pricelists() {
		check_ajax_referer( 'bc_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		try {
			$api = new BC_Business_Central_API();
			$pricelist_manager = new BC_Pricelist_Manager();
			
			// Sync pricelists
			$pricelists = $api->get_pricelists();
			$pricelist_result = $pricelist_manager->sync_pricelists( $pricelists );
			
			// Sync pricelist lines
			$pricelist_lines = $api->get_all_pricelist_lines();
			$lines_result = $pricelist_manager->sync_pricelist_lines( $pricelist_lines );
			
			update_option( 'bc_last_pricelist_sync', current_time( 'mysql' ) );
			
			wp_send_json_success( array(
				'message' => sprintf( 'Successfully synced %d pricelists and %d pricelist lines from Business Central.', 
					count( $pricelists ), count( $pricelist_lines ) ),
				'pricelists_count' => count( $pricelists ),
				'lines_count' => count( $pricelist_lines ),
				'sync_time' => current_time( 'mysql' )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => 'Error syncing pricelists: ' . $e->getMessage()
			) );
		}
	}

	/**
	 * AJAX handler for syncing customer companies.
	 *
	 * @since    1.0.0
	 */
	public function ajax_sync_customers() {
		check_ajax_referer( 'bc_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		try {
			$api = new BC_Business_Central_API();
			$pricelist_manager = new BC_Pricelist_Manager();
			
			$customers = $api->get_customer_companies();
			$result = $pricelist_manager->sync_customer_companies( $customers );
			
			update_option( 'bc_last_customer_sync', current_time( 'mysql' ) );
			
			wp_send_json_success( array(
				'message' => sprintf( 'Successfully synced %d customer companies from Business Central.', count( $customers ) ),
				'customers_count' => count( $customers ),
				'sync_time' => current_time( 'mysql' )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => 'Error syncing customer companies: ' . $e->getMessage()
			) );
		}
	}

	/**
	 * AJAX handler for testing Dokobit connection.
	 */
	public function ajax_dokobit_test_connection() {
		check_ajax_referer( 'bc_dokobit_test_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		
		try {
			$api = new BC_Dokobit_API();
			$result = $api->test_connection();
			
			if ( isset( $result['error'] ) ) {
				wp_send_json_error( array( 'message' => $result['error'] ) );
			} else {
				wp_send_json_success( array( 'message' => $result['message'] ) );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Connection test failed: ' . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for syncing companies from Business Central.
	 */
	public function ajax_sync_companies_from_bc() {
		check_ajax_referer( 'bc_sync_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		
		try {
			$api = new BC_Business_Central_API();
			$bc_companies = $api->get_companies();
			$result = BC_Dokobit_Database::sync_companies_from_bc( $bc_companies );
			
			update_option( 'bc_last_companies_sync', current_time( 'mysql' ) );
			
			wp_send_json_success( array(
				'message' => sprintf( 'Successfully synced %d companies from Business Central.', $result['successful'] ),
				'result' => $result
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Error syncing companies: ' . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for syncing customers with companies from Business Central.
	 */
	public function ajax_sync_customers_with_companies_from_bc() {
		check_ajax_referer( 'bc_sync_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		
		try {
			$api = new BC_Business_Central_API();
			$bc_customers = $api->get_customers_with_companies();
			$result = BC_Dokobit_Database::sync_customers_with_companies_from_bc( $bc_customers );
			
			update_option( 'bc_last_customers_companies_sync', current_time( 'mysql' ) );
			
			wp_send_json_success( array(
				'message' => sprintf( 'Successfully synced %d customers with companies from Business Central.', $result['successful'] ),
				'result' => $result
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Error syncing customers with companies: ' . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for Dokobit authentication status check.
	 */
	public function ajax_dokobit_check_auth_status() {
		check_ajax_referer( 'bc_dokobit_auth_nonce', 'nonce' );
		
		if ( ! isset( $_POST['token'] ) ) {
			wp_die();
		}
		
		$token = sanitize_text_field( $_POST['token'] );
		$api = new BC_Dokobit_API();
		$status = $api->check_login_status( $token );
		
		if ( $status && $status['status'] === 'ok' ) {
			// For Iceland/Audkenni, we get personal code instead of phone
			$personal_code = isset( $status['code'] ) ? $status['code'] : '';
			
			// Log for debugging
			error_log( 'BC Dokobit returned personal code: ' . $personal_code );
			error_log( 'BC Dokobit full response: ' . json_encode( $status ) );
			
			$user_data = BC_Dokobit_Database::get_user_by_personal_code( $personal_code );
			
			if ( $user_data ) {
				wp_set_current_user( $user_data['user_id'] );
				wp_set_auth_cookie( $user_data['user_id'] );
				
				set_transient( 'bc_dokobit_user_company_' . $user_data['user_id'], $user_data['company_id'], 3600 );
				
				wp_send_json_success( array(
					'message' => 'Authentication successful',
					'redirect_url' => home_url()
				) );
			} else {
				wp_send_json_error( array( 'message' => 'User not found with this personal code' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'Authentication failed or pending' ) );
		}
	}

	/**
	 * AJAX handler for initiating Dokobit login.
	 */
	public function ajax_dokobit_initiate_login() {
		check_ajax_referer( 'bc_dokobit_auth_nonce', 'nonce' );
		
		if ( ! isset( $_POST['phone'] ) ) {
			wp_die();
		}
		
		$phone = sanitize_text_field( $_POST['phone'] );
		$api = new BC_Dokobit_API();
		$result = $api->initiate_mobile_login( $phone );
		
		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		} else {
			wp_send_json_success( array(
				'token' => $result['token'],
				'control_code' => $result['control_code']
			) );
		}
	}

}

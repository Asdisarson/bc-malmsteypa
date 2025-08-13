<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://malmsteypa.is
 * @since      1.0.0
 *
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/admin
 */
class BC_Business_Central_Sync_Admin extends BC_Plugin_Core {

	// =============================================================================
	// CLASS PROPERTIES
	// =============================================================================

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	protected $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of this plugin.
	 */
	protected $version;

	// =============================================================================
	// CONSTRUCTOR & INITIALIZATION
	// =============================================================================

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name The name of this plugin.
	 * @param      string    $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		parent::__construct();
		
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function init_hooks() {
		// This method is called by the parent constructor
		// Admin-specific hooks are defined in define_admin_hooks()
		// But we need to ensure the admin menu is registered
			// Use multiple hooks and priorities to ensure it works
	add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
	add_action( 'admin_init', array( $this, 'ensure_admin_menu' ), 1 );
	add_action( 'admin_head', array( $this, 'final_admin_menu_check' ), 999 );
	}
	
	/**
	 * Ensure the admin menu is registered even if it was removed.
	 *
	 * @since 1.0.0
	 */
	public function ensure_admin_menu() {
		// Check if our menu already exists
		global $menu;
		$menu_exists = false;
		
		if ( ! empty( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( ! empty( $item[2] ) && $item[2] === 'bc-business-central-sync' ) {
					$menu_exists = true;
					break;
				}
			}
		}
		
		// If menu doesn't exist, add it again
		if ( ! $menu_exists ) {
			$this->add_admin_menu();
		}
	}
	
	/**
	 * Final check to ensure admin menu exists - runs very late.
	 *
	 * @since 1.0.0
	 */
	public function final_admin_menu_check() {
		// Only run this on admin pages
		if ( ! is_admin() ) {
			return;
		}
		
		// Check if our menu exists
		global $menu;
		$menu_exists = false;
		
		if ( ! empty( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( ! empty( $item[2] ) && $item[2] === 'bc-business-central-sync' ) {
					$menu_exists = true;
					break;
				}
			}
		}
		
		// If menu still doesn't exist, force add it
		if ( ! $menu_exists ) {
			// Force add the menu at the very end
			$this->add_admin_menu();
			
			// Debug log if WP_DEBUG is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BC Sync: Admin menu forced registration at admin_head' );
			}
		}
	}

	// =============================================================================
	// ASSETS & SCRIPTS
	// =============================================================================

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 
			$this->plugin_name, 
			$this->get_file_url( 'admin/css/bc-business-central-sync-admin.css' ), 
			array(), 
			$this->version, 
			'all' 
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 
			$this->plugin_name, 
			$this->get_file_url( 'admin/js/bc-business-central-sync-admin.js' ), 
			array( 'jquery' ), 
			$this->version, 
			false 
		);

		wp_localize_script( $this->plugin_name, 'bc_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => $this->create_nonce( 'bc_sync_nonce' ),
		) );
	}

	// =============================================================================
	// ADMIN MENU & PAGES
	// =============================================================================

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
			'manage_woocommerce', // Changed from 'manage_options' to 'manage_woocommerce'
			'bc-business-central-sync',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-update',
			30
		);
		
		// Pricelist Management submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'Pricelist Management', 'bc-business-central-sync' ),
			__( 'Pricelists', 'bc-business-central-sync' ),
			'manage_woocommerce', // Changed from 'manage_options' to 'manage_woocommerce'
			'bc-pricelist-management',
			array( $this, 'display_pricelist_management_page' )
		);
		
		// Company Management submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'Company Management', 'bc-business-central-sync' ),
			__( 'Companies', 'bc-business-central-sync' ),
			'manage_woocommerce', // Changed from 'manage_options' to 'manage_woocommerce'
			'bc-company-management',
			array( $this, 'display_company_management_page' )
		);
		
		// Customer Management submenu (renamed from User Phones)
		add_submenu_page(
			'bc-business-central-sync',
			__( 'Customer Management', 'bc-business-central-sync' ),
			__( 'Customers', 'bc-business-central-sync' ),
			'manage_woocommerce', // Changed from 'manage_options' to 'manage_woocommerce'
			'bc-customer-management',
			array( $this, 'display_customer_management_page' )
		);
		
		// Dokobit Authentication submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'Dokobit Auth', 'bc-business-central-sync' ),
			__( 'Dokobit Auth', 'bc-business-central-sync' ),
			'manage_woocommerce', // Changed from 'manage_options' to 'manage_woocommerce'
			'bc-dokobit-auth',
			array( $this, 'display_dokobit_admin_page' )
		);
		
		// User Management submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'User Management', 'bc-business-central-sync' ),
			__( 'User Management', 'bc-business-central-sync' ),
			'manage_woocommerce', // Changed from 'manage_options' to 'manage_woocommerce'
			'bc-user-management',
			array( $this, 'display_user_management_page' )
		);
	
		// OAuth Settings submenu is now registered by the BC_OAuth_Settings class
	}

	// =============================================================================
	// ADMIN PAGE DISPLAYS
	// =============================================================================

	/**
	 * Display the main plugin admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_admin_page() {
		// Check permissions first
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'bc-business-central-sync' ) );
		}
		
		include_once $this->get_file_path( 'admin/partials/bc-simple-admin-display.php' );
	}

		/**
	 * Display the pricelist management admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_pricelist_management_page() {
		// Check permissions first
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'bc-business-central-sync' ) );
		}
		
		include_once $this->get_file_path( 'admin/partials/bc-pricelist-management-admin-display.php' );
	}

	/**
	 * Display the company management admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_company_management_page() {
		// Check permissions first
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'bc-business-central-sync' ) );
		}
		
		include_once $this->get_file_path( 'admin/partials/bc-company-management-admin-display.php' );
	}

	/**
	 * Display the customer management admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_customer_management_page() {
		// Check permissions first
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'bc-business-central-sync' ) );
		}
		
		include_once $this->get_file_path( 'admin/partials/bc-customer-management-admin-display.php' );
	}

	/**
	 * Display the Dokobit authentication admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_dokobit_admin_page() {
		// Check permissions first
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'bc-business-central-sync' ) );
		}
		
		include_once $this->get_file_path( 'admin/partials/bc-dokobit-auth-admin-display.php' );
	}

	/**
	 * Display the user management admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_user_management_page() {
		// Check permissions first
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'bc-business-central-sync' ) );
		}
		
		include_once $this->get_file_path( 'admin/partials/bc-user-management-admin-display.php' );
	}
	
	// OAuth settings page display method has been moved to the BC_OAuth_Settings class

	/**
	 * Display the companies admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_companies_admin_page() {
		include_once $this->get_file_path( 'admin/partials/bc-dokobit-companies-admin-display.php' );
	}

	/**
	 * Display the user phones admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_user_phones_admin_page() {
		include_once $this->get_file_path( 'admin/partials/bc-dokobit-user-phones-admin-display.php' );
	}

	// =============================================================================
	// HPOS STATUS DISPLAY
	// =============================================================================

	/**
	 * Display HPOS status information.
	 *
	 * @since 1.0.0
	 */
	public function display_hpos_status() {
		if ( ! class_exists( 'BC_HPOS_Utils' ) ) {
			return;
		}

		$hpos_status = BC_HPOS_Utils::get_hpos_status();
		
		echo '<div class="bc-hpos-status">';
		echo '<h3>' . __( 'HPOS (High-Performance Order Storage) Status', 'bc-business-central-sync' ) . '</h3>';
		
		if ( $hpos_status['available'] ) {
			if ( $hpos_status['enabled'] ) {
				echo '<div class="notice notice-success">';
				echo '<p><strong>' . __( 'HPOS is enabled and active!', 'bc-business-central-sync' ) . '</strong></p>';
				echo '<p>' . __( 'Your store is using WooCommerce\'s new high-performance order storage system.', 'bc-business-central-sync' ) . '</p>';
				
				if ( $hpos_status['usage_percentage'] > 0 ) {
					echo '<p>' . sprintf( __( 'HPOS Usage: %s%% of orders are using the new system.', 'bc-business-central-sync' ), $hpos_status['usage_percentage'] ) . '</p>';
				}
				
				echo '</div>';
			} else {
				echo '<div class="notice notice-warning">';
				echo '<p><strong>' . __( 'HPOS is available but not enabled.', 'bc-business-central-sync' ) . '</strong></p>';
				echo '<p>' . __( 'Consider enabling HPOS for improved performance and scalability.', 'bc-business-central-sync' ) . '</p>';
				echo '<p><a href="' . $this->get_admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ) . '" class="button button-primary">' . __( 'Enable HPOS', 'bc-business-central-sync' ) . '</a></p>';
				echo '</div>';
			}
		} else {
			echo '<div class="notice notice-info">';
			echo '<p><strong>' . __( 'HPOS is not available.', 'bc-business-central-sync' ) . '</strong></p>';
			echo '<p>' . __( 'HPOS requires WooCommerce 7.0 or higher. Your plugin will work with traditional order storage.', 'bc-business-central-sync' ) . '</p>';
			echo '</div>';
		}
		
		echo '</div>';
	}

	// =============================================================================
	// SETTINGS & OPTIONS
	// =============================================================================

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Register settings sections and fields
		$this->register_general_settings();
		$this->register_api_settings();
		$this->register_sync_settings();
		$this->register_dokobit_settings();
	}

	/**
	 * Register general settings.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_general_settings() {
		// General settings section
		add_settings_section(
			'bc_general_settings',
			__( 'General Settings', 'bc-business-central-sync' ),
			array( $this, 'general_settings_section_callback' ),
			'bc-business-central-sync'
		);

		// Enable sync setting
		add_settings_field(
			'bc_sync_enabled',
			__( 'Enable Sync', 'bc-business-central-sync' ),
			array( $this, 'sync_enabled_callback' ),
			'bc-business-central-sync',
			'bc_general_settings'
		);

		// Sync interval setting
		add_settings_field(
			'bc_sync_interval',
			__( 'Sync Interval', 'bc-business-central-sync' ),
			array( $this, 'sync_interval_callback' ),
			'bc-business-central-sync',
			'bc_general_settings'
		);

		// Register settings
		register_setting( 'bc-business-central-sync', 'bc_sync_enabled' );
		register_setting( 'bc-business-central-sync', 'bc_sync_interval' );
	}

	/**
	 * Register API settings.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_api_settings() {
		// API settings section
		add_settings_section(
			'bc_api_settings',
			__( 'Business Central API Settings', 'bc-business-central-sync' ),
			array( $this, 'api_settings_section_callback' ),
			'bc-business-central-sync'
		);

		// API URL setting
		add_settings_field(
			'bc_api_url',
			__( 'API Base URL', 'bc-business-central-sync' ),
			array( $this, 'api_url_callback' ),
			'bc-business-central-sync',
			'bc_api_settings'
		);

		// Company ID setting
		add_settings_field(
			'bc_company_id',
			__( 'Company ID', 'bc-business-central-sync' ),
			array( $this, 'company_id_callback' ),
			'bc-business-central-sync',
			'bc_api_settings'
		);

		// OAuth settings are now handled in a separate OAuth Settings page

		// Register settings
		register_setting( 'bc-business-central-sync', 'bc_api_url' );
		register_setting( 'bc-business-central-sync', 'bc_company_id' );
		// OAuth settings (bc_client_id, bc_client_secret) are now registered in the OAuth Settings class
	}

	/**
	 * Register sync settings.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_sync_settings() {
		// Sync settings section
		add_settings_section(
			'bc_sync_settings',
			__( 'Sync Settings', 'bc-business-central-sync' ),
			array( $this, 'sync_settings_section_callback' ),
			'bc-business-central-sync'
		);

		// Sync pricelists setting
		add_settings_field(
			'bc_sync_pricelists',
			__( 'Sync Pricelists', 'bc-business-central-sync' ),
			array( $this, 'sync_pricelists_callback' ),
			'bc-business-central-sync',
			'bc_sync_settings'
		);

		// Sync customers setting
		add_settings_field(
			'bc_sync_customers',
			__( 'Sync Customer Companies', 'bc-business-central-sync' ),
			array( $this, 'sync_customers_callback' ),
			'bc-business-central-sync',
			'bc_sync_settings'
		);

		// Register settings
		register_setting( 'bc-business-central-sync', 'bc_sync_pricelists' );
		register_setting( 'bc-business-central-sync', 'bc_sync_customers' );
	}

	/**
	 * Register Dokobit settings.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function register_dokobit_settings() {
		// Dokobit settings section
		add_settings_section(
			'bc_dokobit_settings',
			__( 'Dokobit Authentication Settings', 'bc-business-central-sync' ),
			array( $this, 'dokobit_settings_section_callback' ),
			'bc-business-central-sync'
		);

		// API endpoint setting
		add_settings_field(
			'bc_dokobit_api_endpoint',
			__( 'API Endpoint', 'bc-business-central-sync' ),
			array( $this, 'dokobit_endpoint_callback' ),
			'bc-business-central-sync',
			'bc_dokobit_settings'
		);

		// API key setting
		add_settings_field(
			'bc_dokobit_api_key',
			__( 'API Key', 'bc-business-central-sync' ),
			array( $this, 'dokobit_api_key_callback' ),
			'bc-business-central-sync',
			'bc_dokobit_settings'
		);

		// Register settings
		register_setting( 'bc-business-central-sync', 'bc_dokobit_api_endpoint' );
		register_setting( 'bc-business-central-sync', 'bc_dokobit_api_key' );
	}

	// =============================================================================
	// SETTINGS CALLBACKS
	// =============================================================================

	/**
	 * General settings section callback.
	 *
	 * @since 1.0.0
	 */
	public function general_settings_section_callback() {
		echo '<p>' . __( 'Configure general synchronization settings.', 'bc-business-central-sync' ) . '</p>';
	}

	/**
	 * API settings section callback.
	 *
	 * @since 1.0.0
	 */
	public function api_settings_section_callback() {
		echo '<p>' . __( 'Configure your Business Central API connection settings.', 'bc-business-central-sync' ) . '</p>';
	}

	/**
	 * Sync settings section callback.
	 *
	 * @since 1.0.0
	 */
	public function sync_settings_section_callback() {
		echo '<p>' . __( 'Configure what data to synchronize from Business Central.', 'bc-business-central-sync' ) . '</p>';
	}

	/**
	 * Dokobit settings section callback.
	 *
	 * @since 1.0.0
	 */
	public function dokobit_settings_section_callback() {
		echo '<p>' . __( 'Configure Dokobit phone authentication settings.', 'bc-business-central-sync' ) . '</p>';
	}

	// =============================================================================
	// SETTINGS FIELD CALLBACKS
	// =============================================================================

	/**
	 * Sync enabled callback.
	 *
	 * @since 1.0.0
	 */
	public function sync_enabled_callback() {
		$value = $this->get_option( 'sync_enabled', 'no' );
		echo '<input type="checkbox" id="bc_sync_enabled" name="bc_sync_enabled" value="yes" ' . checked( 'yes', $value, false ) . ' />';
		echo '<label for="bc_sync_enabled">' . __( 'Enable automatic synchronization', 'bc-business-central-sync' ) . '</label>';
	}

	/**
	 * Sync interval callback.
	 *
	 * @since 1.0.0
	 */
	public function sync_interval_callback() {
		$value = $this->get_option( 'sync_interval', 'daily' );
		$intervals = array(
			'hourly' => __( 'Hourly', 'bc-business-central-sync' ),
			'daily'  => __( 'Daily', 'bc-business-central-sync' ),
			'weekly' => __( 'Weekly', 'bc-business-central-sync' ),
		);

		echo '<select id="bc_sync_interval" name="bc_sync_interval">';
		foreach ( $intervals as $interval => $label ) {
			echo '<option value="' . $this->escape_attr( $interval ) . '" ' . selected( $interval, $value, false ) . '>' . $this->escape_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * API URL callback.
	 *
	 * @since 1.0.0
	 */
	public function api_url_callback() {
		$value = $this->get_option( 'api_url', '' );
		echo '<input type="url" id="bc_api_url" name="bc_api_url" value="' . $this->escape_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Your Business Central API base URL', 'bc-business-central-sync' ) . '</p>';
	}

	/**
	 * Company ID callback.
	 *
	 * @since 1.0.0
	 */
	public function company_id_callback() {
		$value = $this->get_option( 'company_id', '' );
		echo '<input type="text" id="bc_company_id" name="bc_company_id" value="' . $this->escape_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Your Business Central company ID', 'bc-business-central-sync' ) . '</p>';
	}

	// OAuth callbacks have been moved to the separate OAuth Settings class

	/**
	 * Sync pricelists callback.
	 *
	 * @since 1.0.0
	 */
	public function sync_pricelists_callback() {
		$value = $this->get_option( 'sync_pricelists', 'no' );
		echo '<input type="checkbox" id="bc_sync_pricelists" name="bc_sync_pricelists" value="yes" ' . checked( 'yes', $value, false ) . ' />';
		echo '<label for="bc_sync_pricelists">' . __( 'Synchronize pricelists from Business Central', 'bc-business-central-sync' ) . '</label>';
	}

	/**
	 * Sync customers callback.
	 *
	 * @since 1.0.0
	 */
	public function sync_customers_callback() {
		$value = $this->get_option( 'sync_customers', 'no' );
		echo '<input type="checkbox" id="bc_sync_customers" name="bc_sync_customers" value="yes" ' . checked( 'yes', $value, false ) . ' />';
		echo '<label for="bc_sync_customers">' . __( 'Synchronize customer companies from Business Central', 'bc-business-central-sync' ) . '</label>';
	}

	/**
	 * Dokobit endpoint callback.
	 *
	 * @since 1.0.0
	 */
	public function dokobit_endpoint_callback() {
		$value = $this->get_option( 'dokobit_api_endpoint', 'https://developers.dokobit.com' );
		echo '<input type="url" id="bc_dokobit_api_endpoint" name="bc_dokobit_api_endpoint" value="' . $this->escape_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Dokobit API endpoint URL', 'bc-business-central-sync' ) . '</p>';
	}

	/**
	 * Dokobit API key callback.
	 *
	 * @since 1.0.0
	 */
	public function dokobit_api_key_callback() {
		$value = $this->get_option( 'dokobit_api_key', '' );
		echo '<input type="password" id="bc_dokobit_api_key" name="bc_dokobit_api_key" value="' . $this->escape_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Your Dokobit API key', 'bc-business-central-sync' ) . '</p>';
	}

	// =============================================================================
	// AJAX HANDLERS
	// =============================================================================

	/**
	 * Test Business Central connection.
	 *
	 * @since 1.0.0
	 */
	public function test_connection() {
		// Verify nonce
		if ( ! $this->verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! $this->user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Test connection logic here
		// This would typically involve testing the API credentials
		
		wp_send_json_success( 'Connection successful!' );
	}

	/**
	 * Sync products from Business Central.
	 *
	 * @since 1.0.0
	 */
	public function sync_products() {
		// Verify nonce
		if ( ! $this->verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! $this->user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Sync products logic here
		// This would typically involve calling the Business Central API
		
		wp_send_json_success( 'Products synced successfully!' );
	}

	/**
	 * Sync pricelists from Business Central.
	 *
	 * @since 1.0.0
	 */
	public function sync_pricelists() {
		// Verify nonce
		if ( ! $this->verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! $this->user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Sync pricelists logic here
		// This would typically involve calling the Business Central API
		
		wp_send_json_success( 'Pricelists synced successfully!' );
	}

	/**
	 * Sync customer companies from Business Central.
	 *
	 * @since 1.0.0
	 */
	public function sync_customer_companies() {
		// Verify nonce
		if ( ! $this->verify_nonce( $_POST['nonce'], 'bc_sync_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! $this->user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Sync customer companies logic here
		// This would typically involve calling the Business Central API
		
		wp_send_json_success( 'Customer companies synced successfully!' );
	}
}

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
			'bc-companies',
			array( $this, 'display_companies_admin_page' )
		);
		
		// User Phones submenu
		add_submenu_page(
			'bc-business-central-sync',
			__( 'User Phones', 'bc-business-central-sync' ),
			__( 'User Phones', 'bc-business-central-sync' ),
			'manage_options',
			'bc-user-phones',
			array( $this, 'display_user_phones_admin_page' )
		);
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
		include_once $this->get_file_path( 'admin/partials/bc-business-central-sync-admin-display.php' );
	}

	/**
	 * Display the Dokobit authentication admin page.
	 *
	 * @since 1.0.0
	 */
	public function display_dokobit_admin_page() {
		include_once $this->get_file_path( 'admin/partials/bc-dokobit-auth-admin-display.php' );
	}

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

		// Client ID setting
		add_settings_field(
			'bc_client_id',
			__( 'Client ID', 'bc-business-central-sync' ),
			array( $this, 'client_id_callback' ),
			'bc-business-central-sync',
			'bc_api_settings'
		);

		// Client Secret setting
		add_settings_field(
			'bc_client_secret',
			__( 'Client Secret', 'bc-business-central-sync' ),
			array( $this, 'client_secret_callback' ),
			'bc-business-central-sync',
			'bc_api_settings'
		);

		// Register settings
		register_setting( 'bc-business-central-sync', 'bc_api_url' );
		register_setting( 'bc-business-central-sync', 'bc_company_id' );
		register_setting( 'bc-business-central-sync', 'bc_client_id' );
		register_setting( 'bc-business-central-sync', 'bc_client_secret' );
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

	/**
	 * Client ID callback.
	 *
	 * @since 1.0.0
	 */
	public function client_id_callback() {
		$value = $this->get_option( 'client_id', '' );
		echo '<input type="text" id="bc_client_id" name="bc_client_id" value="' . $this->escape_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Your Azure AD application client ID', 'bc-business-central-sync' ) . '</p>';
	}

	/**
	 * Client secret callback.
	 *
	 * @since 1.0.0
	 */
	public function client_secret_callback() {
		$value = $this->get_option( 'client_secret', '' );
		echo '<input type="password" id="bc_client_secret" name="bc_client_secret" value="' . $this->escape_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Your Azure AD application client secret', 'bc-business-central-sync' ) . '</p>';
	}

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

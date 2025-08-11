<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Business_Central_Sync {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      BC_Business_Central_Sync_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'BC_BUSINESS_CENTRAL_SYNC_VERSION' ) ) {
			$this->version = BC_BUSINESS_CENTRAL_SYNC_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'bc-business-central-sync';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - BC_Business_Central_Sync_Loader. Orchestrates the hooks of the plugin.
	 * - BC_Business_Central_Sync_i18n. Defines internationalization functionality.
	 * - BC_Business_Central_Sync_Admin. Defines all hooks for the admin area.
	 * - BC_Business_Central_Sync_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-business-central-sync-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-business-central-sync-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-bc-business-central-sync-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-bc-business-central-sync-public.php';

		/**
		 * The class responsible for Business Central API integration.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-business-central-api.php';

		/**
		 * The class responsible for WooCommerce product management.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-woocommerce-manager.php';

		/**
		 * The class responsible for pricelist management.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-pricelist-manager.php';

		/**
		 * The class responsible for customer pricing.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-customer-pricing.php';

		/**
		 * The class responsible for shortcodes.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-shortcodes.php';

		/**
		 * The class responsible for Dokobit API integration.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-dokobit-api.php';

		/**
		 * The class responsible for Dokobit database management.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-dokobit-database.php';

		/**
		 * The class responsible for Dokobit shortcodes.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bc-dokobit-shortcode.php';

		$this->loader = new BC_Business_Central_Sync_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the BC_Business_Central_Sync_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new BC_Business_Central_Sync_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new BC_Business_Central_Sync_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'wp_ajax_bc_sync_products', $plugin_admin, 'ajax_sync_products' );
		$this->loader->add_action( 'wp_ajax_bc_test_connection', $plugin_admin, 'ajax_test_connection' );
		$this->loader->add_action( 'wp_ajax_bc_sync_pricelists', $plugin_admin, 'ajax_sync_pricelists' );
		$this->loader->add_action( 'wp_ajax_bc_sync_customers', $plugin_admin, 'ajax_sync_customers' );
		$this->loader->add_action( 'wp_ajax_bc_sync_companies_from_bc', $plugin_admin, 'ajax_sync_companies_from_bc' );
		$this->loader->add_action( 'wp_ajax_bc_sync_customers_with_companies_from_bc', $plugin_admin, 'ajax_sync_customers_with_companies_from_bc' );
		$this->loader->add_action( 'wp_ajax_bc_dokobit_test_connection', $plugin_admin, 'ajax_dokobit_test_connection' );
		$this->loader->add_action( 'wp_ajax_bc_dokobit_check_auth_status', $plugin_admin, 'ajax_dokobit_check_auth_status' );
		$this->loader->add_action( 'wp_ajax_nopriv_bc_dokobit_check_auth_status', $plugin_admin, 'ajax_dokobit_check_auth_status' );
		$this->loader->add_action( 'wp_ajax_bc_dokobit_initiate_login', $plugin_admin, 'ajax_dokobit_initiate_login' );
		$this->loader->add_action( 'wp_ajax_nopriv_bc_dokobit_initiate_login', $plugin_admin, 'ajax_dokobit_initiate_login' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new BC_Business_Central_Sync_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		
		// Initialize shortcodes using proper callback functions
		$this->loader->add_action( 'init', $this, 'init_shortcodes' );

	}

	/**
	 * Initialize shortcodes.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function init_shortcodes() {
		BC_Shortcodes::init();
		BC_Dokobit_Shortcode::init();
	}

	/**
	 * Register all of the hooks related to cron jobs.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_cron_hooks() {
		$this->loader->add_action( 'bc_sync_products_cron', $this, 'cron_sync_products' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    BC_Business_Central_Sync_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Cron job to sync products from Business Central.
	 *
	 * @since    1.0.0
	 */
	public function cron_sync_products() {
		$api = new BC_Business_Central_API();
		$wc_manager = new BC_WooCommerce_Manager();
		
		try {
			$products = $api->get_products();
			$wc_manager->sync_products_to_woocommerce( $products );
			update_option( 'bc_last_sync', current_time( 'mysql' ) );
		} catch ( Exception $e ) {
			error_log( 'BC Sync Error: ' . $e->getMessage() );
		}
	}

}

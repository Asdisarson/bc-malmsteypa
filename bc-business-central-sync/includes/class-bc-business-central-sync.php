<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks. It serves as the main entry point for the plugin
 * and orchestrates all functionality.
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
	 * Plugin instance for singleton pattern.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      BC_Business_Central_Sync    $instance    The single instance of the plugin.
	 */
	private static $instance = null;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->version = defined( 'BC_BUSINESS_CENTRAL_SYNC_VERSION' ) ? BC_BUSINESS_CENTRAL_SYNC_VERSION : '1.0.0';
		$this->plugin_name = 'bc-business-central-sync';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
		$this->init_shortcodes();
		$this->init_hpos_compatibility();
	}

	/**
	 * Get plugin instance (singleton pattern).
	 *
	 * @since 1.0.0
	 * @return BC_Business_Central_Sync Plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
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
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-sync-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'admin/class-bc-business-central-sync-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'public/class-bc-business-central-sync-public.php';

		/**
		 * The class responsible for Business Central API operations.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-business-central-api.php';

		/**
		 * The class responsible for Dokobit API operations.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-dokobit-api.php';

		/**
		 * The class responsible for Dokobit database operations.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-dokobit-database.php';

		/**
		 * The class responsible for Dokobit shortcode functionality.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-dokobit-shortcode.php';

		/**
		 * The class responsible for customer pricing functionality.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-customer-pricing.php';

		/**
		 * The class responsible for pricelist management.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-pricelist-manager.php';

		/**
		 * The class responsible for shortcode functionality.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-shortcodes.php';

		/**
		 * The class responsible for WooCommerce integration.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-woocommerce-manager.php';

		/**
		 * The class responsible for HPOS compatibility.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-hpos-compatibility.php';

		/**
		 * The class responsible for HPOS utility functions.
		 */
		require_once BC_BUSINESS_CENTRAL_SYNC_PATH . 'includes/class-bc-hpos-utils.php';

		$this->loader = new BC_Business_Central_Sync_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the BC_Business_Central_Sync_i18n class in order to set the domain and to register
	 * the hook with WordPress.
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

		// Admin scripts and styles
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Admin menu
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Admin AJAX handlers
		$this->loader->add_action( 'wp_ajax_bc_test_connection', $plugin_admin, 'test_connection' );
		$this->loader->add_action( 'wp_ajax_bc_sync_products', $plugin_admin, 'sync_products' );
		$this->loader->add_action( 'wp_ajax_bc_sync_pricelists', $plugin_admin, 'sync_pricelists' );
		$this->loader->add_action( 'wp_ajax_bc_sync_customer_companies', $plugin_admin, 'sync_customer_companies' );

		// Settings
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
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

		// Public scripts and styles
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Public AJAX handlers
		$this->loader->add_action( 'wp_ajax_bc_dokobit_auth', $plugin_public, 'handle_dokobit_auth' );
		$this->loader->add_action( 'wp_ajax_nopriv_bc_dokobit_auth', $plugin_public, 'handle_dokobit_auth' );

		// WooCommerce hooks
		$this->loader->add_action( 'woocommerce_before_single_product', $plugin_public, 'check_customer_authentication' );
		$this->loader->add_filter( 'woocommerce_get_price_html', $plugin_public, 'filter_product_price', 10, 2 );
		$this->loader->add_action( 'woocommerce_before_add_to_cart_button', $plugin_public, 'show_authentication_notice' );
	}

	/**
	 * Initialize shortcodes.
	 *
	 * @since 1.0.0
	 */
	public function init_shortcodes() {
		$shortcodes = new BC_Shortcodes();
		$shortcodes->init();
	}

	/**
	 * Initialize HPOS compatibility.
	 *
	 * @since 1.0.0
	 */
	private function init_hpos_compatibility() {
		// Initialize HPOS compatibility - the constructor handles all initialization
		new BC_HPOS_Compatibility();
	}

	/**
	 * Register all of the hooks related to cron functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_cron_hooks() {
		// Add cron interval
		$this->loader->add_filter( 'cron_schedules', $this, 'add_cron_interval' );

		// Schedule cron events
		$this->loader->add_action( 'wp', $this, 'schedule_cron_events' );

		// Cron event handlers
		$this->loader->add_action( 'bc_sync_products_cron', $this, 'cron_sync_products' );
		$this->loader->add_action( 'bc_sync_pricelists_cron', $this, 'cron_sync_pricelists' );
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
	 * Add custom cron interval.
	 *
	 * @since 1.0.0
	 * @param array $schedules Cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['bc_hourly'] = array(
			'interval' => 3600,
			'display'  => __( 'Every Hour', 'bc-business-central-sync' ),
		);
		
		$schedules['bc_daily'] = array(
			'interval' => 86400,
			'display'  => __( 'Daily', 'bc-business-central-sync' ),
		);
		
		$schedules['bc_weekly'] = array(
			'interval' => 604800,
			'display'  => __( 'Weekly', 'bc-business-central-sync' ),
		);
		
		return $schedules;
	}

	/**
	 * Schedule cron events.
	 *
	 * @since 1.0.0
	 */
	public function schedule_cron_events() {
		$sync_enabled = get_option( 'bc_sync_enabled', 'no' );
		$sync_interval = get_option( 'bc_sync_interval', 'daily' );
		
		if ( 'yes' === $sync_enabled && ! wp_next_scheduled( 'bc_sync_products_cron' ) ) {
			wp_schedule_event( time(), 'bc_' . $sync_interval, 'bc_sync_products_cron' );
		}
		
		if ( 'yes' === $sync_enabled && ! wp_next_scheduled( 'bc_sync_pricelists_cron' ) ) {
			wp_schedule_event( time(), 'bc_' . $sync_interval, 'bc_sync_pricelists_cron' );
		}
	}

	/**
	 * Cron job to sync products.
	 *
	 * @since 1.0.0
	 */
	public function cron_sync_products() {
		if ( ! class_exists( 'BC_Business_Central_API' ) ) {
			return;
		}
		
		$api = new BC_Business_Central_API();
		$api->sync_products();
	}

	/**
	 * Cron job to sync pricelists.
	 *
	 * @since 1.0.0
	 */
	public function cron_sync_pricelists() {
		if ( ! class_exists( 'BC_Pricelist_Manager' ) ) {
			return;
		}
		
		$pricelist_manager = new BC_Pricelist_Manager();
		$pricelist_manager->sync_pricelists();
	}
}

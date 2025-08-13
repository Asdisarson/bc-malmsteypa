<?php

/**
 * Core Plugin Class
 *
 * Provides common functionality and utilities for the Business Central Sync plugin.
 * This class serves as a foundation for other plugin classes.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes/core
 */
abstract class BC_Plugin_Core {

	// =============================================================================
	// CLASS PROPERTIES
	// =============================================================================

	/**
	 * Plugin name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_version;

	/**
	 * Plugin path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_path;

	/**
	 * Plugin URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_url;

	// =============================================================================
	// CONSTRUCTOR
	// =============================================================================

	/**
	 * Initialize the core plugin class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_properties();
		$this->init_hooks();
	}

	// =============================================================================
	// INITIALIZATION
	// =============================================================================

	/**
	 * Initialize plugin properties.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function init_properties() {
		$this->plugin_name = 'bc-business-central-sync';
		$this->plugin_version = defined( 'BC_BUSINESS_CENTRAL_SYNC_VERSION' ) ? BC_BUSINESS_CENTRAL_SYNC_VERSION : '1.0.0';
		$this->plugin_path = defined( 'BC_BUSINESS_CENTRAL_SYNC_PATH' ) ? BC_BUSINESS_CENTRAL_SYNC_PATH : plugin_dir_path( dirname( dirname( __FILE__ ) ) );
		$this->plugin_url = defined( 'BC_BUSINESS_CENTRAL_SYNC_URL' ) ? BC_BUSINESS_CENTRAL_SYNC_URL : plugin_dir_url( dirname( dirname( __FILE__ ) ) );
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	abstract protected function init_hooks();

	// =============================================================================
	// UTILITY METHODS
	// =============================================================================

	/**
	 * Get plugin name.
	 *
	 * @since 1.0.0
	 * @return string Plugin name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get plugin version.
	 *
	 * @since 1.0.0
	 * @return string Plugin version.
	 */
	public function get_plugin_version() {
		return $this->plugin_version;
	}

	/**
	 * Get plugin path.
	 *
	 * @since 1.0.0
	 * @return string Plugin path.
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get plugin URL.
	 *
	 * @since 1.0.0
	 * @return string Plugin URL.
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @since 1.0.0
	 * @param string $feature Feature name.
	 * @return bool True if enabled, false otherwise.
	 */
	protected function is_feature_enabled( $feature ) {
		$option_name = 'bc_' . $feature . '_enabled';
		return 'yes' === get_option( $option_name, 'no' );
	}

	/**
	 * Get plugin option with default value.
	 *
	 * @since 1.0.0
	 * @param string $option_name Option name.
	 * @param mixed  $default     Default value.
	 * @return mixed Option value.
	 */
	protected function get_option( $option_name, $default = '' ) {
		return get_option( 'bc_' . $option_name, $default );
	}

	/**
	 * Update plugin option.
	 *
	 * @since 1.0.0
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @return bool True on success, false on failure.
	 */
	protected function update_option( $option_name, $value ) {
		return update_option( 'bc_' . $option_name, $value );
	}

	/**
	 * Log message to WordPress debug log.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param string $level   Log level (info, warning, error).
	 * @return void
	 */
	protected function log( $message, $level = 'info' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf( '[BC Plugin] [%s] %s', strtoupper( $level ), $message );
			error_log( $log_message );
		}
	}

	/**
	 * Check if current user has required capability.
	 *
	 * @since 1.0.0
	 * @param string $capability Required capability.
	 * @return bool True if user has capability, false otherwise.
	 */
	protected function user_can( $capability ) {
		return current_user_can( $capability );
	}

	/**
	 * Verify nonce for security.
	 *
	 * @since 1.0.0
	 * @param string $nonce     Nonce value.
	 * @param string $action    Nonce action.
	 * @return bool True if nonce is valid, false otherwise.
	 */
	protected function verify_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Create nonce for security.
	 *
	 * @since 1.0.0
	 * @param string $action Nonce action.
	 * @return string Nonce value.
	 */
	protected function create_nonce( $action ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Sanitize text input.
	 *
	 * @since 1.0.0
	 * @param string $input Input to sanitize.
	 * @return string Sanitized input.
	 */
	protected function sanitize_text( $input ) {
		return sanitize_text_field( $input );
	}

	/**
	 * Sanitize email input.
	 *
	 * @since 1.0.0
	 * @param string $input Input to sanitize.
	 * @return string Sanitized input.
	 */
	protected function sanitize_email( $input ) {
		return sanitize_email( $input );
	}

	/**
	 * Sanitize URL input.
	 *
	 * @since 1.0.0
	 * @param string $input Input to sanitize.
	 * @return string Sanitized input.
	 */
	protected function sanitize_url( $input ) {
		return esc_url_raw( $input );
	}

	/**
	 * Escape HTML output.
	 *
	 * @since 1.0.0
	 * @param string $input Input to escape.
	 * @return string Escaped input.
	 */
	protected function escape_html( $input ) {
		return esc_html( $input );
	}

	/**
	 * Escape HTML attributes.
	 *
	 * @since 1.0.0
	 * @param string $input Input to escape.
	 * @return string Escaped input.
	 */
	protected function escape_attr( $input ) {
		return esc_attr( $input );
	}

	// =============================================================================
	// HELPER METHODS
	// =============================================================================

	/**
	 * Check if WooCommerce is active.
	 *
	 * @since 1.0.0
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	protected function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if HPOS is available.
	 *
	 * @since 1.0.0
	 * @return bool True if HPOS is available, false otherwise.
	 */
	protected function is_hpos_available() {
		return class_exists( 'BC_HPOS_Utils' ) && BC_HPOS_Utils::is_hpos_available();
	}

	/**
	 * Get current user ID.
	 *
	 * @since 1.0.0
	 * @return int User ID.
	 */
	protected function get_current_user_id() {
		return get_current_user_id();
	}

	/**
	 * Check if user is logged in.
	 *
	 * @since 1.0.0
	 * @return bool True if user is logged in, false otherwise.
	 */
	protected function is_user_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Get admin URL.
	 *
	 * @since 1.0.0
	 * @param string $path Admin path.
	 * @return string Admin URL.
	 */
	protected function get_admin_url( $path = '' ) {
		return admin_url( $path );
	}

	/**
	 * Get plugin file path.
	 *
	 * @since 1.0.0
	 * @param string $file File path relative to plugin directory.
	 * @return string Full file path.
	 */
	protected function get_file_path( $file ) {
		return $this->plugin_path . $file;
	}

	/**
	 * Get plugin file URL.
	 *
	 * @since 1.0.0
	 * @param string $file File path relative to plugin directory.
	 * @return string Full file URL.
	 */
	protected function get_file_url( $file ) {
		return $this->plugin_url . $file;
	}

	/**
	 * Check if file exists.
	 *
	 * @since 1.0.0
	 * @param string $file File path relative to plugin directory.
	 * @return bool True if file exists, false otherwise.
	 */
	protected function file_exists( $file ) {
		return file_exists( $this->get_file_path( $file ) );
	}

	/**
	 * Include file if it exists.
	 *
	 * @since 1.0.0
	 * @param string $file File path relative to plugin directory.
	 * @return bool True if file was included, false otherwise.
	 */
	protected function include_file( $file ) {
		$file_path = $this->get_file_path( $file );
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return true;
		}
		return false;
	}
}


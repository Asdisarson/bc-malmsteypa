<?php

/**
 * HPOS (High-Performance Order Storage) Compatibility Class
 *
 * This class ensures the plugin is fully compatible with WooCommerce's
 * new HPOS system while maintaining backward compatibility.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_HPOS_Compatibility {

	/**
	 * Initialize HPOS compatibility.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Check if HPOS is enabled
		if ( $this->is_hpos_enabled() ) {
			// Add HPOS-specific hooks
			add_action( 'woocommerce_order_object_updated_props', array( $this, 'handle_order_updated_props' ), 10, 2 );
			add_action( 'woocommerce_order_object_created_props', array( $this, 'handle_order_created_props' ), 10, 2 );
		}

		// Add compatibility checks
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'modify_orders_query' ), 10, 2 );
	}

	/**
	 * Check if HPOS is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if HPOS is enabled, false otherwise.
	 */
	public function is_hpos_enabled() {
		// Check if HPOS is available and enabled
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Check if an order is using HPOS.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order Order ID or order object.
	 * @return bool True if order uses HPOS, false otherwise.
	 */
	public function is_order_using_hpos( $order ) {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::is_order_using_custom_table( $order );
	}

	/**
	 * Get order meta data in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order Order ID or order object.
	 * @param string       $key   Meta key.
	 * @param bool         $single Whether to return a single value.
	 * @return mixed Meta value.
	 */
	public function get_order_meta( $order, $key, $single = true ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		return $order->get_meta( $key, $single );
	}

	/**
	 * Update order meta data in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order   Order ID or order object.
	 * @param string       $key     Meta key.
	 * @param mixed        $value   Meta value.
	 * @param string       $prev_value Previous value.
	 * @return int|bool Meta ID if the key didn't exist, true on success, false on failure.
	 */
	public function update_order_meta( $order, $key, $value, $prev_value = '' ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		$order->update_meta_data( $key, $value, $prev_value );
		return $order->save();
	}

	/**
	 * Add order meta data in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order Order ID or order object.
	 * @param string       $key   Meta key.
	 * @param mixed        $value Meta value.
	 * @param bool         $unique Whether the same key should not be added.
	 * @return int|bool Meta ID if the key didn't exist, true on success, false on failure.
	 */
	public function add_order_meta( $order, $key, $value, $unique = false ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		$order->add_meta_data( $key, $value, $unique );
		return $order->save();
	}

	/**
	 * Delete order meta data in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order     Order ID or order object.
	 * @param string       $key       Meta key.
	 * @param mixed        $value     Meta value.
	 * @return bool True on success, false on failure.
	 */
	public function delete_order_meta( $order, $key, $value = '' ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		$order->delete_meta_data( $key, $value );
		return $order->save();
	}

	/**
	 * Handle order updated properties for HPOS.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 * @param array    $changes Array of changed properties.
	 */
	public function handle_order_updated_props( $order, $changes ) {
		// Handle any specific logic when order properties are updated
		// This is useful for syncing data to Business Central or other systems
		do_action( 'bc_order_updated_props', $order, $changes );
	}

	/**
	 * Handle order created properties for HPOS.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 * @param array    $changes Array of changed properties.
	 */
	public function handle_order_created_props( $order, $changes ) {
		// Handle any specific logic when order properties are created
		// This is useful for initial data setup
		do_action( 'bc_order_created_props', $order, $changes );
	}

	/**
	 * Modify orders query for HPOS compatibility.
	 *
	 * @since 1.0.0
	 * @param array $query Query arguments.
	 * @param array $query_vars Query vars.
	 * @return array Modified query arguments.
	 */
	public function modify_orders_query( $query, $query_vars ) {
		// Add any custom meta queries that need to work with HPOS
		if ( isset( $query_vars['bc_customer_number'] ) ) {
			$query['meta_query'][] = array(
				'key'     => '_bc_customer_number',
				'value'   => $query_vars['bc_customer_number'],
				'compare' => '=',
			);
		}

		return $query;
	}

	/**
	 * Get orders by meta value in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param string $compare    Comparison operator.
	 * @return array Array of order IDs.
	 */
	public function get_orders_by_meta( $meta_key, $meta_value, $compare = '=' ) {
		$args = array(
			'limit'      => -1,
			'return'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => $compare,
				),
			),
		);

		return wc_get_orders( $args );
	}

	/**
	 * Display admin notices for HPOS compatibility.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// Only show on WooCommerce admin pages
		if ( ! class_exists( 'WooCommerce' ) || ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'woocommerce' ) === false ) {
			return;
		}

		// Check if HPOS is enabled and show appropriate notice
		if ( $this->is_hpos_enabled() ) {
			$notice_class = 'notice notice-info';
			$message = __( 'Business Central Sync is running with HPOS (High-Performance Order Storage) enabled. This provides improved performance and scalability.', 'bc-business-central-sync' );
		} else {
			$notice_class = 'notice notice-warning';
			$message = __( 'Business Central Sync is running with traditional order storage. Consider enabling HPOS for improved performance.', 'bc-business-central-sync' );
		}

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $notice_class ),
			esc_html( $message )
		);
	}

	/**
	 * Get HPOS status information.
	 *
	 * @since 1.0.0
	 * @return array HPOS status information.
	 */
	public function get_hpos_status() {
		$status = array(
			'enabled'           => false,
			'available'         => false,
			'custom_table_name' => '',
			'usage_percentage'  => 0,
		);

		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return $status;
		}

		$status['available'] = true;
		$status['enabled'] = $this->is_hpos_enabled();

		if ( $status['enabled'] ) {
			$status['custom_table_name'] = \Automattic\WooCommerce\Utilities\OrderUtil::get_table_for_orders( 'shop_order' );
			
			// Get usage percentage
			$hpos_count = \Automattic\WooCommerce\Utilities\OrderUtil::get_orders_count( 'custom' );
			$cpt_count = \Automattic\WooCommerce\Utilities\OrderUtil::get_orders_count( 'posts' );
			$total = $hpos_count + $cpt_count;
			
			if ( $total > 0 ) {
				$status['usage_percentage'] = round( ( $hpos_count / $total ) * 100, 2 );
			}
		}

		return $status;
	}

	/**
	 * Check if the current environment supports HPOS.
	 *
	 * @since 1.0.0
	 * @return bool True if HPOS is supported, false otherwise.
	 */
	public function is_hpos_supported() {
		// Check WooCommerce version
		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '7.0', '<' ) ) {
			return false;
		}

		// Check if OrderUtil class exists
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		// Check if custom orders table exists
		if ( ! \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_exists() ) {
			return false;
		}

		return true;
	}
}

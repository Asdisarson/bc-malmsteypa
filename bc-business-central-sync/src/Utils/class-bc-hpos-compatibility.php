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
			
			// Add HPOS-specific order hooks
			add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
			add_action( 'woocommerce_order_refunded', array( $this, 'handle_order_refunded' ), 10, 2 );
			
			// Add HPOS-specific meta handling
			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'modify_orders_query' ), 10, 2 );
			add_action( 'woocommerce_order_meta_updated', array( $this, 'handle_order_meta_updated' ), 10, 3 );
		}

		// Add compatibility checks and notices
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_bc_customer_info' ), 10, 1 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_bc_customer_info' ), 10, 1 );
		
		// Add HPOS migration support
		add_action( 'woocommerce_hpos_migration_completed', array( $this, 'handle_hpos_migration_completed' ) );
		add_action( 'woocommerce_hpos_migration_failed', array( $this, 'handle_hpos_migration_failed' ) );
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
		
		// Log the changes for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BC Order Updated Props: ' . print_r( $changes, true ) );
		}
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
		
		// Log the changes for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BC Order Created Props: ' . print_r( $changes, true ) );
		}
	}

	/**
	 * Handle order status changes for HPOS.
	 *
	 * @since 1.0.0
	 * @param int    $order_id Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param WC_Order $order Order object.
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		// Handle Business Central sync when order status changes
		do_action( 'bc_order_status_changed', $order_id, $old_status, $new_status, $order );
		
		// Log the status change for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BC Order Status Changed: Order {$order_id} from {$old_status} to {$new_status}" );
		}
	}

	/**
	 * Handle order refunded for HPOS.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public function handle_order_refunded( $order_id, $refund_id ) {
		// Handle Business Central sync when order is refunded
		do_action( 'bc_order_refunded', $order_id, $refund_id );
		
		// Log the refund for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BC Order Refunded: Order {$order_id}, Refund {$refund_id}" );
		}
	}

	/**
	 * Handle order meta updates for HPOS.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 * @param string $meta_key Meta key.
	 * @param mixed $meta_value Meta value.
	 */
	public function handle_order_meta_updated( $order_id, $meta_key, $meta_value ) {
		// Handle Business Central sync when order meta is updated
		do_action( 'bc_order_meta_updated', $order_id, $meta_key, $meta_value );
		
		// Log the meta update for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BC Order Meta Updated: Order {$order_id}, Key {$meta_key}, Value " . print_r( $meta_value, true ) );
		}
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

		// Add Business Central product number query
		if ( isset( $query_vars['bc_product_number'] ) ) {
			$query['meta_query'][] = array(
				'key'     => '_bc_product_number',
				'value'   => $query_vars['bc_product_number'],
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
	 * Display Business Central customer information in admin order view.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 */
	public function display_bc_customer_info( $order ) {
		$customer_number = $this->get_order_meta( $order, '_bc_customer_number', true );
		
		if ( $customer_number ) {
			echo '<p><strong>' . __( 'Business Central Customer Number:', 'bc-business-central-sync' ) . '</strong> ' . esc_html( $customer_number ) . '</p>';
		}
	}

	/**
	 * Handle HPOS migration completion.
	 *
	 * @since 1.0.0
	 */
	public function handle_hpos_migration_completed() {
		// Log successful migration
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BC HPOS Migration Completed Successfully' );
		}
		
		// Trigger any post-migration actions
		do_action( 'bc_hpos_migration_completed' );
	}

	/**
	 * Handle HPOS migration failure.
	 *
	 * @since 1.0.0
	 */
	public function handle_hpos_migration_failed() {
		// Log migration failure
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BC HPOS Migration Failed' );
		}
		
		// Trigger any post-failure actions
		do_action( 'bc_hpos_migration_failed' );
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
			'migration_status'  => 'unknown',
		);

		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return $status;
		}

		$status['available'] = true;
		$status['enabled'] = $this->is_hpos_enabled();

		if ( $status['enabled'] ) {
			try {
				$status['custom_table_name'] = \Automattic\WooCommerce\Utilities\OrderUtil::get_table_for_orders( 'shop_order' );
				
				// Get usage percentage
				$hpos_count = \Automattic\WooCommerce\Utilities\OrderUtil::get_orders_count( 'custom' );
				$cpt_count = \Automattic\WooCommerce\Utilities\OrderUtil::get_orders_count( 'posts' );
				$total = $hpos_count + $cpt_count;
				
				if ( $total > 0 ) {
					$status['usage_percentage'] = round( ( $hpos_count / $total ) * 100, 2 );
				}

				// Check migration status
				if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
					$controller = \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::instance();
					$status['migration_status'] = $controller->get_migration_status();
				}
			} catch ( Exception $e ) {
				// Handle any errors gracefully
				error_log( 'BC HPOS Status Error: ' . $e->getMessage() );
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

	/**
	 * Get HPOS migration recommendations.
	 *
	 * @since 1.0.0
	 * @return array Migration recommendations.
	 */
	public function get_migration_recommendations() {
		$recommendations = array();

		if ( ! $this->is_hpos_supported() ) {
			$recommendations[] = __( 'Upgrade to WooCommerce 7.0 or higher to enable HPOS support.', 'bc-business-central-sync' );
		}

		if ( $this->is_hpos_supported() && ! $this->is_hpos_enabled() ) {
			$recommendations[] = __( 'Enable HPOS in WooCommerce settings for improved performance.', 'bc-business-central-sync' );
		}

		if ( $this->is_hpos_enabled() ) {
			$status = $this->get_hpos_status();
			if ( $status['usage_percentage'] < 100 ) {
				$recommendations[] = __( 'Consider migrating all orders to HPOS for optimal performance.', 'bc-business-central-sync' );
			}
		}

		return $recommendations;
	}
}

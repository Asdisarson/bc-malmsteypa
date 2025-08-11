<?php

/**
 * HPOS Utility Functions
 *
 * Helper functions for working with WooCommerce orders in an HPOS-compatible way.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_HPOS_Utils {

	/**
	 * Check if HPOS is available and enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if HPOS is available and enabled.
	 */
	public static function is_hpos_available() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			   \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Get order meta in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order Order ID or order object.
	 * @param string       $key   Meta key.
	 * @param bool         $single Whether to return a single value.
	 * @return mixed Meta value.
	 */
	public static function get_order_meta( $order, $key, $single = true ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		return $order->get_meta( $key, $single );
	}

	/**
	 * Update order meta in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order   Order ID or order object.
	 * @param string       $key     Meta key.
	 * @param mixed        $value   Meta value.
	 * @param string       $prev_value Previous value.
	 * @return bool True on success, false on failure.
	 */
	public static function update_order_meta( $order, $key, $value, $prev_value = '' ) {
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
	 * Add order meta in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order Order ID or order object.
	 * @param string       $key   Meta key.
	 * @param mixed        $value Meta value.
	 * @param bool         $unique Whether the same key should not be added.
	 * @return bool True on success, false on failure.
	 */
	public static function add_order_meta( $order, $key, $value, $unique = false ) {
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
	 * Delete order meta in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param int|WC_Order $order     Order ID or order object.
	 * @param string       $key       Meta key.
	 * @param mixed        $value     Meta value.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_order_meta( $order, $key, $value = '' ) {
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
	 * Get orders by meta value in an HPOS-compatible way.
	 *
	 * @since 1.0.0
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param string $compare    Comparison operator.
	 * @return array Array of order IDs.
	 */
	public static function get_orders_by_meta( $meta_key, $meta_value, $compare = '=' ) {
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
	 * Check if an order exists by meta value.
	 *
	 * @since 1.0.0
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool True if order exists, false otherwise.
	 */
	public static function order_exists_by_meta( $meta_key, $meta_value ) {
		$orders = self::get_orders_by_meta( $meta_key, $meta_value );
		return ! empty( $orders );
	}

	/**
	 * Get HPOS status information.
	 *
	 * @since 1.0.0
	 * @return array HPOS status information.
	 */
	public static function get_hpos_status() {
		$status = array(
			'enabled'           => false,
			'available'         => false,
			'custom_table_name' => '',
			'usage_percentage'  => 0,
		);

		if ( ! self::is_hpos_available() ) {
			return $status;
		}

		$status['available'] = true;
		$status['enabled'] = true;

		try {
			$status['custom_table_name'] = \Automattic\WooCommerce\Utilities\OrderUtil::get_table_for_orders( 'shop_order' );
			
			// Get usage percentage
			$hpos_count = \Automattic\WooCommerce\Utilities\OrderUtil::get_orders_count( 'custom' );
			$cpt_count = \Automattic\WooCommerce\Utilities\OrderUtil::get_orders_count( 'posts' );
			$total = $hpos_count + $cpt_count;
			
			if ( $total > 0 ) {
				$status['usage_percentage'] = round( ( $hpos_count / $total ) * 100, 2 );
			}
		} catch ( Exception $e ) {
			// Handle any errors gracefully
			error_log( 'BC HPOS Status Error: ' . $e->getMessage() );
		}

		return $status;
	}

	/**
	 * Get order count by meta value.
	 *
	 * @since 1.0.0
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param string $compare    Comparison operator.
	 * @return int Number of orders.
	 */
	public static function get_order_count_by_meta( $meta_key, $meta_value, $compare = '=' ) {
		$orders = self::get_orders_by_meta( $meta_key, $meta_value, $compare );
		return count( $orders );
	}

	/**
	 * Batch update order meta for multiple orders.
	 *
	 * @since 1.0.0
	 * @param array $order_ids Array of order IDs.
	 * @param string $meta_key Meta key.
	 * @param mixed $meta_value Meta value.
	 * @return array Results of the batch update.
	 */
	public static function batch_update_order_meta( $order_ids, $meta_key, $meta_value ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $order_ids as $order_id ) {
			try {
				$success = self::update_order_meta( $order_id, $meta_key, $meta_value );
				if ( $success ) {
					$results['success']++;
				} else {
					$results['failed']++;
					$results['errors'][] = "Failed to update order {$order_id}";
				}
			} catch ( Exception $e ) {
				$results['failed']++;
				$results['errors'][] = "Error updating order {$order_id}: " . $e->getMessage();
			}
		}

		return $results;
	}
}

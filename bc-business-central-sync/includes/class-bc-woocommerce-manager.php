<?php

/**
 * WooCommerce product management class.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_WooCommerce_Manager {

	/**
	 * Sync products from Business Central to WooCommerce.
	 *
	 * @param array $bc_products Products from Business Central.
	 * @return array
	 */
	public function sync_products_to_woocommerce( $bc_products ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'errors_list' => array()
		);

		foreach ( $bc_products as $bc_product ) {
			try {
				$result = $this->sync_single_product( $bc_product );
				
				if ( $result['status'] === 'created' ) {
					$results['created']++;
				} elseif ( $result['status'] === 'updated' ) {
					$results['updated']++;
				}
				
				// Log the sync
				$this->log_sync( $bc_product['id'], $bc_product['number'], 'success', '' );
				
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['errors_list'][] = array(
					'product' => $bc_product['number'],
					'error' => $e->getMessage()
				);
				
				// Log the error
				$this->log_sync( $bc_product['id'], $bc_product['number'], 'error', $e->getMessage() );
			}
		}

		return $results;
	}

	/**
	 * Sync a single product from Business Central to WooCommerce.
	 *
	 * @param array $bc_product Product data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function sync_single_product( $bc_product ) {
		// Check if product already exists by BC product number
		$existing_product_id = $this->get_product_by_bc_number( $bc_product['number'] );
		
		if ( $existing_product_id ) {
			return $this->update_existing_product( $existing_product_id, $bc_product );
		} else {
			return $this->create_new_product( $bc_product );
		}
	}

	/**
	 * Get WooCommerce product by Business Central product number.
	 *
	 * @param string $bc_number Business Central product number.
	 * @return int|false
	 */
	private function get_product_by_bc_number( $bc_number ) {
		global $wpdb;
		
		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_bc_product_number' AND meta_value = %s",
			$bc_number
		) );
		
		return $product_id ? (int) $product_id : false;
	}

	/**
	 * Create a new WooCommerce product.
	 *
	 * @param array $bc_product Product data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function create_new_product( $bc_product ) {
		$product_data = $this->prepare_product_data( $bc_product );
		
		// Create the product
		$product = new WC_Product_Simple();
		$product->set_name( $product_data['name'] );
		$product->set_description( $product_data['description'] );
		$product->set_short_description( $product_data['short_description'] );
		$product->set_regular_price( $product_data['regular_price'] );
		$product->set_status( 'draft' ); // Set as draft for review
		
		// Set product meta
		$product->update_meta_data( '_bc_product_number', $bc_product['number'] );
		$product->update_meta_data( '_bc_product_id', $bc_product['id'] );
		$product->update_meta_data( '_bc_last_sync', current_time( 'mysql' ) );
		$product->update_meta_data( '_bc_unit_cost', $bc_product['unitCost'] );
		$product->update_meta_data( '_bc_inventory', $bc_product['inventory'] );
		$product->update_meta_data( '_bc_blocked', $bc_product['blocked'] );
		
		$product_id = $product->save();
		
		if ( ! $product_id ) {
			throw new Exception( 'Failed to create product' );
		}

		return array(
			'status' => 'created',
			'product_id' => $product_id,
			'message' => 'Product created successfully'
		);
	}

	/**
	 * Update an existing WooCommerce product.
	 *
	 * @param int   $product_id WooCommerce product ID.
	 * @param array $bc_product Product data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function update_existing_product( $product_id, $bc_product ) {
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			throw new Exception( 'Product not found' );
		}

		$product_data = $this->prepare_product_data( $bc_product );
		
		// Update basic product information
		$product->set_name( $product_data['name'] );
		$product->set_description( $product_data['description'] );
		$product->set_short_description( $product_data['short_description'] );
		$product->set_regular_price( $product_data['regular_price'] );
		
		// Update meta data
		$product->update_meta_data( '_bc_last_sync', current_time( 'mysql' ) );
		$product->update_meta_data( '_bc_unit_cost', $bc_product['unitCost'] );
		$product->update_meta_data( '_bc_inventory', $bc_product['inventory'] );
		$product->update_meta_data( '_bc_blocked', $bc_product['blocked'] );
		
		$product->save();

		return array(
			'status' => 'updated',
			'product_id' => $product_id,
			'message' => 'Product updated successfully'
		);
	}

	/**
	 * Prepare product data for WooCommerce.
	 *
	 * @param array $bc_product Product data from Business Central.
	 * @return array
	 */
	private function prepare_product_data( $bc_product ) {
		$name = isset( $bc_product['displayName'] ) ? $bc_product['displayName'] : $bc_product['number'];
		$description = isset( $bc_product['description'] ) ? $bc_product['description'] : '';
		$price = isset( $bc_product['unitPrice'] ) ? $bc_product['unitPrice'] : 0;
		
		// Create short description from full description
		$short_description = '';
		if ( $description ) {
			$short_description = wp_trim_words( $description, 20, '...' );
		}
		
		return array(
			'name' => $name,
			'description' => $description,
			'short_description' => $short_description,
			'regular_price' => $price,
		);
	}

	/**
	 * Log sync operation.
	 *
	 * @param string $bc_product_id Business Central product ID.
	 * @param string $bc_product_number Business Central product number.
	 * @param string $status Sync status.
	 * @param string $error_message Error message if any.
	 */
	private function log_sync( $bc_product_id, $bc_product_number, $status, $error_message ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_sync_logs';
		
		$wpdb->insert(
			$table_name,
			array(
				'product_id' => $bc_product_id,
				'bc_product_code' => $bc_product_number,
				'sync_status' => $status,
				'error_message' => $error_message,
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

}

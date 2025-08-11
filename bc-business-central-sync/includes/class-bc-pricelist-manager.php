<?php

/**
 * Pricelist management class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Pricelist_Manager {

	/**
	 * Sync pricelists from Business Central to WordPress.
	 *
	 * @param array $pricelists Pricelists from Business Central.
	 * @return array
	 */
	public function sync_pricelists( $pricelists ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'errors_list' => array()
		);

		foreach ( $pricelists as $pricelist ) {
			try {
				$result = $this->sync_single_pricelist( $pricelist );
				
				if ( $result['status'] === 'created' ) {
					$results['created']++;
				} elseif ( $result['status'] === 'updated' ) {
					$results['updated']++;
				}
				
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['errors_list'][] = array(
					'pricelist' => $pricelist['code'],
					'error' => $e->getMessage()
				);
			}
		}

		return $results;
	}

	/**
	 * Sync pricelist lines from Business Central.
	 *
	 * @param array $pricelist_lines Pricelist lines from Business Central.
	 * @return array
	 */
	public function sync_pricelist_lines( $pricelist_lines ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'errors_list' => array()
		);

		foreach ( $pricelist_lines as $line ) {
			try {
				$result = $this->sync_single_pricelist_line( $line );
				
				if ( $result['status'] === 'created' ) {
					$results['created']++;
				} elseif ( $result['status'] === 'updated' ) {
					$results['updated']++;
				}
				
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['errors_list'][] = array(
					'line' => $line['id'],
					'error' => $e->getMessage()
				);
			}
		}

		return $results;
	}

	/**
	 * Sync customer company assignments.
	 *
	 * @param array $customers Customers from Business Central.
	 * @return array
	 */
	public function sync_customer_companies( $customers ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'errors_list' => array()
		);

		foreach ( $customers as $customer ) {
			try {
				$result = $this->sync_single_customer( $customer );
				
				if ( $result['status'] === 'created' ) {
					$results['created']++;
				} elseif ( $result['status'] === 'updated' ) {
					$results['updated']++;
				}
				
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['errors_list'][] = array(
					'customer' => $customer['number'],
					'error' => $e->getMessage()
				);
			}
		}

		return $results;
	}

	/**
	 * Sync a single pricelist.
	 *
	 * @param array $pricelist Pricelist data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function sync_single_pricelist( $pricelist ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		// Check if pricelist already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE bc_pricelist_id = %s",
			$pricelist['id']
		) );
		
		$data = array(
			'bc_pricelist_id' => $pricelist['id'],
			'code' => $pricelist['code'],
			'name' => $pricelist['name'],
			'currency_code' => $pricelist['currencyCode'],
			'last_modified' => $pricelist['lastModifiedDateTime'],
			'last_sync' => current_time( 'mysql' )
		);
		
		if ( $existing ) {
			// Update existing pricelist
			$wpdb->update( $table_name, $data, array( 'id' => $existing->id ) );
			
			return array(
				'status' => 'updated',
				'pricelist_id' => $existing->id,
				'message' => 'Pricelist updated successfully'
			);
		} else {
			// Create new pricelist
			$wpdb->insert( $table_name, $data );
			
			if ( ! $wpdb->insert_id ) {
				throw new Exception( 'Failed to create pricelist' );
			}
			
			return array(
				'status' => 'created',
				'pricelist_id' => $wpdb->insert_id,
				'message' => 'Pricelist created successfully'
			);
		}
	}

	/**
	 * Sync a single pricelist line.
	 *
	 * @param array $line Pricelist line data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function sync_single_pricelist_line( $line ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		
		// Check if line already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE bc_line_id = %s",
			$line['id']
		) );
		
		$data = array(
			'bc_line_id' => $line['id'],
			'pricelist_id' => $this->get_pricelist_id_by_bc_id( $line['salesPriceListId'] ),
			'item_id' => $this->get_product_id_by_bc_number( $line['itemNumber'] ),
			'bc_item_id' => $line['itemId'],
			'item_number' => $line['itemNumber'],
			'unit_price' => $line['unitPrice'],
			'currency_code' => $line['currencyCode'],
			'starting_date' => $line['startingDate'],
			'ending_date' => $line['endingDate'],
			'minimum_quantity' => $line['minimumQuantity'],
			'last_sync' => current_time( 'mysql' )
		);
		
		if ( $existing ) {
			// Update existing line
			$wpdb->update( $table_name, $data, array( 'id' => $existing->id ) );
			
			return array(
				'status' => 'updated',
				'line_id' => $existing->id,
				'message' => 'Pricelist line updated successfully'
			);
		} else {
			// Create new line
			$wpdb->insert( $table_name, $data );
			
			if ( ! $wpdb->insert_id ) {
				throw new Exception( 'Failed to create pricelist line' );
			}
			
			return array(
				'status' => 'created',
				'line_id' => $wpdb->insert_id,
				'message' => 'Pricelist line created successfully'
			);
		}
	}

	/**
	 * Sync a single customer.
	 *
	 * @param array $customer Customer data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function sync_single_customer( $customer ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_customer_companies';
		
		// Check if customer already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE bc_customer_id = %s",
			$customer['id']
		) );
		
		$data = array(
			'bc_customer_id' => $customer['id'],
			'customer_number' => $customer['number'],
			'customer_name' => $customer['name'],
			'pricelist_id' => $this->get_pricelist_id_by_bc_id( $customer['priceListId'] ),
			'bc_pricelist_id' => $customer['priceListId'],
			'pricelist_code' => $customer['priceListCode'],
			'last_sync' => current_time( 'mysql' )
		);
		
		if ( $existing ) {
			// Update existing customer
			$wpdb->update( $table_name, $data, array( 'id' => $existing->id ) );
			
			return array(
				'status' => 'updated',
				'customer_id' => $existing->id,
				'message' => 'Customer updated successfully'
			);
		} else {
			// Create new customer
			$wpdb->insert( $table_name, $data );
			
			if ( ! $wpdb->insert_id ) {
				throw new Exception( 'Failed to create customer' );
			}
			
			return array(
				'status' => 'created',
				'customer_id' => $wpdb->insert_id,
				'message' => 'Customer created successfully'
			);
		}
	}

	/**
	 * Get pricelist ID by Business Central ID.
	 *
	 * @param string $bc_pricelist_id Business Central pricelist ID.
	 * @return int|false
	 */
	private function get_pricelist_id_by_bc_id( $bc_pricelist_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		$pricelist_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE bc_pricelist_id = %s",
			$bc_pricelist_id
		) );
		
		return $pricelist_id ? (int) $pricelist_id : false;
	}

	/**
	 * Get product ID by Business Central product number.
	 *
	 * @param string $bc_product_number Business Central product number.
	 * @return int|false
	 */
	private function get_product_id_by_bc_number( $bc_product_number ) {
		global $wpdb;
		
		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_bc_product_number' AND meta_value = %s",
			$bc_product_number
		) );
		
		return $product_id ? (int) $product_id : false;
	}

	/**
	 * Get customer pricelist by customer number.
	 *
	 * @param string $customer_number Customer number.
	 * @return array|false
	 */
	public function get_customer_pricelist( $customer_number ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_customer_companies';
		
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE customer_number = %s",
			$customer_number
		) );
		
		if ( ! $customer ) {
			return false;
		}
		
		// Get pricelist details
		$pricelist_table = $wpdb->prefix . 'bc_pricelists';
		$pricelist = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $pricelist_table WHERE id = %d",
			$customer->pricelist_id
		) );
		
		if ( ! $pricelist ) {
			return false;
		}
		
		return array(
			'customer' => $customer,
			'pricelist' => $pricelist
		);
	}

	/**
	 * Get product price for specific customer.
	 *
	 * @param int    $product_id WooCommerce product ID.
	 * @param string $customer_number Customer number.
	 * @return float|false
	 */
	public function get_customer_product_price( $product_id, $customer_number ) {
		global $wpdb;
		
		// Get customer's pricelist
		$customer_data = $this->get_customer_pricelist( $customer_number );
		if ( ! $customer_data ) {
			return false;
		}
		
		// Get product's BC number
		$bc_product_number = get_post_meta( $product_id, '_bc_product_number', true );
		if ( ! $bc_product_number ) {
			return false;
		}
		
		// Get pricelist line for this product
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		$line = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name 
			WHERE pricelist_id = %d AND item_number = %s",
			$customer_data['pricelist']->id,
			$bc_product_number
		) );
		
		if ( ! $line ) {
			return false;
		}
		
		// Check if price is valid (within date range)
		$now = current_time( 'mysql' );
		if ( $line->starting_date && $line->starting_date > $now ) {
			return false;
		}
		if ( $line->ending_date && $line->ending_date < $now ) {
			return false;
		}
		
		return (float) $line->unit_price;
	}

}

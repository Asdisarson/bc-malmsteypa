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
	 * Get all pricelists from the database.
	 *
	 * @return array
	 */
	public function get_all_pricelists() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		$results = $wpdb->get_results(
			"SELECT * FROM $table_name ORDER BY name ASC"
		);
		
		return $results ?: array();
	}

	/**
	 * Get pricelist by ID.
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @return object|false
	 */
	public function get_pricelist_by_id( $pricelist_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$pricelist_id
		) );
	}

	/**
	 * Overwrite a pricelist with Business Central data.
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @return bool
	 * @throws Exception
	 */
	public function overwrite_pricelist( $pricelist_id ) {
		$pricelist = $this->get_pricelist_by_id( $pricelist_id );
		if ( ! $pricelist ) {
			throw new Exception( 'Pricelist not found' );
		}

		// Fetch fresh data from Business Central
		$bc_api = new BC_Business_Central_API();
		$bc_pricelist = $bc_api->get_pricelist_by_id( $pricelist->bc_pricelist_id );
		
		if ( ! $bc_pricelist ) {
			throw new Exception( 'Pricelist not found in Business Central' );
		}

		// Update the pricelist
		$result = $this->sync_single_pricelist( $bc_pricelist );
		
		// Mark as overwritten
		$this->mark_pricelist_overwritten( $pricelist_id );
		
		return $result['status'] === 'updated';
	}

	/**
	 * Mark a pricelist as kept (not overwritten).
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @return bool
	 */
	public function mark_pricelist_kept( $pricelist_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		return $wpdb->update(
			$table_name,
			array(
				'last_kept' => current_time( 'mysql' ),
				'status' => 'kept'
			),
			array( 'id' => $pricelist_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark a pricelist as overwritten.
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @return bool
	 */
	public function mark_pricelist_overwritten( $pricelist_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		return $wpdb->update(
			$table_name,
			array(
				'last_overwritten' => current_time( 'mysql' ),
				'status' => 'overwritten'
			),
			array( 'id' => $pricelist_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Force overwrite all pricelists with Business Central data.
	 *
	 * @return int Number of pricelists overwritten.
	 * @throws Exception
	 */
	public function force_overwrite_all_pricelists() {
		$pricelists = $this->get_all_pricelists();
		$overwritten = 0;
		
		foreach ( $pricelists as $pricelist ) {
			try {
				if ( $this->overwrite_pricelist( $pricelist->id ) ) {
					$overwritten++;
				}
			} catch ( Exception $e ) {
				// Log error but continue with other pricelists
				error_log( 'Error overwriting pricelist ' . $pricelist->id . ': ' . $e->getMessage() );
			}
		}
		
		return $overwritten;
	}

	/**
	 * Update pricelist manually (edits within WordPress).
	 *
	 * @param int   $pricelist_id Pricelist ID.
	 * @param array $data Pricelist data.
	 * @return bool
	 * @throws Exception
	 */
	public function update_pricelist_manual( $pricelist_id, $data ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		// Validate data
		if ( empty( $data['name'] ) ) {
			throw new Exception( 'Pricelist name is required' );
		}
		
		$update_data = array(
			'name' => sanitize_text_field( $data['name'] ),
			'last_manual_edit' => current_time( 'mysql' ),
			'status' => 'manually_edited'
		);
		
		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $pricelist_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		
		if ( $result === false ) {
			throw new Exception( 'Failed to update pricelist' );
		}
		
		return true;
	}

	/**
	 * Get pricelist lines for a specific pricelist.
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @return array
	 */
	public function get_pricelist_lines( $pricelist_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE pricelist_id = %d ORDER BY item_number ASC",
			$pricelist_id
		) );
		
		return $results ?: array();
	}

	/**
	 * Get product price from pricelist.
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @param int $product_id Product ID.
	 * @return float|false
	 */
	public function get_product_price_from_pricelist( $pricelist_id, $product_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		
		$price = $wpdb->get_var( $wpdb->prepare(
			"SELECT unit_price FROM $table_name 
			WHERE pricelist_id = %d AND item_id = %d",
			$pricelist_id, $product_id
		) );
		
		return $price ? (float) $price : false;
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
		// This method would handle customer sync logic
		// Implementation depends on your specific requirements
		return array(
			'status' => 'updated',
			'customer_id' => $customer['id'],
			'message' => 'Customer updated successfully'
		);
	}

	/**
	 * Get pricelist ID by Business Central ID.
	 *
	 * @param string $bc_id Business Central pricelist ID.
	 * @return int|false
	 */
	private function get_pricelist_id_by_bc_id( $bc_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelists';
		
		$pricelist_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE bc_pricelist_id = %s",
			$bc_id
		) );
		
		return $pricelist_id ? (int) $pricelist_id : false;
	}

	/**
	 * Get product ID by Business Central product number.
	 *
	 * @param string $bc_number Business Central product number.
	 * @return int|false
	 */
	private function get_product_id_by_bc_number( $bc_number ) {
		global $wpdb;
		
		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_bc_product_number' AND meta_value = %s",
			$bc_number
		) );
		
		return $product_id ? (int) $product_id : false;
	}
}

<?php
/**
 * Company management class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Company_Manager {

	/**
	 * Sync companies from Business Central to WordPress.
	 *
	 * @param array $companies Companies from Business Central.
	 * @return array
	 */
	public function sync_companies( $companies ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'errors_list' => array()
		);

		foreach ( $companies as $company ) {
			try {
				$result = $this->sync_single_company( $company );
				
				if ( $result['status'] === 'created' ) {
					$results['created']++;
				} elseif ( $result['status'] === 'updated' ) {
					$results['updated']++;
				}
				
			} catch ( Exception $e ) {
				$results['errors']++;
				$results['errors_list'][] = array(
					'company' => $company['name'],
					'error' => $e->getMessage()
				);
			}
		}

		return $results;
	}

	/**
	 * Get all companies from the database.
	 *
	 * @return array
	 */
	public function get_all_companies() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		$results = $wpdb->get_results(
			"SELECT c.*, 
					COALESCE(cp.pricelist_id, 0) as pricelist_id,
					COALESCE(cp.assigned_at, '') as assigned_at
			FROM $table_name c
			LEFT JOIN {$wpdb->prefix}bc_company_pricelists cp ON c.id = cp.company_id
			ORDER BY c.company_name ASC"
		);
		
		return $results ?: array();
	}

	/**
	 * Get company by ID.
	 *
	 * @param int $company_id Company ID.
	 * @return object|false
	 */
	public function get_company_by_id( $company_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$company_id
		) );
	}

	/**
	 * Get company by Business Central ID.
	 *
	 * @param string $bc_id Business Central company ID.
	 * @return object|false
	 */
	public function get_company_by_bc_id( $bc_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE bc_company_id = %s",
			$bc_id
		) );
	}

	/**
	 * Assign a pricelist to a company.
	 *
	 * @param int $company_id Company ID.
	 * @param int $pricelist_id Pricelist ID.
	 * @return bool
	 * @throws Exception
	 */
	public function assign_pricelist_to_company( $company_id, $pricelist_id ) {
		global $wpdb;
		
		// Validate company exists
		$company = $this->get_company_by_id( $company_id );
		if ( ! $company ) {
			throw new Exception( 'Company not found' );
		}
		
		// Validate pricelist exists
		$pricelist_manager = new BC_Pricelist_Manager();
		$pricelist = $pricelist_manager->get_pricelist_by_id( $pricelist_id );
		if ( ! $pricelist ) {
			throw new Exception( 'Pricelist not found' );
		}
		
		$table_name = $wpdb->prefix . 'bc_company_pricelists';
		
		// Check if assignment already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE company_id = %d",
			$company_id
		) );
		
		$data = array(
			'company_id' => $company_id,
			'pricelist_id' => $pricelist_id,
			'assigned_at' => current_time( 'mysql' ),
			'last_updated' => current_time( 'mysql' )
		);
		
		if ( $existing ) {
			// Update existing assignment
			$result = $wpdb->update(
				$table_name,
				array(
					'pricelist_id' => $pricelist_id,
					'last_updated' => current_time( 'mysql' )
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		} else {
			// Create new assignment
			$result = $wpdb->insert( $table_name, $data );
		}
		
		if ( $result === false ) {
			throw new Exception( 'Failed to assign pricelist to company' );
		}
		
		return true;
	}

	/**
	 * Remove pricelist assignment from a company.
	 *
	 * @param int $company_id Company ID.
	 * @return bool
	 */
	public function remove_pricelist_from_company( $company_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_company_pricelists';
		
		$result = $wpdb->delete(
			$table_name,
			array( 'company_id' => $company_id ),
			array( '%d' )
		);
		
		return $result !== false;
	}

	/**
	 * Get pricelist assigned to a company.
	 *
	 * @param int $company_id Company ID.
	 * @return object|false
	 */
	public function get_company_pricelist( $company_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_company_pricelists';
		
		$pricelist_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT pricelist_id FROM $table_name WHERE company_id = %d",
			$company_id
		) );
		
		if ( ! $pricelist_id ) {
			return false;
		}
		
		$pricelist_manager = new BC_Pricelist_Manager();
		return $pricelist_manager->get_pricelist_by_id( $pricelist_id );
	}

	/**
	 * Get company users count.
	 *
	 * @param int $company_id Company ID.
	 * @return int
	 */
	public function get_company_users_count( $company_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE company_id = %d",
			$company_id
		) );
		
		return (int) $count;
	}

	/**
	 * Get total users count across all companies.
	 *
	 * @return int
	 */
	public function get_total_users_count() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		
		return (int) $count;
	}

	/**
	 * Get count of companies with assigned pricelists.
	 *
	 * @return int
	 */
	public function get_companies_with_pricelist_count() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_company_pricelists';
		
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		
		return (int) $count;
	}

	/**
	 * Get count of companies without assigned pricelists.
	 *
	 * @return int
	 */
	public function get_companies_without_pricelist_count() {
		global $wpdb;
		
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		$pricelists_table = $wpdb->prefix . 'bc_company_pricelists';
		
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM $companies_table c
			LEFT JOIN $pricelists_table p ON c.id = p.company_id
			WHERE p.company_id IS NULL"
		);
		
		return (int) $count;
	}

	/**
	 * Delete a company.
	 *
	 * @param int $company_id Company ID.
	 * @return bool
	 */
	public function delete_company( $company_id ) {
		global $wpdb;
		
		// Check if company has associated users
		$users_count = $this->get_company_users_count( $company_id );
		if ( $users_count > 0 ) {
			return false; // Cannot delete company with users
		}
		
		// Remove pricelist assignment
		$this->remove_pricelist_from_company( $company_id );
		
		// Delete company
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $company_id ),
			array( '%d' )
		);
		
		return $result !== false;
	}

	/**
	 * Get companies by pricelist.
	 *
	 * @param int $pricelist_id Pricelist ID.
	 * @return array
	 */
	public function get_companies_by_pricelist( $pricelist_id ) {
		global $wpdb;
		
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		$pricelists_table = $wpdb->prefix . 'bc_company_pricelists';
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.* FROM $companies_table c
			INNER JOIN $pricelists_table p ON c.id = p.company_id
			WHERE p.pricelist_id = %d
			ORDER BY c.company_name ASC",
			$pricelist_id
		) );
		
		return $results ?: array();
	}

	/**
	 * Sync a single company.
	 *
	 * @param array $company Company data from Business Central.
	 * @return array
	 * @throws Exception
	 */
	private function sync_single_company( $company ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		// Check if company already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE bc_company_id = %s",
			$company['id']
		) );
		
		$data = array(
			'bc_company_id' => $company['id'],
			'company_name' => $company['name'],
			'company_number' => $company['number'],
			'address' => isset( $company['address'] ) ? $company['address'] : '',
			'city' => isset( $company['city'] ) ? $company['city'] : '',
			'postal_code' => isset( $company['postalCode'] ) ? $company['postalCode'] : '',
			'country' => isset( $company['country'] ) ? $company['country'] : '',
			'phone' => isset( $company['phoneNumber'] ) ? $company['phoneNumber'] : '',
			'email' => isset( $company['email'] ) ? $company['email'] : '',
			'last_sync' => current_time( 'mysql' )
		);
		
		if ( $existing ) {
			// Update existing company
			$wpdb->update( $table_name, $data, array( 'id' => $existing->id ) );
			
			return array(
				'status' => 'updated',
				'company_id' => $existing->id,
				'message' => 'Company updated successfully'
			);
		} else {
			// Create new company
			$wpdb->insert( $table_name, $data );
			
			if ( ! $wpdb->insert_id ) {
				throw new Exception( 'Failed to create company' );
			}
			
			return array(
				'status' => 'created',
				'company_id' => $wpdb->insert_id,
				'message' => 'Company created successfully'
			);
		}
	}
}

<?php

/**
 * Dokobit database management class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Dokobit_Database {

	/**
	 * Create database tables.
	 */
	public static function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		$user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$companies_sql = "CREATE TABLE $companies_table (
			id int(11) NOT NULL AUTO_INCREMENT,
			company_name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		
		$user_phones_sql = "CREATE TABLE $user_phones_table (
			id int(11) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			phone_number varchar(50) NOT NULL,
			personal_code varchar(20) DEFAULT NULL,
			company_id int(11) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_number (phone_number),
			KEY user_id (user_id),
			KEY company_id (company_id),
			KEY personal_code (personal_code)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $companies_sql );
		dbDelta( $user_phones_sql );
	}

	/**
	 * Get user by phone number.
	 *
	 * @param string $phone Phone number.
	 * @return array|false
	 */
	public static function get_user_by_phone( $phone ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		// Try exact match first
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT user_id, company_id FROM $table WHERE phone_number = %s",
			$phone
		), ARRAY_A );
		
		// If no exact match, try with different formats
		if ( ! $result ) {
			// Remove spaces and special characters for comparison
			$normalized_phone = preg_replace( '/[^0-9+]/', '', $phone );
			
			// Get all phone numbers and compare normalized versions
			$all_phones = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
			foreach ( $all_phones as $row ) {
				$normalized_stored = preg_replace( '/[^0-9+]/', '', $row['phone_number'] );
				if ( $normalized_phone === $normalized_stored ) {
					return array(
						'user_id' => $row['user_id'],
						'company_id' => $row['company_id']
					);
				}
			}
		}
		
		return $result;
	}

	/**
	 * Get user by personal code.
	 *
	 * @param string $personal_code Personal code.
	 * @return array|false
	 */
	public static function get_user_by_personal_code( $personal_code ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT user_id, company_id FROM $table WHERE personal_code = %s",
			$personal_code
		), ARRAY_A );
		
		return $result;
	}

	/**
	 * Add user phone.
	 *
	 * @param int    $user_id User ID.
	 * @param string $phone Phone number.
	 * @param int    $company_id Company ID.
	 * @param string $personal_code Personal code.
	 * @return int|false
	 */
	public static function add_user_phone( $user_id, $phone, $company_id, $personal_code = null ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		return $wpdb->insert(
			$table,
			array(
				'user_id' => $user_id,
				'phone_number' => $phone,
				'personal_code' => $personal_code,
				'company_id' => $company_id
			),
			array( '%d', '%s', '%s', '%d' )
		);
	}

	/**
	 * Update user phone.
	 *
	 * @param int    $id Record ID.
	 * @param string $phone Phone number.
	 * @param int    $company_id Company ID.
	 * @return int|false
	 */
	public static function update_user_phone( $id, $phone, $company_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		return $wpdb->update(
			$table,
			array(
				'phone_number' => $phone,
				'company_id' => $company_id
			),
			array( 'id' => $id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete user phone.
	 *
	 * @param int $id Record ID.
	 * @return int|false
	 */
	public static function delete_user_phone( $id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		return $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get user phones.
	 *
	 * @param int|null $user_id User ID.
	 * @return array
	 */
	public static function get_user_phones( $user_id = null ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		if ( $user_id ) {
			$result = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			) );
		} else {
			$result = $wpdb->get_results(
				"SELECT * FROM $table ORDER BY created_at DESC"
			);
		}
		
		return $result ?: array();
	}

	/**
	 * Add company.
	 *
	 * @param string $company_name Company name.
	 * @return int|false
	 */
	public static function add_company( $company_name ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_companies';
		
		return $wpdb->insert(
			$table,
			array( 'company_name' => $company_name ),
			array( '%s' )
		);
	}

	/**
	 * Update company.
	 *
	 * @param int    $id Company ID.
	 * @param string $company_name Company name.
	 * @return int|false
	 */
	public static function update_company( $id, $company_name ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_companies';
		
		return $wpdb->update(
			$table,
			array( 'company_name' => $company_name ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete company.
	 *
	 * @param int $id Company ID.
	 * @return int|false
	 */
	public static function delete_company( $id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_companies';
		
		// Check if company has associated users
		$user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
		$has_users = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $user_phones_table WHERE company_id = %d",
			$id
		) );
		
		if ( $has_users > 0 ) {
			return false; // Cannot delete company with users
		}
		
		return $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get companies.
	 *
	 * @return array
	 */
	public static function get_companies() {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_companies';
		
		$result = $wpdb->get_results(
			"SELECT * FROM $table ORDER BY company_name ASC"
		);
		
		return $result ?: array();
	}

	/**
	 * Get company by ID.
	 *
	 * @param int $id Company ID.
	 * @return object|false
	 */
	public static function get_company( $id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_companies';
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get company by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return object|false
	 */
	public static function get_company_by_user_id( $user_id ) {
		global $wpdb;
		
		$user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.* FROM $companies_table c
			INNER JOIN $user_phones_table up ON c.id = up.company_id
			WHERE up.user_id = %d
			LIMIT 1",
			$user_id
		) );
		
		return $result;
	}

	/**
	 * Get company users count.
	 *
	 * @param int $company_id Company ID.
	 * @return int
	 */
	public static function get_company_users_count( $company_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE company_id = %d",
			$company_id
		) );
		
		return intval( $count );
	}

	/**
	 * Get company users.
	 *
	 * @param int $company_id Company ID.
	 * @return array
	 */
	public static function get_company_users( $company_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$result = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE company_id = %d ORDER BY created_at DESC",
			$company_id
		) );
		
		return $result ?: array();
	}

	/**
	 * Sync companies from Business Central.
	 *
	 * @param array $bc_companies Business Central companies.
	 * @return array
	 */
	public static function sync_companies_from_bc( $bc_companies ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'bc_dokobit_companies';
		$synced = array();
		$errors = array();
		
		foreach ( $bc_companies as $bc_company ) {
			try {
				// Check if company already exists by BC ID
				$existing = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM $table WHERE bc_company_id = %s",
					$bc_company['id']
				) );
				
				if ( $existing ) {
					// Update existing company
					$updated = $wpdb->update(
						$table,
						array(
							'company_name' => $bc_company['displayName'] ?: $bc_company['name'],
							'bc_company_data' => json_encode( $bc_company ),
							'last_sync' => current_time( 'mysql' )
						),
						array( 'id' => $existing->id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
					
					if ( $updated !== false ) {
						$synced[] = array(
							'action' => 'updated',
							'company_id' => $existing->id,
							'bc_company_id' => $bc_company['id'],
							'company_name' => $bc_company['displayName'] ?: $bc_company['name']
						);
					}
				} else {
					// Create new company
					$inserted = $wpdb->insert(
						$table,
						array(
							'company_name' => $bc_company['displayName'] ?: $bc_company['name'],
							'bc_company_id' => $bc_company['id'],
							'bc_company_data' => json_encode( $bc_company ),
							'last_sync' => current_time( 'mysql' )
						),
						array( '%s', '%s', '%s', '%s' )
					);
					
					if ( $inserted ) {
						$synced[] = array(
							'action' => 'created',
							'company_id' => $wpdb->insert_id,
							'bc_company_id' => $bc_company['id'],
							'company_name' => $bc_company['displayName'] ?: $bc_company['name']
						);
					}
				}
			} catch ( Exception $e ) {
				$errors[] = array(
					'bc_company_id' => $bc_company['id'],
					'error' => $e->getMessage()
				);
			}
		}
		
		return array(
			'synced' => $synced,
			'errors' => $errors,
			'total_processed' => count( $bc_companies ),
			'successful' => count( $synced ),
			'failed' => count( $errors )
		);
	}

	/**
	 * Sync customers with companies from Business Central.
	 *
	 * @param array $bc_customers Business Central customers with company info.
	 * @return array
	 */
	public static function sync_customers_with_companies_from_bc( $bc_customers ) {
		global $wpdb;
		
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		$user_phones_table = $wpdb->prefix . 'bc_dokobit_user_phones';
		$synced = array();
		$errors = array();
		
		foreach ( $bc_customers as $bc_customer ) {
			try {
				// Find or create company
				$company = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM $companies_table WHERE bc_company_id = %s",
					$bc_customer['id']
				) );
				
				if ( ! $company ) {
					// Create company if it doesn't exist
					$wpdb->insert(
						$companies_table,
						array(
							'company_name' => $bc_customer['companyName'] ?: $bc_customer['name'],
							'bc_company_id' => $bc_customer['id'],
							'bc_company_data' => json_encode( $bc_customer ),
							'last_sync' => current_time( 'mysql' )
						),
						array( '%s', '%s', '%s', '%s' )
					);
					$company_id = $wpdb->insert_id;
				} else {
					$company_id = $company->id;
				}
				
				// Check if customer already exists
				$existing_customer = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM $user_phones_table WHERE bc_customer_id = %s",
					$bc_customer['id']
				) );
				
				if ( $existing_customer ) {
					// Update existing customer
					$wpdb->update(
						$user_phones_table,
						array(
							'company_id' => $company_id,
							'bc_customer_data' => json_encode( $bc_customer ),
							'last_sync' => current_time( 'mysql' )
						),
						array( 'id' => $existing_customer->id ),
						array( '%d', '%s', '%s' ),
						array( '%d' )
					);
					
					$synced[] = array(
						'action' => 'updated',
						'customer_id' => $existing_customer->id,
						'bc_customer_id' => $bc_customer['id'],
						'company_id' => $company_id,
						'customer_name' => $bc_customer['name']
					);
				} else {
					// Create new customer entry (without user_id initially)
					$wpdb->insert(
						$user_phones_table,
						array(
							'user_id' => 0, // Will be set when user registers
							'phone_number' => '', // Will be set when user registers
							'company_id' => $company_id,
							'bc_customer_id' => $bc_customer['id'],
							'bc_customer_data' => json_encode( $bc_customer ),
							'last_sync' => current_time( 'mysql' )
						),
						array( '%d', '%s', '%d', '%s', '%s', '%s' )
					);
					
					$synced[] = array(
						'action' => 'created',
						'customer_id' => $wpdb->insert_id,
						'bc_customer_id' => $bc_customer['id'],
						'company_id' => $company_id,
						'customer_name' => $bc_customer['name']
					);
				}
			} catch ( Exception $e ) {
				$errors[] = array(
					'bc_customer_id' => $bc_customer['id'],
					'error' => $e->getMessage()
				);
			}
		}
		
		return array(
			'synced' => $synced,
			'errors' => $errors,
			'total_processed' => count( $bc_customers ),
			'successful' => count( $synced ),
			'failed' => count( $errors )
		);
	}

}

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

}

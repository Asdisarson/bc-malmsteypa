<?php
if (!defined('ABSPATH')) {
	exit;
}

class BCC_Dokobit_Database {

	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$companies_table = $wpdb->prefix . 'dokobit_companies';
		$user_phones_table = $wpdb->prefix . 'dokobit_user_phones';

        $companies_sql = "CREATE TABLE $companies_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_name varchar(255) NOT NULL,
            kennitala varchar(32) DEFAULT '',
            business_central_id varchar(64) DEFAULT '',
            address_line1 varchar(255) DEFAULT '',
            address_line2 varchar(255) DEFAULT '',
            city varchar(128) DEFAULT '',
            state varchar(128) DEFAULT '',
            postal_code varchar(32) DEFAULT '',
            country varchar(8) DEFAULT '',
            email varchar(191) DEFAULT '',
            phone varchar(64) DEFAULT '',
            balance_due decimal(18,2) DEFAULT 0,
            credit_limit decimal(18,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_kennitala (kennitala),
            KEY idx_bc_id (business_central_id)
        ) $charset_collate;";

		$user_phones_sql = "CREATE TABLE $user_phones_table (
			id int(11) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			phone_number varchar(50) NOT NULL,
			personal_code varchar(20) DEFAULT NULL,
			company_id int(11) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY phone_number (phone_number),
			KEY user_id (user_id),
			KEY company_id (company_id),
			KEY personal_code (personal_code)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($companies_sql);
		dbDelta($user_phones_sql);
	}

	public static function get_user_by_phone($phone) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_user_phones';

		$result = $wpdb->get_row($wpdb->prepare(
			"SELECT user_id, company_id FROM $table WHERE phone_number = %s",
			$phone
		), ARRAY_A);

		if (!$result) {
			$normalized_phone = preg_replace('/[^0-9+]/', '', $phone);
			$all_phones = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
			foreach ($all_phones as $row) {
				$normalized_stored = preg_replace('/[^0-9+]/', '', $row['phone_number']);
				if ($normalized_phone === $normalized_stored) {
					return array(
						'user_id' => $row['user_id'],
						'company_id' => $row['company_id']
					);
				}
			}
		}

		return $result;
	}

	public static function get_user_by_personal_code($personal_code) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_user_phones';

		$result = $wpdb->get_row($wpdb->prepare(
			"SELECT user_id, company_id FROM $table WHERE personal_code = %s",
			$personal_code
		), ARRAY_A);

		return $result;
	}

	public static function add_user_phone($user_id, $phone, $company_id, $personal_code = null) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_user_phones';

		return $wpdb->insert(
			$table,
			array(
				'user_id' => $user_id,
				'phone_number' => $phone,
				'personal_code' => $personal_code,
				'company_id' => $company_id
			),
			array('%d', '%s', '%s', '%d')
		);
	}

	public static function update_user_phone($id, $phone, $company_id) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_user_phones';

		return $wpdb->update(
			$table,
			array(
				'phone_number' => $phone,
				'company_id' => $company_id
			),
			array('id' => $id),
			array('%s', '%d'),
			array('%d')
		);
	}

	public static function delete_user_phone($id) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_user_phones';

		return $wpdb->delete($table, array('id' => $id), array('%d'));
	}

	public static function get_user_phones($user_id = null) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_user_phones';
		$companies_table = $wpdb->prefix . 'dokobit_companies';

		$query = "SELECT up.*, c.company_name, u.user_login, u.user_email 
				  FROM $table up
				  LEFT JOIN $companies_table c ON up.company_id = c.id
				  LEFT JOIN {$wpdb->users} u ON up.user_id = u.ID";

		if ($user_id) {
			$query .= $wpdb->prepare(" WHERE up.user_id = %d", $user_id);
		}

		$query .= " ORDER BY up.created_at DESC";

		return $wpdb->get_results($query);
	}

	public static function add_company($company_name) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_companies';

		return $wpdb->insert(
			$table,
			array('company_name' => $company_name),
			array('%s')
		);
	}

	public static function update_company($id, $company_name) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_companies';

		return $wpdb->update(
			$table,
			array('company_name' => $company_name),
			array('id' => $id),
			array('%s'),
			array('%d')
		);
	}

	public static function delete_company($id) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_companies';
		$user_phones_table = $wpdb->prefix . 'dokobit_user_phones';

		$has_users = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $user_phones_table WHERE company_id = %d",
			$id
		));

		if ($has_users > 0) {
			return false;
		}

		return $wpdb->delete($table, array('id' => $id), array('%d'));
	}

	public static function get_companies() {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_companies';

		return $wpdb->get_results("SELECT * FROM $table ORDER BY company_name ASC");
	}

	public static function get_company($id) {
		global $wpdb;

		$table = $wpdb->prefix . 'dokobit_companies';

		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d",
			$id
		));
	}

	public static function get_company_by_user_id($user_id) {
		global $wpdb;

		$user_phones_table = $wpdb->prefix . 'dokobit_user_phones';
		$companies_table = $wpdb->prefix . 'dokobit_companies';

		return $wpdb->get_row($wpdb->prepare(
			"SELECT c.* FROM $companies_table c
			   INNER JOIN $user_phones_table up ON c.id = up.company_id
			   WHERE up.user_id = %d
			   LIMIT 1",
			$user_id
		));
	}
}



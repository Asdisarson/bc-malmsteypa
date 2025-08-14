<?php
if (!defined('ABSPATH')) {
	exit;
}

class BCC_Dokobit {

	public static function init() {
		add_action('init', array(__CLASS__, 'init_shortcodes'));
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('wp_ajax_dokobit_check_auth_status', array(__CLASS__, 'check_auth_status'));
		add_action('wp_ajax_nopriv_dokobit_check_auth_status', array(__CLASS__, 'check_auth_status'));
		add_action('wp_ajax_dokobit_initiate_login', array(__CLASS__, 'initiate_login'));
		add_action('wp_ajax_nopriv_dokobit_initiate_login', array(__CLASS__, 'initiate_login'));
	}

	public static function init_shortcodes() {
		BCC_Dokobit_Shortcode::init();
	}

	public static function admin_menu() {
		BCC_Dokobit_Admin::add_admin_submenus();
	}

	public static function check_auth_status() {
		if (!isset($_POST['token']) || !isset($_POST['nonce'])) {
			wp_die();
		}

		if (!wp_verify_nonce($_POST['nonce'], 'dokobit_auth_nonce')) {
			wp_die();
		}

		$token = sanitize_text_field($_POST['token']);
		$api = new BCC_Dokobit_API();
		$status = $api->check_login_status($token);

		if ($status && isset($status['status']) && $status['status'] === 'ok') {
			$personal_code = isset($status['code']) ? $status['code'] : '';

			error_log('Dokobit returned personal code: ' . $personal_code);
			error_log('Dokobit full response: ' . json_encode($status));

			$user_data = BCC_Dokobit_Database::get_user_by_personal_code($personal_code);

			if ($user_data) {
				wp_set_current_user($user_data['user_id']);
				wp_set_auth_cookie($user_data['user_id']);

				set_transient('dokobit_user_company_' . $user_data['user_id'], $user_data['company_id'], 3600);

				wp_send_json_success(array(
					'status' => 'authenticated',
					'redirect' => home_url('/dokotest')
				));
			} else {
				wp_send_json_error(array(
					'message' => __('User not found. Please ensure your personal code is registered.', 'business-central-connector'),
					'debug' => array(
						'returned_personal_code' => $personal_code,
						'full_response' => $status
					)
				));
			}
		} elseif ($status && isset($status['status']) && $status['status'] === 'waiting') {
			wp_send_json_success(array(
				'status' => 'waiting'
			));
		} else {
			wp_send_json_error(array(
				'message' => __('Authentication failed or timed out.', 'business-central-connector')
			));
		}
	}

	public static function initiate_login() {
		if (!isset($_POST['phone']) || !isset($_POST['nonce'])) {
			wp_die();
		}

		if (!wp_verify_nonce($_POST['nonce'], 'dokobit_auth_nonce')) {
			wp_die();
		}

		$phone = sanitize_text_field($_POST['phone']);

		$user_data = BCC_Dokobit_Database::get_user_by_phone($phone);
		if (!$user_data) {
			wp_send_json_error(array(
				'message' => __('Phone number not registered.', 'business-central-connector')
			));
			return;
		}

		$api = new BCC_Dokobit_API();
		$response = $api->initiate_mobile_login($phone);

		if ($response && isset($response['token'])) {
			wp_send_json_success(array(
				'token' => $response['token'],
				'control_code' => isset($response['control_code']) ? $response['control_code'] : ''
			));
		} else {
			$error_message = __('Failed to initiate authentication.', 'business-central-connector');
			if (isset($response['error'])) {
				$error_message .= ' ' . $response['error'];
				if (isset($response['response'])) {
					error_log('Dokobit API Response: ' . $response['response']);
				}
			}
			wp_send_json_error(array(
				'message' => $error_message,
				'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? $response : null
			));
		}
	}
}



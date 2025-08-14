<?php
if (!defined('ABSPATH')) {
	exit;
}

class BCC_Dokobit_API {

	private $api_endpoint;
	private $api_key;

	public function __construct() {
		$settings = get_option('bcc_settings', array());
		$this->api_endpoint = isset($settings['dokobit_api_base']) && $settings['dokobit_api_base'] !== ''
			? rtrim($settings['dokobit_api_base'], '/')
			: 'https://developers.dokobit.com';
		$this->api_key = isset($settings['dokobit_api_key']) ? $settings['dokobit_api_key'] : '';
	}

	private function make_request($action, $params = array(), $method = 'POST') {
		if ($this->api_key === '') {
			return array('error' => 'Missing Dokobit API key');
		}

		$url = $this->api_endpoint . '/';

		if ($method === 'GET') {
			$params['access_token'] = $this->api_key;
			$url .= $action . '?' . http_build_query($params);
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		} else {
			$url .= $action . '?access_token=' . rawurlencode($this->api_key);
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			if (!empty($params)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
			}
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded'
		));

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		if ($response === false) {
			error_log('Dokobit API CURL Error: ' . $curl_error);
			return array('error' => 'CURL Error: ' . $curl_error);
		}

		if ($http_code >= 400) {
			error_log('Dokobit API HTTP Error: ' . $http_code . ' Response: ' . $response);
			return array('error' => 'HTTP Error: ' . $http_code, 'response' => $response);
		}

		$decoded = json_decode($response, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			error_log('Dokobit API JSON Error: ' . json_last_error_msg());
			return array('error' => 'JSON Error: ' . json_last_error_msg());
		}

		return $decoded;
	}

	public function initiate_mobile_login($phone) {
		$params = array(
			'phone' => $phone,
			'message' => sprintf(__('Login to %s', 'business-central-connector'), get_bloginfo('name'))
		);

		return $this->make_request('v2/mobile/login.json', $params);
	}

	public function check_login_status($token) {
		return $this->make_request('v2/mobile/login/status/' . $token . '.json', array(), 'GET');
	}

	public function get_certificate_info($token) {
		return $this->make_request('v2/mobile/certificate/' . $token . '.json', array(), 'GET');
	}
}



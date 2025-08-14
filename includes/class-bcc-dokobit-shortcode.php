<?php
if (!defined('ABSPATH')) {
	exit;
}

class BCC_Dokobit_Shortcode {

	public static function init() {
		add_shortcode('dokobit_login', array(__CLASS__, 'render_login_form'));
		add_shortcode('dokobit_company', array(__CLASS__, 'render_company_name'));

		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
	}

	public static function enqueue_scripts() {
		if (is_singular() && (has_shortcode(get_post()->post_content, 'dokobit_login') || has_shortcode(get_post()->post_content, 'dokobit_company'))) {
			wp_enqueue_script(
				'dokobit-auth',
				BCC_PLUGIN_URL . 'assets/js/dokobit-auth.js',
				array('jquery'),
				BCC_PLUGIN_VERSION,
				true
			);

			wp_localize_script('dokobit-auth', 'dokobit_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('dokobit_auth_nonce')
			));

			wp_enqueue_style(
				'dokobit-auth',
				BCC_PLUGIN_URL . 'assets/css/dokobit-auth.css',
				array(),
				BCC_PLUGIN_VERSION
			);
		}
	}

	public static function render_login_form($atts) {
		if (is_user_logged_in()) {
			return '<p>' . __('You are already logged in.', 'business-central-connector') . '</p>';
		}

		ob_start();
		?>
		<div class="dokobit-login-container">
			<form id="dokobit-login-form" class="dokobit-login-form">
				<h3><?php _e('Login with Phone', 'business-central-connector'); ?></h3>

				<div class="dokobit-form-group">
					<label for="dokobit-phone"><?php _e('Phone Number', 'business-central-connector'); ?></label>
					<input type="tel" id="dokobit-phone" name="phone" placeholder="+37060000000" required>
					<p class="dokobit-help-text"><?php _e('Enter your registered phone number with country code', 'business-central-connector'); ?></p>
				</div>

				<button type="submit" class="dokobit-submit-btn">
					<?php _e('Login', 'business-central-connector'); ?>
				</button>

				<div id="dokobit-message" class="dokobit-message" style="display: none;"></div>

				<div id="dokobit-auth-info" class="dokobit-auth-info" style="display: none;">
					<p><?php _e('Authentication request sent to your phone.', 'business-central-connector'); ?></p>
					<p><?php _e('Control code:', 'business-central-connector'); ?> <strong id="dokobit-control-code"></strong></p>
					<p><?php _e('Please confirm on your mobile device...', 'business-central-connector'); ?></p>
					<div class="dokobit-spinner"></div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_company_name($atts) {
		if (!is_user_logged_in()) {
			return '';
		}

		$user_id = get_current_user_id();

		$company_id = get_transient('dokobit_user_company_' . $user_id);

		if (!$company_id) {
			$company = BCC_Dokobit_Database::get_company_by_user_id($user_id);
			if ($company) {
				$company_id = $company->id;
				set_transient('dokobit_user_company_' . $user_id, $company_id, 3600);
			}
		} else {
			$company = BCC_Dokobit_Database::get_company($company_id);
		}

		if ($company) {
			return '<div class="dokobit-company-info">' .
				   '<h3>' . __('Your Company', 'business-central-connector') . '</h3>' .
				   '<p>' . esc_html($company->company_name) . '</p>' .
				   '</div>';
		}

		return '<p>' . __('No company information found.', 'business-central-connector') . '</p>';
	}
}



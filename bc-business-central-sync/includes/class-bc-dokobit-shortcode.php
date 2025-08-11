<?php

/**
 * Dokobit shortcode class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Dokobit_Shortcode {

	/**
	 * Initialize shortcodes.
	 */
	public static function init() {
		add_shortcode( 'bc_dokobit_login', array( __CLASS__, 'render_login_form' ) );
		add_shortcode( 'bc_dokobit_company', array( __CLASS__, 'render_company_name' ) );
		
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public static function enqueue_scripts() {
		if ( is_singular() && (
			has_shortcode( get_post()->post_content, 'bc_dokobit_login' ) ||
			has_shortcode( get_post()->post_content, 'bc_dokobit_company' )
		) ) {
			wp_enqueue_script(
				'bc-dokobit-auth',
				plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/bc-dokobit-auth.js',
				array( 'jquery' ),
				'1.0.0',
				true
			);
			
			wp_localize_script( 'bc-dokobit-auth', 'bc_dokobit_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'bc_dokobit_auth_nonce' )
			) );
			
			wp_enqueue_style(
				'bc-dokobit-auth',
				plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/bc-dokobit-auth.css',
				array(),
				'1.0.0'
			);
		}
	}

	/**
	 * Render Dokobit login form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_login_form( $atts ) {
		if ( is_user_logged_in() ) {
			return '<p>' . __( 'You are already logged in.', 'bc-business-central-sync' ) . '</p>';
		}
		
		ob_start();
		?>
		<div class="bc-dokobit-login-container">
			<form id="bc-dokobit-login-form" class="bc-dokobit-login-form">
				<h3><?php _e( 'Login with Phone', 'bc-business-central-sync' ); ?></h3>
				
				<div class="bc-dokobit-form-group">
					<label for="bc-dokobit-phone"><?php _e( 'Phone Number', 'bc-business-central-sync' ); ?></label>
					<input type="tel" id="bc-dokobit-phone" name="phone" placeholder="+37060000000" required>
					<p class="bc-dokobit-help-text"><?php _e( 'Enter your registered phone number with country code', 'bc-business-central-sync' ); ?></p>
				</div>
				
				<button type="submit" class="bc-dokobit-submit-btn">
					<?php _e( 'Login', 'bc-business-central-sync' ); ?>
				</button>
				
				<div id="bc-dokobit-message" class="bc-dokobit-message" style="display: none;"></div>
				
				<div id="bc-dokobit-auth-info" class="bc-dokobit-auth-info" style="display: none;">
					<p><?php _e( 'Authentication request sent to your phone.', 'bc-business-central-sync' ); ?></p>
					<p><?php _e( 'Control code:', 'bc-business-central-sync' ); ?> <strong id="bc-dokobit-control-code"></strong></p>
					<p><?php _e( 'Please confirm on your mobile device...', 'bc-business-central-sync' ); ?></p>
					<div class="bc-dokobit-spinner"></div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render company name.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_company_name( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		
		$user_id = get_current_user_id();
		
		$company_id = get_transient( 'bc_dokobit_user_company_' . $user_id );
		
		if ( ! $company_id ) {
			$company = BC_Dokobit_Database::get_company_by_user_id( $user_id );
			if ( $company ) {
				$company_id = $company->id;
				set_transient( 'bc_dokobit_user_company_' . $user_id, $company_id, 3600 );
			}
		} else {
			$company = BC_Dokobit_Database::get_company( $company_id );
		}
		
		if ( $company ) {
			return '<div class="bc-dokobit-company-info">' . 
				   '<h3>' . __( 'Your Company', 'bc-business-central-sync' ) . '</h3>' .
				   '<p>' . esc_html( $company->company_name ) . '</p>' .
				   '</div>';
		}
		
		return '<p>' . __( 'No company information found.', 'bc-business-central-sync' ) . '</p>';
	}

}

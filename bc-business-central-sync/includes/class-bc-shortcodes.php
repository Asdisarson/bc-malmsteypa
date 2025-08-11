<?php

/**
 * Shortcode class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Shortcodes {

	/**
	 * Initialize shortcodes.
	 */
	public static function init() {
		add_shortcode( 'bc_login_form', array( __CLASS__, 'render_login_form' ) );
		add_shortcode( 'bc_customer_info', array( __CLASS__, 'render_customer_info' ) );
		add_shortcode( 'bc_company_pricing', array( __CLASS__, 'render_company_pricing' ) );
		
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public static function enqueue_scripts() {
		if ( is_singular() && (
			has_shortcode( get_post()->post_content, 'bc_login_form' ) ||
			has_shortcode( get_post()->post_content, 'bc_customer_info' ) ||
			has_shortcode( get_post()->post_content, 'bc_company_pricing' )
		) ) {
			wp_enqueue_style(
				'bc-shortcodes',
				plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/bc-shortcodes.css',
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
		$atts = shortcode_atts( array(
			'title' => __( 'Login Required', 'bc-business-central-sync' ),
			'description' => __( 'You must be authenticated to view prices and purchase products.', 'bc-business-central-sync' ),
		), $atts );

		if ( is_user_logged_in() ) {
			$customer_pricing = new BC_Customer_Pricing();
			if ( $customer_pricing->is_user_authenticated() ) {
				return '<div class="bc-login-success">' .
					   '<h3>' . __( '✅ Authentication Successful', 'bc-business-central-sync' ) . '</h3>' .
					   '<p>' . __( 'You are now logged in and can view prices and purchase products.', 'bc-business-central-sync' ) . '</p>' .
					   '</div>';
			} else {
				return '<div class="bc-login-error">' .
					   '<h3>' . __( '⚠️ Company Assignment Required', 'bc-business-central-sync' ) . '</h3>' .
					   '<p>' . __( 'You are logged in but not assigned to a company. Please contact your administrator.', 'bc-business-central-sync' ) . '</p>' .
					   '</div>';
			}
		}

		ob_start();
		?>
		<div class="bc-login-container">
			<div class="bc-login-header">
				<h3><?php echo esc_html( $atts['title'] ); ?></h3>
				<p><?php echo esc_html( $atts['description'] ); ?></p>
			</div>
			
			<div class="bc-login-options">
				<div class="bc-login-option">
					<h4><?php _e( 'Option 1: Dokobit Phone Authentication', 'bc-business-central-sync' ); ?></h4>
					<p><?php _e( 'Use your existing Dokobit phone authentication to login securely.', 'bc-business-central-sync' ); ?></p>
					<?php echo do_shortcode( '[bc_dokobit_login]' ); ?>
				</div>
				
				<div class="bc-login-option">
					<h4><?php _e( 'Option 2: Standard WordPress Login', 'bc-business-central-sync' ); ?></h4>
					<p><?php _e( 'If you have a WordPress account, you can login here.', 'bc-business-central-sync' ); ?></p>
					<?php
					if ( function_exists( 'wp_login_form' ) ) {
						wp_login_form( array(
							'redirect' => get_permalink(),
							'label_username' => __( 'Username or Email', 'bc-business-central-sync' ),
							'label_password' => __( 'Password', 'bc-business-central-sync' ),
							'label_remember' => __( 'Remember Me', 'bc-business-central-sync' ),
							'label_log_in' => __( 'Log In', 'bc-business-central-sync' ),
						) );
					}
					?>
				</div>
			</div>
			
			<div class="bc-login-help">
				<h4><?php _e( 'Need Help?', 'bc-business-central-sync' ); ?></h4>
				<p><?php _e( 'If you are having trouble logging in, please contact your company administrator or support team.', 'bc-business-central-sync' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render customer information.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_customer_info( $atts ) {
		$atts = shortcode_atts( array(
			'show_company' => 'true',
			'show_customer_number' => 'true',
			'show_pricing_info' => 'true',
		), $atts );

		if ( ! is_user_logged_in() ) {
			return '<div class="bc-customer-info-login-required">' .
				   '<p>' . __( 'Please login to view your customer information.', 'bc-business-central-sync' ) . '</p>' .
				   do_shortcode( '[bc_login_form]' ) .
				   '</div>';
		}

		$customer_pricing = new BC_Customer_Pricing();
		
		if ( ! $customer_pricing->is_user_authenticated() ) {
			return '<div class="bc-customer-info-error">' .
				   '<p>' . __( 'You are logged in but not assigned to a company. Please contact your administrator.', 'bc-business-central-sync' ) . '</p>' .
				   '</div>';
		}

		$company = $customer_pricing->get_user_company();
		$customer_number = get_user_meta( get_current_user_id(), '_bc_customer_number', true );

		ob_start();
		?>
		<div class="bc-customer-info">
			<h3><?php _e( 'Your Customer Information', 'bc-business-central-sync' ); ?></h3>
			
			<?php if ( $atts['show_company'] === 'true' && $company ) : ?>
				<div class="bc-info-section">
					<h4><?php _e( 'Company', 'bc-business-central-sync' ); ?></h4>
					<p><?php echo esc_html( $company->company_name ); ?></p>
				</div>
			<?php endif; ?>
			
			<?php if ( $atts['show_customer_number'] === 'true' ) : ?>
				<div class="bc-info-section">
					<h4><?php _e( 'Customer Number', 'bc-business-central-sync' ); ?></h4>
					<?php if ( $customer_number ) : ?>
						<p><?php echo esc_html( $customer_number ); ?></p>
					<?php else : ?>
						<p class="bc-no-customer-number">
							<?php _e( 'No customer number set.', 'bc-business-central-sync' ); ?>
							<a href="<?php echo esc_url( get_edit_profile_url() ); ?>"><?php _e( 'Set customer number', 'bc-business-central-sync' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			
			<?php if ( $atts['show_pricing_info'] === 'true' && $customer_number ) : ?>
				<div class="bc-info-section">
					<h4><?php _e( 'Pricing Information', 'bc-business-central-sync' ); ?></h4>
					<p><?php _e( 'You have access to company-specific pricing. Prices will be displayed on product pages.', 'bc-business-central-sync' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render company pricing information.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_company_pricing( $atts ) {
		$atts = shortcode_atts( array(
			'product_id' => 0,
			'show_login_form' => 'true',
		), $atts );

		if ( ! is_user_logged_in() ) {
			if ( $atts['show_login_form'] === 'true' ) {
				return '<div class="bc-company-pricing-login-required">' .
					   '<p>' . __( 'Please login to view company pricing.', 'bc-business-central-sync' ) . '</p>' .
					   do_shortcode( '[bc_login_form]' ) .
					   '</div>';
			} else {
				return '<div class="bc-company-pricing-login-required">' .
					   '<p>' . __( 'Please login to view company pricing.', 'bc-business-central-sync' ) . '</p>' .
					   '</div>';
			}
		}

		$customer_pricing = new BC_Customer_Pricing();
		
		if ( ! $customer_pricing->is_user_authenticated() ) {
			return '<div class="bc-company-pricing-error">' .
				   '<p>' . __( 'You are logged in but not assigned to a company. Please contact your administrator.', 'bc-business-central-sync' ) . '</p>' .
				   '</div>';
		}

		$product_id = $atts['product_id'] ?: get_the_ID();
		
		if ( ! $product_id ) {
			return '<div class="bc-company-pricing-error">' .
				   '<p>' . __( 'No product specified.', 'bc-business-central-sync' ) . '</p>' .
				   '</div>';
		}

		ob_start();
		?>
		<div class="bc-company-pricing">
			<?php $customer_pricing->display_customer_pricing( $product_id ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

}

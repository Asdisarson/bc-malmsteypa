<?php

/**
 * Customer pricing utility class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Customer_Pricing {

	/**
	 * Initialize customer pricing functionality.
	 */
	public function __construct() {
		// Hook into WooCommerce to modify product prices for customers
		add_filter( 'woocommerce_product_get_price', array( $this, 'get_customer_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'get_customer_regular_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'get_customer_sale_price' ), 10, 2 );
		
		// Hide prices for non-authenticated users
		add_filter( 'woocommerce_get_price_html', array( $this, 'hide_prices_for_guests' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'hide_cart_prices_for_guests' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'hide_cart_subtotal_for_guests' ), 10, 3 );
		
		// Disable purchasing for non-authenticated users
		add_filter( 'woocommerce_is_purchasable', array( $this, 'disable_purchasing_for_guests' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'prevent_add_to_cart_for_guests' ), 10, 5 );
		
		// Add customer number field to checkout (only for authenticated users)
		add_action( 'woocommerce_checkout_fields', array( $this, 'add_customer_number_field' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_customer_number' ) );
		
		// Add customer number field to user profile
		add_action( 'show_user_profile', array( $this, 'add_customer_number_profile_field' ) );
		add_action( 'edit_user_profile', array( $this, 'add_customer_number_profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_customer_number_profile_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_customer_number_profile_field' ) );
		
		// Add login required notice
		add_action( 'woocommerce_before_single_product', array( $this, 'show_login_required_notice' ) );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'show_login_required_notice' ) );
	}

	/**
	 * Check if user is authenticated via Dokobit.
	 *
	 * @return bool
	 */
	public function is_user_authenticated() {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}
		
		$user_id = get_current_user_id();
		
		// Check if user has a company assignment via Dokobit
		$company_id = get_transient( 'bc_dokobit_user_company_' . $user_id );
		
		if ( ! $company_id ) {
			// Try to get from database
			if ( class_exists( 'BC_Dokobit_Database' ) ) {
				$company = BC_Dokobit_Database::get_company_by_user_id( $user_id );
				if ( $company ) {
					$company_id = $company->id;
					set_transient( 'bc_dokobit_user_company_' . $user_id, $company_id, 3600 );
				}
			}
		}
		
		return ! empty( $company_id );
	}

	/**
	 * Get customer price for a product.
	 *
	 * @param float  $price Product price.
	 * @param object $product WooCommerce product object.
	 * @return float
	 */
	public function get_customer_price( $price, $product ) {
		// Only show prices for authenticated users
		if ( ! $this->is_user_authenticated() ) {
			return 0;
		}
		
		$customer_number = $this->get_current_customer_number();
		
		if ( ! $customer_number ) {
			return $price;
		}
		
		$pricelist_manager = new BC_Pricelist_Manager();
		$customer_price = $pricelist_manager->get_customer_product_price( $product->get_id(), $customer_number );
		
		if ( $customer_price !== false ) {
			return $customer_price;
		}
		
		return $price;
	}

	/**
	 * Get customer regular price for a product.
	 *
	 * @param float  $price Product regular price.
	 * @param object $product WooCommerce product object.
	 * @return float
	 */
	public function get_customer_regular_price( $price, $product ) {
		return $this->get_customer_price( $price, $product );
	}

	/**
	 * Get customer sale price for a product.
	 *
	 * @param float  $price Product sale price.
	 * @param object $product WooCommerce product object.
	 * @return float
	 */
	public function get_customer_sale_price( $price, $product ) {
		return $this->get_customer_price( $price, $product );
	}

	/**
	 * Hide prices for non-authenticated users.
	 *
	 * @param string $price_html Price HTML.
	 * @param object $product Product object.
	 * @return string
	 */
	public function hide_prices_for_guests( $price_html, $product ) {
		if ( ! $this->is_user_authenticated() ) {
			return '<span class="price-login-required">' . __( 'Login required to see prices', 'bc-business-central-sync' ) . '</span>';
		}
		
		return $price_html;
	}

	/**
	 * Hide cart item prices for non-authenticated users.
	 *
	 * @param string $price_html Price HTML.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function hide_cart_prices_for_guests( $price_html, $cart_item, $cart_item_key ) {
		if ( ! $this->is_user_authenticated() ) {
			return '<span class="price-login-required">' . __( 'Login required', 'bc-business-central-sync' ) . '</span>';
		}
		
		return $price_html;
	}

	/**
	 * Hide cart subtotals for non-authenticated users.
	 *
	 * @param string $subtotal_html Subtotal HTML.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function hide_cart_subtotal_for_guests( $subtotal_html, $cart_item, $cart_item_key ) {
		if ( ! $this->is_user_authenticated() ) {
			return '<span class="price-login-required">' . __( 'Login required', 'bc-business-central-sync' ) . '</span>';
		}
		
		return $subtotal_html;
	}

	/**
	 * Disable purchasing for non-authenticated users.
	 *
	 * @param bool   $is_purchasable Whether product is purchasable.
	 * @param object $product Product object.
	 * @return bool
	 */
	public function disable_purchasing_for_guests( $is_purchasable, $product ) {
		if ( ! $this->is_user_authenticated() ) {
			return false;
		}
		
		return $is_purchasable;
	}

	/**
	 * Prevent adding to cart for non-authenticated users.
	 *
	 * @param bool   $passed Whether validation passed.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variations Variations.
	 * @return bool
	 */
	public function prevent_add_to_cart_for_guests( $passed, $product_id, $quantity, $variation_id, $variations ) {
		if ( ! $this->is_user_authenticated() ) {
			wc_add_notice( __( 'You must be logged in to purchase products. Please use the Dokobit phone authentication to login.', 'bc-business-central-sync' ), 'error' );
			return false;
		}
		
		return $passed;
	}

	/**
	 * Show login required notice.
	 */
	public function show_login_required_notice() {
		if ( ! $this->is_user_authenticated() ) {
			echo '<div class="woocommerce-info bc-login-required-notice">';
			echo '<p>' . __( '🔒 <strong>Login Required</strong> - You must be authenticated via Dokobit phone authentication to view prices and purchase products.', 'bc-business-central-sync' ) . '</p>';
			echo '<p>' . __( 'Use the shortcode [dokobit_login] to display the login form.', 'bc-business-central-sync' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Get current customer number from user meta.
	 *
	 * @return string|false
	 */
	private function get_current_customer_number() {
		// Only for authenticated users
		if ( ! $this->is_user_authenticated() ) {
			return false;
		}
		
		// Check if user is logged in
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$customer_number = get_user_meta( $user_id, '_bc_customer_number', true );
			
			if ( $customer_number ) {
				return $customer_number;
			}
		}
		
		return false;
	}

	/**
	 * Add customer number field to checkout (only for authenticated users).
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function add_customer_number_field( $fields ) {
		// Only show for authenticated users
		if ( ! $this->is_user_authenticated() ) {
			return $fields;
		}
		
		$fields['billing']['bc_customer_number'] = array(
			'label'       => __( 'Customer Number', 'bc-business-central-sync' ),
			'placeholder' => __( 'Enter your Business Central customer number', 'bc-business-central-sync' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'clear'       => true,
			'priority'    => 25,
		);
		
		return $fields;
	}

	/**
	 * Save customer number from checkout.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_customer_number( $order_id ) {
		if ( ! empty( $_POST['bc_customer_number'] ) ) {
			$customer_number = sanitize_text_field( $_POST['bc_customer_number'] );
			
			// Use HPOS-compatible method to save order meta
			if ( class_exists( 'BC_HPOS_Utils' ) ) {
				BC_HPOS_Utils::update_order_meta( $order_id, '_bc_customer_number', $customer_number );
			} else {
				// Fallback to traditional method
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->update_meta_data( '_bc_customer_number', $customer_number );
					$order->save();
				}
			}
			
			// Also save to user meta if user is logged in
			$order = wc_get_order( $order_id );
			if ( $order && $order->get_customer_id() ) {
				update_user_meta( $order->get_customer_id(), '_bc_customer_number', $customer_number );
			}
		}
	}

	/**
	 * Add customer number field to user profile.
	 *
	 * @param WP_User $user User object.
	 */
	public function add_customer_number_profile_field( $user ) {
		// Only show for authenticated users
		if ( ! $this->is_user_authenticated() ) {
			return;
		}
		
		?>
		<h3><?php _e( 'Business Central Customer Information', 'bc-business-central-sync' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="bc_customer_number"><?php _e( 'Customer Number', 'bc-business-central-sync' ); ?></label></th>
				<td>
					<input type="text" name="bc_customer_number" id="bc_customer_number" 
						   value="<?php echo esc_attr( get_user_meta( $user->ID, '_bc_customer_number', true ) ); ?>" 
						   class="regular-text" />
					<p class="description"><?php _e( 'Your Business Central customer number for special pricing.', 'bc-business-central-sync' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save customer number from user profile.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_customer_number_profile_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}
		
		if ( isset( $_POST['bc_customer_number'] ) ) {
			$customer_number = sanitize_text_field( $_POST['bc_customer_number'] );
			update_user_meta( $user_id, '_bc_customer_number', $customer_number );
		}
	}

	/**
	 * Get customer pricing information for display.
	 *
	 * @param int $product_id Product ID.
	 * @return array|false
	 */
	public function get_customer_pricing_info( $product_id ) {
		// Only for authenticated users
		if ( ! $this->is_user_authenticated() ) {
			return false;
		}
		
		$customer_number = $this->get_current_customer_number();
		
		if ( ! $customer_number ) {
			return false;
		}
		
		$pricelist_manager = new BC_Pricelist_Manager();
		$customer_data = $pricelist_manager->get_customer_pricelist( $customer_number );
		
		if ( ! $customer_data ) {
			return false;
		}
		
		$customer_price = $pricelist_manager->get_customer_product_price( $product_id, $customer_number );
		
		if ( $customer_price === false ) {
			return false;
		}
		
		return array(
			'customer_number' => $customer_number,
			'customer_name' => $customer_data['customer']->customer_name,
			'pricelist_name' => $customer_data['pricelist']->name,
			'customer_price' => $customer_price,
			'currency' => $customer_data['pricelist']->currency_code
		);
	}

	/**
	 * Display customer pricing information on product page.
	 *
	 * @param int $product_id Product ID.
	 */
	public function display_customer_pricing( $product_id ) {
		// Only for authenticated users
		if ( ! $this->is_user_authenticated() ) {
			return;
		}
		
		$pricing_info = $this->get_customer_pricing_info( $product_id );
		
		if ( ! $pricing_info ) {
			return;
		}
		
		$currency_symbol = get_woocommerce_currency_symbol( $pricing_info['currency'] );
		
		echo '<div class="bc-customer-pricing">';
		echo '<h4>' . __( 'Your Company Pricing', 'bc-business-central-sync' ) . '</h4>';
		echo '<p><strong>' . __( 'Customer:', 'bc-business-central-sync' ) . '</strong> ' . esc_html( $pricing_info['customer_name'] ) . '</p>';
		echo '<p><strong>' . __( 'Pricelist:', 'bc-business-central-sync' ) . '</strong> ' . esc_html( $pricing_info['pricelist_name'] ) . '</p>';
		echo '<p><strong>' . __( 'Your Price:', 'bc-business-central-sync' ) . '</strong> <span class="price">' . $currency_symbol . number_format( $pricing_info['customer_price'], 2 ) . '</span></p>';
		echo '</div>';
	}

	/**
	 * Get user's company information from Dokobit.
	 *
	 * @return object|false
	 */
	public function get_user_company() {
		if ( ! $this->is_user_authenticated() ) {
			return false;
		}
		
		$user_id = get_current_user_id();
		$company_id = get_transient( 'bc_dokobit_user_company_' . $user_id );
		
		if ( ! $company_id && class_exists( 'BC_Dokobit_Database' ) ) {
			$company = BC_Dokobit_Database::get_company_by_user_id( $user_id );
			if ( $company ) {
				$company_id = $company->id;
				set_transient( 'bc_dokobit_user_company_' . $user_id, $company_id, 3600 );
			}
		}
		
		if ( $company_id && class_exists( 'BC_Dokobit_Database' ) ) {
			return BC_Dokobit_Database::get_company( $company_id );
		}
		
		return false;
	}

}

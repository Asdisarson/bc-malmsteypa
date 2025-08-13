<?php

/**
 * Simple Pricing System for Business Central Sync
 * 
 * This class provides a lightweight way to adjust product prices on the fly
 * without dealing with orders or complex HPOS compatibility.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_Simple_Pricing {

	/**
	 * Initialize the simple pricing system.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Enqueue styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// Filter product prices on the fly
		add_filter( 'woocommerce_product_get_price', array( $this, 'adjust_product_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'adjust_product_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'adjust_product_price' ), 10, 2 );
		
		// Filter variation prices
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'adjust_variation_price' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'adjust_variation_price' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'adjust_variation_price' ), 10, 3 );
		
		// Filter cart and checkout prices
		add_filter( 'woocommerce_cart_item_price', array( $this, 'adjust_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'adjust_cart_item_subtotal' ), 10, 3 );
		
		// Add customer pricing display
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_customer_pricing_info' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_customer_pricing_info' ), 15 );
		
		// Add customer selector to product pages
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_customer_selector' ) );
		
		// Handle customer selection via AJAX
		add_action( 'wp_ajax_bc_set_customer', array( $this, 'set_customer_via_ajax' ) );
		add_action( 'wp_ajax_nopriv_bc_set_customer', array( $this, 'set_customer_via_ajax' ) );
	}

	/**
	 * Enqueue styles and scripts for the simple pricing system.
	 */
	public function enqueue_assets() {
		// Only enqueue on WooCommerce pages
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'bc-simple-pricing',
			plugin_dir_url( __FILE__ ) . '../../public/css/bc-simple-pricing.css',
			array(),
			'1.0.0'
		);
	}

	/**
	 * Adjust product price based on customer's company pricelist.
	 *
	 * @param mixed  $price Product price.
	 * @param object $product Product object.
	 * @return mixed Adjusted price.
	 */
	public function adjust_product_price( $price, $product ) {
		if ( ! $price || ! $product ) {
			return $price;
		}

		$customer_price = $this->get_customer_product_price( $product->get_id() );
		
		if ( $customer_price !== false ) {
			return $customer_price;
		}

		return $price;
	}

	/**
	 * Adjust variation price based on customer's company pricelist.
	 *
	 * @param mixed  $price Variation price.
	 * @param object $variation Variation object.
	 * @param object $product Product object.
	 * @return mixed Adjusted price.
	 */
	public function adjust_variation_price( $price, $variation, $product ) {
		if ( ! $price || ! $variation ) {
			return $price;
		}

		$customer_price = $this->get_customer_product_price( $variation->get_id() );
		
		if ( $customer_price !== false ) {
			return $customer_price;
		}

		return $price;
	}

	/**
	 * Adjust cart item price display.
	 *
	 * @param string $price_formatted Formatted price.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string Adjusted price.
	 */
	public function adjust_cart_item_price( $price_formatted, $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];
		$variation_id = $cart_item['variation_id'];
		
		$actual_product_id = $variation_id ? $variation_id : $product_id;
		$customer_price = $this->get_customer_product_price( $actual_product_id );
		
		if ( $customer_price !== false ) {
			return wc_price( $customer_price );
		}

		return $price_formatted;
	}

	/**
	 * Adjust cart item subtotal display.
	 *
	 * @param string $subtotal_formatted Formatted subtotal.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string Adjusted subtotal.
	 */
	public function adjust_cart_item_subtotal( $subtotal_formatted, $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];
		$variation_id = $cart_item['variation_id'];
		$quantity = $cart_item['quantity'];
		
		$actual_product_id = $variation_id ? $variation_id : $product_id;
		$customer_price = $this->get_customer_product_price( $actual_product_id );
		
		if ( $customer_price !== false ) {
			return wc_price( $customer_price * $quantity );
		}

		return $subtotal_formatted;
	}

	/**
	 * Get customer-specific product price.
	 *
	 * @param int $product_id Product ID.
	 * @return mixed Customer price or false if not available.
	 */
	private function get_customer_product_price( $product_id ) {
		// Get current customer from session
		$customer_number = $this->get_current_customer_number();
		
		if ( ! $customer_number ) {
			return false;
		}

		// Get Business Central product number
		$bc_product_number = get_post_meta( $product_id, '_bc_product_number', true );
		
		if ( ! $bc_product_number ) {
			return false;
		}

		// Get customer price from pricelist
		global $wpdb;
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		
		$price = $wpdb->get_var( $wpdb->prepare(
			"SELECT unit_price FROM $table_name 
			WHERE item_no = %s AND customer_pricing_group_code = %s",
			$bc_product_number,
			$customer_number
		) );

		return $price !== null ? (float) $price : false;
	}

	/**
	 * Get current customer number from session.
	 *
	 * @return string|false Customer number or false if not set.
	 */
	private function get_current_customer_number() {
		// Check session first
		if ( isset( $_SESSION['bc_customer_number'] ) ) {
			return sanitize_text_field( $_SESSION['bc_customer_number'] );
		}

		// Check cookie
		if ( isset( $_COOKIE['bc_customer_number'] ) ) {
			return sanitize_text_field( $_COOKIE['bc_customer_number'] );
		}

		// Check if user is logged in and has customer number
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$customer_number = get_user_meta( $user_id, '_bc_customer_number', true );
			
			if ( $customer_number ) {
				// Store in session for future use
				if ( ! session_id() ) {
					session_start();
				}
				$_SESSION['bc_customer_number'] = $customer_number;
				
				return $customer_number;
			}
		}

		return false;
	}

	/**
	 * Display customer pricing information on product pages.
	 *
	 * @param object $product Product object.
	 */
	public function display_customer_pricing_info( $product ) {
		$customer_number = $this->get_current_customer_number();
		
		if ( ! $customer_number ) {
			return;
		}

		$customer_price = $this->get_customer_product_price( $product->get_id() );
		
		if ( $customer_price !== false ) {
			$regular_price = $product->get_regular_price();
			
			if ( $regular_price && $customer_price < $regular_price ) {
				$savings = $regular_price - $customer_price;
				$savings_percentage = round( ( $savings / $regular_price ) * 100, 1 );
				
				echo '<div class="bc-customer-pricing-info">';
				echo '<p class="bc-customer-price">';
				echo '<strong>' . __( 'Your Price:', 'bc-business-central-sync' ) . '</strong> ';
				echo '<span class="price">' . wc_price( $customer_price ) . '</span>';
				echo '</p>';
				echo '<p class="bc-customer-savings">';
				echo '<strong>' . __( 'You Save:', 'bc-business-central-sync' ) . '</strong> ';
				echo wc_price( $savings ) . ' (' . $savings_percentage . '%)';
				echo '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Add customer selector to product pages.
	 *
	 * @param object $product Product object.
	 */
	public function add_customer_selector( $product ) {
		// Get available companies
		global $wpdb;
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		$companies = $wpdb->get_results(
			"SELECT id, name, customer_number FROM $table_name ORDER BY name ASC"
		);

		if ( empty( $companies ) ) {
			return;
		}

		$current_customer = $this->get_current_customer_number();
		
		echo '<div class="bc-customer-selector">';
		echo '<label for="bc_customer_select">' . __( 'Select Your Company:', 'bc-business-central-sync' ) . '</label>';
		echo '<select id="bc_customer_select" name="bc_customer_select">';
		echo '<option value="">' . __( '-- Select Company --', 'bc-business-central-sync' ) . '</option>';
		
		foreach ( $companies as $company ) {
			$selected = ( $company->customer_number === $current_customer ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $company->customer_number ) . '" ' . $selected . '>';
			echo esc_html( $company->name );
			echo '</option>';
		}
		
		echo '</select>';
		echo '<button type="button" id="bc_update_customer" class="button">' . __( 'Update Prices', 'bc-business-central-sync' ) . '</button>';
		echo '</div>';
		
		// Add JavaScript for AJAX handling
		$this->add_customer_selector_script();
	}

	/**
	 * Add JavaScript for customer selector functionality.
	 */
	private function add_customer_selector_script() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#bc_update_customer').on('click', function() {
				var customerNumber = $('#bc_customer_select').val();
				
				if (!customerNumber) {
					alert('<?php _e( 'Please select a company.', 'bc-business-central-sync' ); ?>');
					return;
				}
				
				$.ajax({
					url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
					type: 'POST',
					data: {
						action: 'bc_set_customer',
						customer_number: customerNumber,
						nonce: '<?php echo wp_create_nonce( 'bc_set_customer_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							// Reload page to show updated prices
							location.reload();
						} else {
							alert('<?php _e( 'Error updating customer selection.', 'bc-business-central-sync' ); ?>');
						}
					},
					error: function() {
						alert('<?php _e( 'Error updating customer selection.', 'bc-business-central-sync' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle customer selection via AJAX.
	 */
	public function set_customer_via_ajax() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'bc_set_customer_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$customer_number = sanitize_text_field( $_POST['customer_number'] );
		
		if ( empty( $customer_number ) ) {
			wp_send_json_error( 'Customer number is required' );
		}

		// Store in session
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION['bc_customer_number'] = $customer_number;
		
		// Store in cookie for persistence
		setcookie( 'bc_customer_number', $customer_number, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
		
		// If user is logged in, save to user meta
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			update_user_meta( $user_id, '_bc_customer_number', $customer_number );
		}

		wp_send_json_success( 'Customer updated successfully' );
	}

	/**
	 * Get all available companies for admin use.
	 *
	 * @return array Array of companies.
	 */
	public function get_available_companies() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bc_dokobit_companies';
		
		return $wpdb->get_results(
			"SELECT id, name, customer_number, created_at FROM $table_name ORDER BY name ASC"
		);
	}

	/**
	 * Get customer pricing statistics.
	 *
	 * @return array Pricing statistics.
	 */
	public function get_pricing_stats() {
		global $wpdb;
		$pricelist_lines_table = $wpdb->prefix . 'bc_pricelist_lines';
		$companies_table = $wpdb->prefix . 'bc_dokobit_companies';
		
		$stats = array(
			'total_companies' => 0,
			'total_products' => 0,
			'average_discount' => 0,
			'total_savings' => 0
		);
		
		// Get company count
		$stats['total_companies'] = $wpdb->get_var( "SELECT COUNT(*) FROM $companies_table" );
		
		// Get product count
		$stats['total_products'] = $wpdb->get_var( "SELECT COUNT(DISTINCT item_no) FROM $pricelist_lines_table" );
		
		// Get pricing statistics
		$pricing_data = $wpdb->get_results(
			"SELECT unit_price, standard_price FROM $pricelist_lines_table 
			WHERE standard_price > 0 AND unit_price > 0"
		);
		
		if ( ! empty( $pricing_data ) ) {
			$total_savings = 0;
			$total_discounts = 0;
			
			foreach ( $pricing_data as $price ) {
				if ( $price->unit_price < $price->standard_price ) {
					$savings = $price->standard_price - $price->unit_price;
					$discount = ( $savings / $price->standard_price ) * 100;
					
					$total_savings += $savings;
					$total_discounts += $discount;
				}
			}
			
			$stats['total_savings'] = $total_savings;
			$stats['average_discount'] = count( $pricing_data ) > 0 ? $total_discounts / count( $pricing_data ) : 0;
		}
		
		return $stats;
	}
}

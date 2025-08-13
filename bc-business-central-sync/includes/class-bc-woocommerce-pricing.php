<?php
/**
 * WooCommerce pricing integration class for Business Central Sync.
 *
 * @since      1.0.0
 * @package    BC_Business_Central_Sync
 * @subpackage BC_Business_Central_Sync/includes
 */
class BC_WooCommerce_Pricing {

	/**
	 * Initialize the pricing integration.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Filter product prices based on customer's company pricelist
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_product_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'filter_product_price' ), 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'filter_product_price' ), 10, 2 );
		
		// Filter variation prices
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'filter_variation_price' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'filter_variation_price' ), 10, 3 );
		
		// Filter cart item prices
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'filter_cart_item_subtotal' ), 10, 3 );
		
		// Filter order item prices
		add_filter( 'woocommerce_order_item_get_total', array( $this, 'filter_order_item_total' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_subtotal', array( $this, 'filter_order_item_subtotal' ), 10, 2 );
		
		// Add customer pricing information to product display
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_customer_pricing_info' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_customer_pricing_info' ), 15 );
	}

	/**
	 * Filter product price based on customer's company pricelist.
	 *
	 * @param mixed  $price Product price.
	 * @param object $product Product object.
	 * @return mixed
	 */
	public function filter_product_price( $price, $product ) {
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
	 * Filter variation price based on customer's company pricelist.
	 *
	 * @param mixed  $price Variation price.
	 * @param object $variation Variation object.
	 * @param object $product Product object.
	 * @return mixed
	 */
	public function filter_variation_price( $price, $variation, $product ) {
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
	 * Filter cart item price display.
	 *
	 * @param string $price_formatted Formatted price.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_price( $price_formatted, $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];
		$variation_id = $cart_item['variation_id'];
		
		$actual_product_id = $variation_id ? $variation_id : $product_id;
		$customer_price = $this->get_customer_product_price( $actual_product_id );
		
		if ( $customer_price !== false ) {
			$original_price = $cart_item['data']->get_price();
			if ( $customer_price != $original_price ) {
				$price_formatted = '<del>' . wc_price( $original_price ) . '</del> <ins>' . wc_price( $customer_price ) . '</ins>';
			}
		}

		return $price_formatted;
	}

	/**
	 * Filter cart item subtotal display.
	 *
	 * @param string $subtotal_formatted Formatted subtotal.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_subtotal( $subtotal_formatted, $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];
		$variation_id = $cart_item['variation_id'];
		
		$actual_product_id = $variation_id ? $variation_id : $product_id;
		$customer_price = $this->get_customer_product_price( $actual_product_id );
		
		if ( $customer_price !== false ) {
			$quantity = $cart_item['quantity'];
			$subtotal = $customer_price * $quantity;
			$subtotal_formatted = wc_price( $subtotal );
		}

		return $subtotal_formatted;
	}

	/**
	 * Filter order item total.
	 *
	 * @param mixed $total Item total.
	 * @param object $item Order item object.
	 * @return mixed
	 */
	public function filter_order_item_total( $total, $item ) {
		$product_id = $item->get_product_id();
		$variation_id = $item->get_variation_id();
		
		$actual_product_id = $variation_id ? $variation_id : $product_id;
		$customer_price = $this->get_customer_product_price( $actual_product_id );
		
		if ( $customer_price !== false ) {
			$quantity = $item->get_quantity();
			$total = $customer_price * $quantity;
		}

		return $total;
	}

	/**
	 * Filter order item subtotal.
	 *
	 * @param mixed $subtotal Item subtotal.
	 * @param object $item Order item object.
	 * @return mixed
	 */
	public function filter_order_item_subtotal( $subtotal, $item ) {
		$product_id = $item->get_product_id();
		$variation_id = $item->get_variation_id();
		
		$actual_product_id = $variation_id ? $variation_id : $product_id;
		$customer_price = $this->get_customer_product_price( $actual_product_id );
		
		if ( $customer_price !== false ) {
			$quantity = $item->get_quantity();
			$subtotal = $customer_price * $quantity;
		}

		return $subtotal;
	}

	/**
	 * Get customer-specific product price.
	 *
	 * @param int $product_id Product ID.
	 * @return float|false
	 */
	public function get_customer_product_price( $product_id ) {
		// Get current user
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Get user's company
		$company_id = $this->get_user_company_id( $user_id );
		if ( ! $company_id ) {
			return false;
		}

		// Get company's pricelist
		$company_manager = new BC_Company_Manager();
		$pricelist = $company_manager->get_company_pricelist( $company_id );
		if ( ! $pricelist ) {
			return false;
		}

		// Get product's BC number
		$bc_product_number = get_post_meta( $product_id, '_bc_product_number', true );
		if ( ! $bc_product_number ) {
			return false;
		}

		// Get price from pricelist
		$pricelist_manager = new BC_Pricelist_Manager();
		$price = $pricelist_manager->get_product_price_from_pricelist( $pricelist->id, $product_id );
		
		if ( $price === false ) {
			// Try to get price by BC product number
			$price = $this->get_price_by_bc_number( $pricelist->id, $bc_product_number );
		}

		return $price;
	}

	/**
	 * Get user's company ID.
	 *
	 * @param int $user_id User ID.
	 * @return int|false
	 */
	private function get_user_company_id( $user_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_dokobit_user_phones';
		
		$company_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT company_id FROM $table_name WHERE user_id = %d",
			$user_id
		) );
		
		return $company_id ? (int) $company_id : false;
	}

	/**
	 * Get price by Business Central product number.
	 *
	 * @param int    $pricelist_id Pricelist ID.
	 * @param string $bc_number Business Central product number.
	 * @return float|false
	 */
	private function get_price_by_bc_number( $pricelist_id, $bc_number ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bc_pricelist_lines';
		
		$price = $wpdb->get_var( $wpdb->prepare(
			"SELECT unit_price FROM $table_name 
			WHERE pricelist_id = %d AND item_number = %s",
			$pricelist_id, $bc_number
		) );
		
		return $price ? (float) $price : false;
	}

	/**
	 * Display customer pricing information on product pages.
	 */
	public function display_customer_pricing_info() {
		global $product;
		
		if ( ! $product ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$company_id = $this->get_user_company_id( $user_id );
		if ( ! $company_id ) {
			return;
		}

		$company_manager = new BC_Company_Manager();
		$company = $company_manager->get_company_by_id( $company_id );
		$pricelist = $company_manager->get_company_pricelist( $company_id );
		
		if ( ! $company || ! $pricelist ) {
			return;
		}

		$customer_price = $this->get_customer_product_price( $product->get_id() );
		
		if ( $customer_price !== false ) {
			$regular_price = $product->get_regular_price();
			
			if ( $customer_price != $regular_price ) {
				echo '<div class="bc-customer-pricing-info">';
				echo '<p class="bc-company-name">' . sprintf( __( 'Special pricing for %s', 'bc-business-central-sync' ), esc_html( $company->company_name ) ) . '</p>';
				echo '<p class="bc-pricelist-name">' . sprintf( __( 'Pricelist: %s', 'bc-business-central-sync' ), esc_html( $pricelist->name ) ) . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Get all customer prices for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_all_customer_prices( $product_id ) {
		$prices = array();
		
		// Get all companies with their pricelists
		$company_manager = new BC_Company_Manager();
		$companies = $company_manager->get_all_companies();
		
		foreach ( $companies as $company ) {
			$pricelist = $company_manager->get_company_pricelist( $company->id );
			if ( $pricelist ) {
				$pricelist_manager = new BC_Pricelist_Manager();
				$price = $pricelist_manager->get_product_price_from_pricelist( $pricelist->id, $product_id );
				
				if ( $price !== false ) {
					$prices[] = array(
						'company_id' => $company->id,
						'company_name' => $company->company_name,
						'pricelist_id' => $pricelist->id,
						'pricelist_name' => $pricelist->name,
						'price' => $price
					);
				}
			}
		}
		
		return $prices;
	}

	/**
	 * Check if user has access to customer pricing.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function user_has_customer_pricing( $user_id ) {
		$company_id = $this->get_user_company_id( $user_id );
		if ( ! $company_id ) {
			return false;
		}

		$company_manager = new BC_Company_Manager();
		$pricelist = $company_manager->get_company_pricelist( $company_id );
		
		return $pricelist !== false;
	}
}

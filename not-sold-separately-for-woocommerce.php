<?php
/*
* Plugin Name: Not Sold Separately for WooCommerce
* Plugin URI: https://woocommerce.com/products/woocommerce-mix-and-match-products/
* Description: Optionally restrict products to sale only as part of Mix and Match or Bundle Product.
* Version: 1.1.0-beta-2
* Author: Kathy Darling
* Author URI: http://kathyisawesome.com/
*
* Text Domain: not-sold-separately-for-woocommerce
* Domain Path: /languages/
*
* Requires PHP: 7.0
* Requires at least: 5.6.0
* Tested up to: 6.0.0
*
* WC requires at least: 6.0.0
* WC tested up to: 6.6.0
*
* Copyright: © 2020 Kathy Darling
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Not_Sold_Separately {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '1.1.0-beta-2';

	/**
	 * Props added to child products.
	 * @var array
	 */
	private static $bundled_props = [];

	/**
	 * Functions that test child cart items.
	 * @var array
	 */
	private static $bundled_cart_fn = [];

	/**
	 * Skip test in cart.
	 * @var bool
	 */
	private static $cart_loading = false;

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename(__FILE__) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {

		// Bundles.
		if ( class_exists( 'WC_Bundles' ) ) {
			self::$bundled_props[]   = 'bundled_item';
			self::$bundled_props[]   = 'bundled_by';
			self::$bundled_cart_fn[] = 'wc_pb_is_bundled_cart_item';
		}

		// Composites.
		if ( class_exists( 'WC_Composite_Products' ) ) {

		}

		// Mix and Match.
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$bundled_props[]   = 'mnm_child_item';
			self::$bundled_cart_fn[] = 'wc_mnm_is_child_cart_item';
		}

		if ( ! empty( self::$bundled_props ) ) {
			self::add_hooks();
		}

	}

	/**
	 * Hooks for plugin support.
	 */
	public static function add_hooks() {
		// Admin
		add_action( 'woocommerce_product_options_inventory_product_data', [ __CLASS__, 'product_options' ] );
		add_action( 'woocommerce_admin_process_product_object', [ __CLASS__, 'save_meta' ] );

		// Variable Product.
		add_action( 'woocommerce_variation_options', [ __CLASS__, 'product_variations_options' ], 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', [ __CLASS__, 'save_product_variation' ], 30, 2 );

		// Manipulate product availability.
		add_filter( 'woocommerce_is_purchasable', [ __CLASS__, 'is_purchasable' ], 99, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', [ __CLASS__, 'is_purchasable' ], 99, 2 );
		add_filter( 'woocommerce_variation_is_visible', [ __CLASS__, 'is_visible' ], 99, 4 );
		
		// Remove is_purchasable filter in cart session.
		add_action( 'woocommerce_load_cart_from_session', [ __CLASS__, 'remove_is_purchasable' ] );

		// Restore is_purchasable filter after cart loaded.
		add_action( 'woocommerce_cart_loaded_from_session', [ __CLASS__, 'restore_is_purchasable' ] );

		// Change add to cart validation error.
		add_filter( 'woocommerce_cart_product_cannot_be_purchased_message', [ __CLASS__, 'product_cannot_be_purchased_message' ], 10, 2 );	

		// Catch any stray standalone products.
		add_filter( 'woocommerce_pre_remove_cart_item_from_session', [ __CLASS__, 'remove_cart_item_from_session' ], 10, 3 );		

	}


	/*-----------------------------------------------------------------------------------*/
	/* Admin Display */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Adds the sold separately option.
	 */
	public static function product_options() {

		global $product_object;

		// Not sold separately meta.
		woocommerce_wp_checkbox( array(
			'id'          => '_not_sold_separately',
			'label'       => __( 'Not sold separately', 'not-sold-separately-for-woocommerce' ),
			'wrapper_class' => 'show_if_simple show_if_variable',
			'value'       => self::is_not_sold_separately( $product_object, 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Enable this if this product should only be sold as part of a bundle.', 'not-sold-separately-for-woocommerce' ),
		) );

	}


	/**
	 * Save the meta
	 *
	 * @param  WC_Product  $product
	 */
	public static function save_meta( $product ) {

		if ( $product->is_type( array( 'simple', 'variable' ) ) ) {

			if ( isset( $_POST['_not_sold_separately'] ) ) {
				$product->update_meta_data( '_not_sold_separately', 'yes' );
			} else {
				$product->delete_meta_data( '_not_sold_separately' );
			}

		}

	}

	/**
	 * Add checkbox to each variation
	 *
	 * @since 1.1.0
	 *
	 * @param string  $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public static function product_variations_options( $loop, $variation_data, $variation ) {

		$not_sold_separately = self::is_not_sold_separately( $variation->ID, 'edit' );
		?>

		<label><input type="checkbox" class="checkbox not_sold_separately" name="not_sold_separately[<?php echo esc_attr( $loop ); ?>]" <?php checked( $not_sold_separately, 'yes' ); ?> /> <?php esc_html_e( 'Not sold separately', 'not-sold-separately-for-woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'Enable this if this product should only be sold as part of a bundle.', 'not-sold-separately-for-woocommerce' ); ?>" href="#">[?]</a></label>

		<?php

	}

	/**
	 * Save extra meta info for variations
	 *
	 * @since 1.1.0
	 *
	 * @param obj WC_Product_Variation $variation
	 * @param int $i
	 */
	public static function save_product_variation( $variation, $i ) {
		$not_sold_separately = wc_bool_to_string( isset( $_POST['not_sold_separately'][ $i ] ) );
		$variation->update_meta_data( '_not_sold_separately', $not_sold_separately );

	}


	/*-----------------------------------------------------------------------------------*/
	/* Front-end Display */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Prevent products from being added to cart if not sold separately.
	 * 
	 * @param bool $is_purchasable
	 * @param WC_Product $product Product object.
	 * @return  bool
	 */
	public static function is_purchasable( $is_purchasable , $product ) {

		if ( ! self::$cart_loading ) {

			if ( ! $product->get_parent_id() && self::is_not_sold_separately( $product ) && ! self::is_in_bundled_context( $product ) ) {
				$is_purchasable = false;
			}
		}
		return $is_purchasable;
	}

	/**
	 * Prevent variations from displaying if not sold separately.
	 *
	 * @since  1.1.0
	 * 
	 * @param bool $is_visible
	 * @param int $variation_id
	 * @param int $parent_id
	 * @param WC_Product_Variation $variation Product object.
	 * @return  bool
	 */
	public static function is_visible( $is_visible , $variation_id, $parent_id, $variation ) {
		if ( self::is_not_sold_separately( $variation ) && ! self::is_in_bundled_context( $variation ) ) { 
			$is_visible = false;
		}
		return $is_visible;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Cart validation.                                                                  */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Set flag to bypass is_purchasable filter while loading the cart from session.
	 */
	public static function remove_is_purchasable() {
		self::$cart_loading = true;
	}

	/**
	 * Reset cart loading flag.
	 */
	public static function restore_is_purchasable() {
		self::$cart_loading = false;
	}


	/**
	 * Filters message about product unable to be purchased.
	 *
	 * @since 2.0.0
	 * @param string     $message Message.
	 * @param WC_Product $product Product data.
	 */
	public static function product_cannot_be_purchased_message( $message, $product ) {
		if ( self::is_not_sold_separately( $product ) ) {
			// Translators: %s is the name of the product being added to the cart.
			$message = sprintf( __( 'Sorry, %s is not sold separately.', 'not-sold-separately-for-woocommerce' ), $product->get_name() );
		}
		return $message;
	}


	/**
	 * Prevent products from being added to cart if not sold separately.
	 * 
	 * @param bool $remove If true, the item will not be added to the cart. Default: false.
	 * @param string $key Cart item key.
	 * @param array $values Cart item values e.g. quantity and product_id.
	 * @return  bool
	 */
	public static function remove_cart_item_from_session( $remove, $key, $values ) {

		$is_bundled = false;
		
		foreach( self::$bundled_cart_fn as $fn ) {
			if ( $fn( $values ) ) {
				$is_bundled = true;
				break;
			}

		}

		if ( ! $is_bundled ) {

			$product = wc_get_product( $values['variation_id'] ? $values['variation_id'] : $values['product_id'] );

			$remove = self::is_not_sold_separately( $product );
		
			if ( $remove ) {
				/* translators: %s: product name */
				$message = sprintf( __( '%s has been removed from your cart because it cannot be purchased separately. Please contact us if you need assistance.', 'not-sold-separately-for-woocommerce' ), $product->get_name() );
				/**
				 * Filter message about item removed from the cart.
				 *
				 * @since 2.0.0
				 * @param string     $message Message.
				 * @param WC_Product $product Product data.
				 */
				$message = apply_filters( 'wc_not_sold_separately_cart_item_removed_message', $message, $product );
				wc_add_notice( $message, 'error' );
			}
		}
		return $remove;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Helpers                                                                           */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Test if the product is part of a bundle.
	 *
	 * @param mixed int|WC_Product
	 *
	 * @return bool
	 */
	private static function is_not_sold_separately( $product, $context = 'view' ) {

		if ( is_integer( $product ) ) { 
			$product = wc_get_product( $product );
		}

		return $product instanceof WC_Product && wc_string_to_bool( $product->get_meta( '_not_sold_separately', true, $context ) );

	}

	/**
	 * Test if the product is part of a bundle.
	 *
	 * @param mixed int|WC_Product
	 *
	 * @return bool True if has defining prop set on product.
	 */
	private static function is_in_bundled_context( $product ) {

		if ( is_integer( $product ) ) { 
			$product = wc_get_product( $product );
		}

		$is_in_bundled_context = false;

		foreach ( self::$bundled_props as $prop ) {
			if ( property_exists( $product, $prop ) ) {
				$is_in_bundled_context = true;
				break;
			}
		}
		

		return $is_in_bundled_context;
	}

}
add_action( 'plugins_loaded', array( 'WC_Not_Sold_Separately', 'init' ), 20 );
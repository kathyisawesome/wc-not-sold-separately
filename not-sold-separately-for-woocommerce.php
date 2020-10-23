<?php
/*
* Plugin Name: Not Sold Separately for WooCommerce
* Plugin URI: https://woocommerce.com/products/woocommerce-mix-and-match-products/
* Description: Optionally restrict products to sale only as part of Mix and Match or Bundle Product.
* Version:           1.0.0-beta-1
* Author: Kathy Darling
* Author URI: http://kathyisawesome.com/
*
* Text Domain: not-sold-separately-for-woocommerce
* Domain Path: /languages/
*
* Requires at least: 5.3.0
* Tested up to: 5.3.0
*
* WC requires at least: 4.0.0
* WC tested up to: 4.1.0
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
	public static $version = '1.0.0-beta-1';

	/**
	 * Complex product types.
	 * @var array
	 */
	private static $bundle_types = array();

	/**
	 * Classes in the backtrace to exclude.
	 * @var array
	 */
	private static $backtrace_exclusions = array();


	/**
	 * Post-sync hook names.
	 * @var array
	 */
	private static $cart_loaded = true;

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
			self::$bundle_types[]       = 'bundle';
			$add_to_exclusions          = array( 'WC_Product_Bundle', 'WC_PB_Cart' );
			self::$backtrace_exclusions = array_merge( self::$backtrace_exclusions, $add_to_exclusions );
		}

		// Composites.
		if ( class_exists( 'WC_Composite_Products' ) ) {
			self::$bundle_types[]       = 'composite';
			$add_to_exclusions          = array( 'WC_Product_Composite', 'WC_CP_Cart' );
			self::$backtrace_exclusions = array_merge( self::$backtrace_exclusions, $add_to_exclusions );
		}

		// Mix n Match.
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$bundle_types[]       = 'mix-and-match';
			$add_to_exclusions          = array( 'WC_Product_Mix_and_Match', 'WC_Mix_and_Match_Cart' );
			self::$backtrace_exclusions = array_merge( self::$backtrace_exclusions, $add_to_exclusions );
		}

		if ( ! empty( self::$bundle_types ) ) {
			self::add_hooks();
		}

	}

	/**
	 * Hooks for plugin support.
	 */
	public static function add_hooks() {

		/**
		 * Admin.
		 */
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'product_options' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_meta' ) );

		/**
		 * Manipulate single product availability.
		 */
		add_action( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 99, 2 );
		
		// Remove is_purchasable filter in cart session.
		add_action( 'woocommerce_load_cart_from_session', array( __CLASS__, 'remove_is_purchasable' ) );

		// Restore is_purchasable filter after cart loaded.
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'restore_is_purchasable' ) );



	}


	/*-----------------------------------------------------------------------------------*/
	/* Admin Display */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Adds the sold separately option.
	 *
	 */
	public static function product_options() {

		global $product_object;

		// Not sold separately meta.
		woocommerce_wp_checkbox( array(
			'id'          => '_not_sold_separately',
			'label'       => __( 'Not sold separately', 'woocommerce-mix-and-match-products' ),
			'wrapper_class' => 'show_if_simple show_if_variable',
			'value'       => wc_string_to_bool( $product_object->get_meta( '_not_sold_separately' ) ) ? 'yes' : 'no',
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

	/*-----------------------------------------------------------------------------------*/
	/* Front-end Display */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Removes is_purchasable filter in bundled product contexts.
	 */
	public static function remove_is_purchasable() {

		// Set cart loading flag, because the synced hook is firing on the parent product before the bundled products finish loading.
		if( 'woocommerce_load_cart_from_session' === current_action() ) {
			self::$cart_loaded = false;
		}
		remove_action( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 99, 2 );
	}

	/**
	 * Removes is_purchasable filter in bundled product contexts.
	 */
	public static function restore_is_purchasable() {

		// Reset cart loading flag.
		if( 'woocommerce_cart_loaded_from_session' === current_action() ) {
			self::$cart_loaded = true;
		}

		if( self::$cart_loaded ) {
			add_action( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 99, 2 );
		}
	}

	/*-----------------------------------------------------------------------------------*/
	/* Cart validation.                                                                  */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Prevent products from being added to cart if not sold separately.
	 * @param bool $is_purchasable
	 * @param WC_Product $product Product object.
	 * @return  bool
	 */
	public static function is_purchasable( $is_purchasable , $product ) {

		if( $product->is_type( array( 'simple', 'variable' ) ) && wc_string_to_bool( $product->get_meta( '_not_sold_separately' ) ) && ! self::is_classes_in_backtrace( self::$backtrace_exclusions ) ) {
			$is_purchasable = false;
		}
		return $is_purchasable;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Helpers                                                                           */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * To call {@see is_function_in_backtrace()} with the array of parameters.
	 *
	 * @param classes[] $classess Array of classess.
	 *
	 * @return bool True if any of the pair is found in the backtrace.
	 */
	public static function is_classes_in_backtrace( array $classes ) {
		foreach ( $classes as $class ) {
			if ( self::is_class_in_backtrace( $class ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if was called by a specific class (could be any levels deep).
	 *
	 * @param callable|string $class_name Class name.
	 *
	 * @return bool True if Class is in backtrace.
	 */
	public static function is_class_in_backtrace( $class_name ) {
		$class_in_backtrace = false;

		// Only look for strings.
		if ( ! is_string( $class_name ) ) {
			return false;
		}

		// Traverse backtrace and stop if the callable is found there.
		foreach ( debug_backtrace() as $trace ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			if ( isset( $trace['class'] ) && $trace['class'] === $class_name ) {
				$class_in_backtrace = true;
				if ( $class_in_backtrace ) {
					break;
				}
			}
		}

		return $class_in_backtrace;
	}

}
add_action( 'plugins_loaded', array( 'WC_Not_Sold_Separately', 'init' ), 20 );
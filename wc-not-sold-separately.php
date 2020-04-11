<?php
/*
* Plugin Name: WooCommerce Mix and Match: Not Sold Separately
* Plugin URI: https://woocommerce.com/products/woocommerce-mix-and-match-products/
* Description: Optionally restrict products to sale only as part of Mix and Match Product.
* Version: 0.0.1-beta-1
* Author: Kathy Darling
* Author URI: http://kathyisawesome.com/
*
* Text Domain: wc-mnm-not-sold-separately
* Domain Path: /languages/
*
* Requires at least: 5.3.0
* Tested up to: 5.3.0
*
* WC requires at least: 4.0.0
* WC tested up to: 4.0.0
*
* Copyright: © 2020 Kathy Darling
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_MNM_Not_Sold_Separately {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '0.0.1-beta-1';

	/**
	 * Pre-sync hook names.
	 * @var array
	 */
	private static $pre_sync_hooks = array();

	/**
	 * Post-sync hook names.
	 * @var array
	 */
	private static $post_sync_hooks = array();

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
			self::$pre_sync_hooks[]               = 'woocommerce_bundles_before_sync_bundle';
			self::$post_sync_hooks[]              = 'woocommerce_bundles_synced_bundle';
		}

		// Composites.
		if ( class_exists( 'WC_Composite_Products' ) ) {
			self::$pre_sync_hooks[]               = 'woocommerce_composite_before_sync_bundle';
			self::$post_sync_hooks[]              = 'woocommerce_composite_synced_bundle';
		}

		// Mix n Match.
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$pre_sync_hooks[]               = 'wc_mnm_before_sync';
			self::$post_sync_hooks[]              = 'woocommerce_mnm_synced';
		}

		if ( ! empty( self::$pre_sync_hooks ) || ! empty( self::$post_sync_hooks ) ) {
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

		// Remove is_purchasable filter before sync.
		foreach( self::$pre_sync_hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'remove_is_purchasable' ), 1 );
		}

		// Restore is_purchasable filter after sync.
		foreach( self::$post_sync_hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'restore_is_purchasable' ), 1 );
		}

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
			'description' => __( 'Enable this to prevent purchase of this product outside of a bundle.', 'wc-mnm-not-sold-separately' ),
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
		remove_action( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 99, 2 );
	}

	/**
	 * Removes is_purchasable filter in bundled product contexts.
	 */
	public static function restore_is_purchasable() {
		add_action( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 99, 2 );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Cart validation. */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Prevent products from being added to cart if not sold separately.
	 * @param bool $is_purchasable
	 * @param WC_Product $product Product object.
	 * @return  bool
	 */
	public static function is_purchasable( $is_purchasable , $product ) {
		if( $product->is_type( array( 'simple', 'variable' ) ) && wc_string_to_bool( $product->get_meta( '_not_sold_separately' ) ) ) {
			$is_purchasable = false;
		}
		return $is_purchasable;
	}

}
add_action( 'woocommerce_mnm_loaded', array( 'WC_MNM_Not_Sold_Separately', 'init' ) );
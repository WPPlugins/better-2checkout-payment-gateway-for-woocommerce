<?php

/**
 * Plugin Name: Better 2Checkout for WooCommerce (Lite)
 * Plugin URI: http://omnipay.io/downloads/better-2checkout-payment-gateway-for-woocommerce/
 * Description: Accept Credit Card and PayPal payments in your WooCommerce store via 2checkout
 * Version: 1.0.1
 * Author: Agbonghama Collins (W3Guy LLC)
 * Author URI: http://omnipay.io
 * Text Domain: better-2checkout-for-woocommerce
 * Domain Path: /languages
 */

namespace OmnipayWP\WC_2Checkout_Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'W3GUY_2CHECKOUT_LITE_FILE_PATH', __FILE__ );
define( 'W3GUY_2CHECKOUT_LITE_ROOT', plugin_dir_path( __FILE__ ) );
define( 'W3GUY_2CHECKOUT_LITE_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'W3GUY_2CHECKOUT_LITE_WOOCOMMERCE_ID', 'better-2checkout-for-woocommerce' );
define( 'W3GUY_2CHECKOUT_LITE_ASSETS_URL', W3GUY_2CHECKOUT_LITE_ROOT_URL . 'assets/' );

class Base {

	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway_name' ) );
	}


	/**
	 * Add the Gateway to WooCommerce
	 **/
	function add_gateway_name( $methods ) {
		$methods[] = 'WC_2Checkout_Gateway_Lite';

		return $methods;
	}

}

function twocheckout_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	load_plugin_textdomain( 'better-2checkout-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	require W3GUY_2CHECKOUT_LITE_ROOT . 'vendor/autoload.php';
	require W3GUY_2CHECKOUT_LITE_ROOT . 'class.wc-2checkout-lite.php';

	new Base;
}


add_action( 'plugins_loaded', 'OmnipayWP\WC_2Checkout_Lite\twocheckout_init', 0 );

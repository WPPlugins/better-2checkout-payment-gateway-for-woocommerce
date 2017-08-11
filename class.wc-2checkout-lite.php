<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Omnipay\Omnipay;

/**
 * Gateway class
 */
class WC_2Checkout_Gateway_Lite extends \WC_Payment_Gateway {

	/** @var bool Whether or not logging is enabled */
	public $debug_active = false;

	/** @var WC_Logger Logger instance */
	public $log = false;

	/** @var string WC_API for the gateway - being use as return URL */
	public $returnUrl;

	function __construct() {

		// The global ID for this Payment method
		$this->id = W3GUY_2CHECKOUT_LITE_WOOCOMMERCE_ID;

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "2Checkout", 'better-2checkout-for-woocommerce' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "2Checkout Payment Gateway for WooCommerce",
			'better-2checkout-for-woocommerce' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "2Checkout", 'better-2checkout-for-woocommerce' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = apply_filters( 'omnipay_2checkout_icon', null );

		$this->supports = array();

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		$this->init_settings();

		$this->debug_active = true;
		$this->has_fields   = false;

		$this->description = $this->get_option( 'description' );

		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Set if the place order button should be renamed on selection.
		$this->order_button_text = apply_filters( 'woocommerce_2checkout_button_text',
			__( 'Proceed to 2Checkout', 'better-2checkout-for-woocommerce' ) );

		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' ) );
		}

		// Hooks
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_wc_api' ) );
	}

	/**
	 * Gateway settings page.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable / Disable', 'better-2checkout-for-woocommerce' ),
				'label'   => __( 'Enable this payment gateway', 'better-2checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'environment'    => array(
				'title'       => __( '2Chekout Test Mode', 'better-2checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'better-2checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( '2Checkout sandbox can be used to test payments. Sign up for an account <a href="%s">here</a>',
					'better-2checkout-for-woocommerce' ),
					'https://sandbox.2checkout.com' ),
				'default'     => 'no',
			),
			'title'          => array(
				'title'   => __( 'Title', 'better-2checkout-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( '2Checkout', 'better-2checkout-for-woocommerce' ),
			),
			'description'    => array(
				'title'   => __( 'Description', 'better-2checkout-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely using your credit card or PayPal', 'better-2checkout-for-woocommerce' ),
				'css'     => 'max-width:350px;',
			),
			'account_number' => array(
				'title'       => __( 'Account Number', 'better-2checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your 2Checkout account Number.', 'better-2checkout-for-woocommerce' ),
			),
			'secret_word'    => array(
				'title'       => __( 'Secret Word', 'better-2checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your 2Checkout secret word.', 'better-2checkout-for-woocommerce' ),
			),
		);
	}


	public function admin_options() { ?>

		<h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings',
				'woocommerce' ); ?></h3>

		<?php echo ( ! empty( $this->method_description ) ) ? wpautop( $this->method_description ) : ''; ?>

		<div id="message" class="error notice"><p>
				<?php printf(
					__(
						'Inline checkout style, on-site checkout style and access to support from WooCommerce experts. <strong><a target="_blank" href="%s">Upgrade to PRO Now</a></strong>.',
						'better-2checkout-for-woocommerce'
					),
					'https://omnipay.io/downloads/better-2checkout-payment-gateway-for-woocommerce/'
				); ?>
			</p></div>
		<table class="form-table">
		<?php $this->generate_settings_html(); ?>
		</table><?php
	}

	/**
	 * Is gateway in test mode?
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return $this->environment == "yes";
	}

	/**
	 * 2co gateway perform test payment by appending demo=Y
	 * to the payment parameter before redirecting offsite to 2co for payment.
	 *
	 * This filter controls enabling testing via sandbox account.
	 *
	 * @return bool
	 */
	public function is_sandbox_test() {
		return apply_filters( 'woocommerce_2checkout_enable_sandbox', false );
	}

	/**
	 * WooCommerce payment processing function/method.
	 *
	 * @inheritdoc
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 */
	public function process_payment( $order_id ) {
		$order           = new WC_Order( $order_id );
		$this->returnUrl = WC()->api_request_url( __CLASS__ );

		do_action( 'omnipay_2checkout_lite_before_process_payment' );

		// call the appropriate method to process the payment.
		return $this->process_off_site_payment( $order );
	}


	/**
	 * Return 2Checkout Omnipay instance.
	 *
	 * @return mixed
	 */
	public function gateway_instance() {

		$gateway = Omnipay::create( 'TwoCheckoutPlus' );
		$gateway->setAccountNumber( $this->account_number );
		$gateway->setSecretWord( $this->secret_word );
		$gateway->setTestMode( $this->is_sandbox_test() );
		// activate test mode by passing demo parameter to checkout parameters.
		$gateway->setDemoMode( $this->is_test_mode() );

		return $gateway;
	}

	/**
	 * Process off-site payment.
	 *
	 * @param $order
	 *
	 * @return array|void
	 */
	public function process_off_site_payment( WC_Order $order ) {
		try {
			$gateway = $this->gateway_instance();

			$formData = array(
				'firstName' => $order->billing_first_name,
				'lastName'  => $order->billing_last_name,
				'email'     => $order->billing_email,
				'address1'  => $order->billing_address_1,
				'address2'  => $order->billing_address_2,
				'city'      => $order->billing_city,
				'state'     => $order->billing_state,
				'postcode'  => $order->billing_postcode,
				'country'   => $order->billing_country,
			);

			$order_cart             = $order->get_items();

			$cart = array();
			foreach ( $order_cart as $product_id => $product ) {
				$cart[] = array(
					'name'       => $product['name'],
					'quantity'   => $product['qty'],
					'price'      => $product['line_total'],
					'product_id' => $product_id,
				);
			}

			if ( ( $shipping_total = $order->get_total_shipping() ) > 0 ) {
				$cart[] = array(
					'name'     => __( 'Shipping Fee', 'better-2checkout-for-woocommerce' ),
					'quantity' => 1,
					'price'    => $shipping_total,
				);
			}

			$gateway->setCart( $cart );

			$response = $gateway->purchase(
				apply_filters( 'woocommerce_2checkout_lite_args',
					array(
						'card'          => $formData,
						'transactionId' => $order->get_order_number(),
						'currency'      => get_woocommerce_currency(),
						// add a query parameter to the returnUrl to listen and complete payment
						'returnUrl'     => $this->returnUrl,
					)
				)
			)->send();


			do_action( 'woocommerce_2checkout_lite_before_payment_redirect', $response );

			if ( $response->isRedirect() ) {
				return array(
					'result'   => 'success',
					'redirect' => $response->getRedirectUrl(),
				);
			} else {
				$error = $response->getMessage();
				$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
				wc_add_notice( $error, 'error' );
				$this->log( $error );

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} catch ( Exception $e ) {
			$error = $e->getMessage();
			$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
			wc_add_notice( $error, "error" );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Handles off-site return and processing of order.
	 */
	public function handle_wc_api() {
		if ( isset( $_REQUEST['invoice_id'] ) && ! empty( $_REQUEST['invoice_id'] ) ) {
			try {
				$gateway  = $this->gateway_instance();
				$response = $gateway->completePurchase()->send();

				$order = new WC_Order( $response->getTransactionId() );

				if ( $response->isSuccessful() ) {
					$transaction_ref = $response->getTransactionReference();
					$order->payment_complete();
					// Add order note
					$order->add_order_note(
						sprintf( __( '2Checkout payment complete (Charge ID: %s)', 'better-2checkout-for-woocommerce' ),
							$transaction_ref
						)
					);

					WC()->cart->empty_cart();
					wp_redirect( $this->get_return_url( $order ) );
					exit;
				} else {
					$error = $response->getMessage();
					$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
					wc_add_notice( $error, 'error' );
					$this->log( $error );
					wp_redirect( wc_get_checkout_url() );
					exit;
				}
			} catch ( \Exception $e ) {
				$error = $e->getMessage();
				wc_add_notice( $error, 'error' );
				$this->log( $error );
				wp_redirect( wc_get_checkout_url() );
				exit;
			}
		}
	}


	/**
	 * Logger helper function.
	 *
	 * @param $message
	 */
	public function log( $message ) {
		if ( $this->debug_active ) {
			if ( ! ( $this->log ) ) {
				$this->log = new WC_Logger();
			}
			$this->log->add( 'woocommerce_2checkout_lite', $message );
		}
	}
}

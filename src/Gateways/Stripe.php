<?php
namespace BengalStudio\EDD\Stripe\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use BengalStudio\EDD\Stripe\Loader;
use BengalStudio\EDD\Stripe\StripeAPI;

use EDD_Customer;

/**
 * Stripe class.
 */
class Stripe extends StripePayments {

	/**
	 * Gateway ID.
	 * @var string
	 */
	public $gateway_id;

	/**
	 * All the required settings have been filled out?
	 * @var [type]
	 */
	public $is_setup;

	/**
	 * API access publishable key.
	 * @var [type]
	 */
	public $publishable_key;

	/**
	 * API access secret key
	 * @var [type]
	 */
	public $secret_key;

	/**
	 * The single instance of the class.
	 * @var [type]
	 */
	private static $instance;

	/**
	 * Get class instance.
	 * @return [type] [description]
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * is not allowed to call from outside to prevent from creating multiple instances,
	 * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
	 */
	private function __construct() {
		// Update variables.
		$this->gateway_id = 'stripe';

		// Run this separate so we can ditch as early as possible
		$this->register();

		if ( ! edd_is_gateway_active( $this->gateway_id ) ) {
			return;
		}

		$this->set_api();
		$this->filters();
		$this->actions();
	}

	/**
	 * Register the payment gateway.
	 * @return [type] [description]
	 */
	private function register() {
		add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ), 1, 1 );
	}

	/**
	 * Register the gateway.
	 */
	public function register_gateway( $gateways ) {
		$default_stripe_info           = [];
		$default_stripe_info['stripe'] = array(
			'admin_label'    => __( 'Stripe', 'edd-gateway-stripe' ),
			'checkout_label' => __( 'Credit Card (Stripe)', 'edd-gateway-stripe' ),
			'supports'       => array(),
		);

		$default_stripe_info = apply_filters( 'edd_register_stripe_gateway', $default_stripe_info );
		$gateways            = array_merge( $gateways, $default_stripe_info );

		return $gateways;
	}

	/**
	 * Set Stripe API key.
	 */
	public function set_api() {
		if ( ! $this->is_setup() ) {
			return;
		}

		StripeAPI::set_secret_key( $this->secret_key );
	}

	/**
	 * Add filters.
	 * @return [type] [description]
	 */
	public function filters() {
		if ( is_admin() ) {
			add_filter( 'edd_settings_sections_gateways', array( $this, 'register_gateway_section' ), 1, 1 );
			add_filter( 'edd_settings_gateways', array( $this, 'register_gateway_settings' ), 1, 1 );
		}
	}

	/**
	 * Add actions.
	 * @return [type] [description]
	 */
	public function actions() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_filter( 'edd_enabled_payment_gateways', array( $this, 'prepare_pay_page' ) );
		add_action( 'edd_stripe_cc_form', array( $this, 'get_cc_form' ) );
		add_action( 'edd_checkout_error_checks', array( $this, 'checkout_error_checks' ) );
		add_action( 'edd_gateway_stripe', array( $this, 'process_purchase' ) );
		add_action( 'edd_update_payment_status', array( $this, 'cancel_purchase' ), 200, 3 );
	}

	/**
	 * Register the payment gateways setting section.
	 * @param  [type] $gateway_sections [description]
	 * @return [type]                   [description]
	 */
	public function register_gateway_section( $gateway_sections ) {
		$gateway_sections['stripe'] = __( 'Stripe Payments', 'edd-gateway-stripe' );

		return $gateway_sections;
	}

	/**
	 * Register the gateway settings.
	 * @param  [type] $gateway_settings [description]
	 * @return [type]                   [description]
	 */
	public function register_gateway_settings( $gateway_settings ) {
		$default_stripe_settings = array(
			'stripe'                      => array(
				'id'   => 'stripe',
				'name' => '<strong>' . __( 'Stripe Payments Settings', 'edd-gateway-stripe' ) . '</strong>',
				'type' => 'header',
			),
			'stripe_test_publishable_key' => array(
				'id'   => 'stripe_test_publishable_key',
				'name' => __( 'Test Publishable Key', 'woocommerce-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
				'type' => 'text',
				'std'  => '',
			),
			'stripe_test_secret_key'      => array(
				'id'   => 'stripe_test_secret_key',
				'name' => __( 'Test Secret Key', 'woocommerce-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
				'type' => 'password',
				'std'  => '',
			),
			'stripe_publishable_key'      => array(
				'id'   => 'stripe_publishable_key',
				'name' => __( 'Live Publishable Key', 'woocommerce-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
				'type' => 'text',
				'std'  => '',
			),
			'stripe_secret_key'           => array(
				'id'   => 'stripe_secret_key',
				'name' => __( 'Live Secret Key', 'woocommerce-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'woocommerce-gateway-stripe' ),
				'type' => 'password',
				'std'  => '',
			),
			'stripe_capture'              => array(
				'id'    => 'stripe_capture',
				'name'  => __( 'Capture', 'woocommerce-gateway-stripe' ),
				'label' => __( 'Capture charge immediately', 'woocommerce-gateway-stripe' ),
				'desc'  => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-stripe' ),
				'type'  => 'checkbox',
				'std'   => 1,
			),
		);

		$default_stripe_settings    = apply_filters( 'edd_default_stripe_settings', $default_stripe_settings );
		$gateway_settings['stripe'] = $default_stripe_settings;

		return $gateway_settings;
	}

	/**
	 * Method to check if all the required settings have been filled out, allowing us to not output information without it.
	 * @return boolean [description]
	 */
	public function is_setup() {
		if ( null !== $this->is_setup ) {
			return $this->is_setup;
		}

		$this->secret_key      = edd_is_test_mode() ? edd_get_option( 'stripe_test_secret_key', '' ) : edd_get_option( 'stripe_secret_key', '' );
		$this->publishable_key = edd_is_test_mode() ? edd_get_option( 'stripe_test_publishable_key', '' ) : edd_get_option( 'stripe_publishable_key', '' );
		$this->is_setup        = $this->are_keys_set();

		return $this->is_setup;
	}

	/**
	 * Checks if keys are set.
	 */
	public function are_keys_set() {
		if ( empty( $this->secret_key ) || empty( $this->publishable_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register scripts.
	 * @return [type] [description]
	 */
	public function register_scripts() {
		// Exit early.
		if ( ! $this->is_setup() || ! edd_is_checkout() ) {
			return;
		}

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script(
			'edd_stripe',
			Loader::get_url( 'stripe.js' ),
			array( 'jquery', 'stripe' ),
			Loader::get_file_version( 'stripe.js' ),
			true
		);

		$stripe_params = array(
			'key' => $this->publishable_key,
		);

		wp_localize_script(
			'edd_stripe',
			'edd_stripe_params',
			apply_filters( 'edd_stripe_params', $stripe_params )
		);

		wp_register_style(
			'edd_stripe',
			Loader::get_url( 'stripe.css' ),
			array(),
			Loader::get_file_version( 'stripe.css' )
		);
	}

	/**
	 * Load scripts.
	 * @return [type] [description]
	 */
	public function load_scripts() {
		wp_enqueue_script( 'edd_stripe' );
		wp_enqueue_style( 'edd_stripe' );
	}

	/**
	 * [checkout_error_checks description]
	 * @return [type] [description]
	 */
	public function checkout_error_checks() {
		// edd_set_error( 'missing_reference_id', __( 'Missing Reference ID, please try again', 'edd-gateway-stripe' ) );
		// edd_die();
	}

	/**
	 * Process the purchase and create the charge in Stripe.
	 * @param  [type] $purchase_data [description]
	 * @return [type]                [description]
	 */
	public function process_purchase( $purchase_data ) {
		if ( empty( $purchase_data['post_data']['stripe_source'] ) ) {
			edd_set_error( 'missing_source_id', __( 'Missing Source ID, please try again', 'edd-gateway-stripe' ) );
		}

		$errors = edd_get_errors();
		if ( $errors ) {
			edd_send_back_to_checkout( '?payment-mode=stripe' );
		}

		// Collect payment data
		$payment_data = array(
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => $this->gateway_id,
			'status'       => 'pending',
		);

		// Record the pending payment
		$payment_id = edd_insert_payment( $payment_data );

		// Check payment
		if ( ! $payment_id ) {
			// Problems? send back
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		}

		// Set the session data to recover this payment in the event of abandonment or error.
		EDD()->session->set( 'edd_resume_payment', $payment_id );

		// Prepare source.
		$prepared_source = $this->prepare_source( $payment_id, $purchase_data );

		// Update or create payment intent.
		$payment_intent = $this->get_payment_intent( $payment_id );
		if ( $payment_intent ) {
			$payment_intent = $this->update_payment_intent( $payment_intent, $prepared_source, $purchase_data );
		} else {
			$payment_intent = $this->create_payment_intent( $payment_id, $prepared_source, $purchase_data );
		}

		// Confirm payment intent.
		if ( empty( $payment_intent->error ) ) {
			$payment_intent = $this->confirm_payment_intent( $payment_intent, $payment_id, $prepared_source );
		}

		if ( ! empty( $payment_intent ) ) {
			// Use the last charge within the intent to proceed.
			$response = end( $payment_intent->charges->data );
		}

		// Process valid response.
		$this->process_response( $response, $payment_id, $purchase_data );

		// Empty the shopping cart
		edd_empty_cart();
		edd_send_to_success_page();
	}

	/**
	 * [get_cc_form description]
	 * @return [type] [description]
	 */
	public function get_cc_form() {
		ob_start(); ?>

		<?php do_action( 'edd_before_cc_fields' ); ?>

		<fieldset id="edd_cc_fields" class="edd-do-validate">
			<legend><?php _e( 'Credit Card Info', 'edd-gateway-stripe' ); ?></legend>
			<?php if ( is_ssl() ) : ?>
				<div id="edd_secure_site_wrapper">
					<span class="padlock">
						<svg class="edd-icon edd-icon-lock" xmlns="http://www.w3.org/2000/svg" width="18" height="28" viewBox="0 0 18 28" aria-hidden="true">
							<path d="M5 12h8V9c0-2.203-1.797-4-4-4S5 6.797 5 9v3zm13 1.5v9c0 .828-.672 1.5-1.5 1.5h-15C.672 24 0 23.328 0 22.5v-9c0-.828.672-1.5 1.5-1.5H2V9c0-3.844 3.156-7 7-7s7 3.156 7 7v3h.5c.828 0 1.5.672 1.5 1.5z"/>
						</svg>
					</span>
					<span><?php _e( 'This is a secure SSL encrypted payment.', 'edd-gateway-stripe' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( edd_is_test_mode() ) : ?>
				<?php printf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank">Testing Stripe documentation</a> for more card numbers.', 'edd-gateway-stripe' ), 'https://stripe.com/docs/testing' ); ?>
			<?php endif; ?>
			<p id="edd-card-number-wrap">
				<label for="card_number" class="edd-label">
					<?php _e( 'Card Number', 'edd-gateway-stripe' ); ?>
					<span class="edd-required-indicator">*</span>
					<span class="card-type"></span>
				</label>
				<span class="edd-description"><?php _e( 'The (typically) 16 digits on the front of your credit card.', 'edd-gateway-stripe' ); ?></span>
				<div id="stripe-card-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
				<i class="stripe-credit-card-brand stripe-card-brand" alt="Credit Card"></i>
			</p>

			<?php do_action( 'edd_before_cc_expiration' ); ?>
			<p class="card-expiration">
				<label for="card_exp_month" class="edd-label">
					<?php _e( 'Expiration (MM/YY)', 'edd-gateway-stripe' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The date your credit card expires, typically on the front of the card.', 'edd-gateway-stripe' ); ?></span>
				<div id="stripe-exp-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>
			<?php do_action( 'edd_after_cc_expiration' ); ?>

			<p id="edd-card-cvc-wrap">
				<label for="card_cvc" class="edd-label">
					<?php _e( 'CVC', 'edd-gateway-stripe' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The 3 digit (back) or 4 digit (front) value on your card.', 'edd-gateway-stripe' ); ?></span>
				<div id="stripe-cvc-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>

		</fieldset>
		<?php
		do_action( 'edd_after_cc_fields' );

		echo ob_get_clean();
	}

	/**
	 * Cancel pre-auth on refund/cancellation.
	 * @param  [type] $payment_id [description]
	 * @param  [type] $new_status [description]
	 * @param  [type] $old_status [description]
	 * @return [type]             [description]
	 */
	public function cancel_purchase( $payment_id, $new_status, $old_status ) {
		// If the payment was not in publish or revoked status, don't decrement stats as they were never incremented
		if ( ( 'publish' != $old_status && 'revoked' != $old_status ) || 'refunded' != $new_status ) {
			return;
		}

		if ( 'stripe' !== edd_get_payment_gateway( $payment_id ) ) {
			return;
		}

		// If not captured, refund.
		if ( 'no' === edd_get_payment_meta( $payment_id, '_stripe_charge_captured', true ) ) {
			$this->process_refund( $payment_id );
		}

		// This hook fires when admin manually changes order status to cancel.
		do_action( 'edd_stripe_process_manual_cancel', $order );
	}

	/**
	 * [prepare_pay_page description]
	 * @param  [type] $gateways [description]
	 * @return [type]           [description]
	 */
	public function prepare_pay_page( $gateways ) {
		if ( ! edd_is_checkout() || ! isset( $_GET['edd-stripe-confirmation'] ) ) { // wpcs: csrf ok.
			return $gateways;
		}

		remove_all_actions( 'edd_purchase_form_after_cc_form' );
		remove_all_actions( 'edd_purchase_form_after_user_info' );
		remove_all_actions( 'edd_purchase_form_register_fields' );
		remove_all_actions( 'edd_purchase_form_login_fields' );
		remove_all_actions( 'edd_register_fields_before' );
		remove_all_actions( 'edd_cc_form' );
		remove_all_actions( 'edd_checkout_form_top' );

		// echo edd_get_chosen_gateway();

		add_filter( 'edd_enabled_payment_gateways', '__return_empty_array' );
		add_action( 'edd_can_checkout', '__return_false' );

		return array();
	}

}

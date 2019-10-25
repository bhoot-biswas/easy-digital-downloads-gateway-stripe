<?php
namespace BengalStudio\EDD\Stripe\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use BengalStudio\EDD\Stripe\API;
use BengalStudio\EDD\Stripe\Loader;

/**
 * GatewayStripe class.
 */
class Stripe {

	/**
	 * Define variables.
	 * @var string
	 */
	public $gateway_id = 'stripe';
	public $is_setup   = null;

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
		// Run this separate so we can ditch as early as possible
		$this->register();

		if ( ! edd_is_gateway_active( $this->gateway_id ) ) {
			return;
		}

		$this->setup();
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
	 * [setup description]
	 * @return [type] [description]
	 */
	public function setup() {
		if ( ! $this->is_setup() ) {
			return;
		}

		$config = array(
			'secret_key'      => edd_is_test_mode() ? edd_get_option( 'stripe_test_secret_key', '' ) : edd_get_option( 'stripe_secret_key', '' ),
			'publishable_key' => edd_is_test_mode() ? edd_get_option( 'stripe_test_publishable_key', '' ) : edd_get_option( 'stripe_publishable_key', '' ),
		);

		API::set_secret_key( $config['secret_key'] );
	}

	/**
	 * Add filters.
	 * @return [type] [description]
	 */
	public function filters() {
		if ( is_admin() ) {
			add_filter( 'edd_settings_sections_gateways', array( $this, 'register_gateway_section' ), 1, 1 );
			add_filter( 'edd_settings_gateways', array( $this, 'register_gateway_settings' ), 1, 1 );
			add_action( 'edd_stripe_cc_form', array( $this, 'get_cc_form' ) );
		}
	}

	/**
	 * Register the payment gateways setting section.
	 * @param  [type] $gateway_sections [description]
	 * @return [type]                   [description]
	 */
	public function register_gateway_section( $gateway_sections ) {
		$gateway_sections['stripe'] = __( 'Stripe Payments', 'easy-digital-downloads' );

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
				'name' => '<strong>' . __( 'Stripe Payments Settings', 'easy-digital-downloads' ) . '</strong>',
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
	 * Add actions.
	 * @return [type] [description]
	 */
	public function actions() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
	}

	/**
	 * [is_setup description]
	 * @return boolean [description]
	 */
	public function is_setup() {
		if ( null !== $this->is_setup ) {
			return $this->is_setup;
		}

		$required_items = array( 'secret_key', 'publishable_key' );

		$current_values = array(
			'secret_key'      => edd_is_test_mode() ? edd_get_option( 'stripe_test_secret_key', '' ) : edd_get_option( 'stripe_secret_key', '' ),
			'publishable_key' => edd_is_test_mode() ? edd_get_option( 'stripe_test_publishable_key', '' ) : edd_get_option( 'stripe_publishable_key', '' ),
		);

		$this->is_setup = true;

		foreach ( $required_items as $key ) {
			if ( empty( $current_values[ $key ] ) ) {
				$this->is_setup = false;
				break;
			}
		}

		return $this->is_setup;
	}

	/**
	 * Register scripts.
	 * @return [type] [description]
	 */
	public function register_scripts() {
		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script(
			'edd_stripe',
			Loader::get_url( 'stripe.js' ),
			array( 'stripe' ),
			Loader::get_file_version( 'stripe.js' ),
			true
		);
	}

	/**
	 * Load scripts.
	 * @return [type] [description]
	 */
	public function load_scripts() {
		wp_enqueue_script( 'edd_stripe' );
	}

	/**
	 * Process the purchase and create the charge in Stripe.
	 * @param  [type] $purchase_data [description]
	 * @return [type]                [description]
	 */
	public function process_purchase( $purchase_data ) {
		edd_debug_log( 'PayPal IPN endpoint loaded' );
		edd_set_error( 'missing_reference_id', __( 'Missing Reference ID, please try again', 'edd-gateway-stripe' ) );
		$errors = edd_get_errors();
		if ( $errors ) {
			edd_send_back_to_checkout( '?payment-mode=stripe' );
		}
	}

	/**
	 * [get_cc_form description]
	 * @return [type] [description]
	 */
	public function get_cc_form() {
		ob_start(); ?>

		<?php do_action( 'edd_before_cc_fields' ); ?>

		<fieldset id="edd_cc_fields" class="edd-do-validate">
			<legend><?php _e( 'Credit Card Info', 'easy-digital-downloads' ); ?></legend>
			<?php if ( is_ssl() ) : ?>
				<div id="edd_secure_site_wrapper">
					<span class="padlock">
						<svg class="edd-icon edd-icon-lock" xmlns="http://www.w3.org/2000/svg" width="18" height="28" viewBox="0 0 18 28" aria-hidden="true">
							<path d="M5 12h8V9c0-2.203-1.797-4-4-4S5 6.797 5 9v3zm13 1.5v9c0 .828-.672 1.5-1.5 1.5h-15C.672 24 0 23.328 0 22.5v-9c0-.828.672-1.5 1.5-1.5H2V9c0-3.844 3.156-7 7-7s7 3.156 7 7v3h.5c.828 0 1.5.672 1.5 1.5z"/>
						</svg>
					</span>
					<span><?php _e( 'This is a secure SSL encrypted payment.', 'easy-digital-downloads' ); ?></span>
				</div>
			<?php endif; ?>
			<p id="edd-card-number-wrap">
				<label for="card_number" class="edd-label">
					<?php _e( 'Card Number', 'easy-digital-downloads' ); ?>
					<span class="edd-required-indicator">*</span>
					<span class="card-type"></span>
				</label>
				<span class="edd-description"><?php _e( 'The (typically) 16 digits on the front of your credit card.', 'easy-digital-downloads' ); ?></span>
				<div id="stripe-card-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>

			<?php do_action( 'edd_before_cc_expiration' ); ?>
			<p class="card-expiration">
				<label for="card_exp_month" class="edd-label">
					<?php _e( 'Expiration (MM/YY)', 'easy-digital-downloads' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The date your credit card expires, typically on the front of the card.', 'easy-digital-downloads' ); ?></span>
				<div id="stripe-exp-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>
			<?php do_action( 'edd_after_cc_expiration' ); ?>

			<p id="edd-card-cvc-wrap">
				<label for="card_cvc" class="edd-label">
					<?php _e( 'CVC', 'easy-digital-downloads' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The 3 digit (back) or 4 digit (front) value on your card.', 'easy-digital-downloads' ); ?></span>
				<div id="stripe-cvc-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>

		</fieldset>
		<?php
		// do_action( 'edd_after_cc_fields' );

		echo ob_get_clean();
	}

}

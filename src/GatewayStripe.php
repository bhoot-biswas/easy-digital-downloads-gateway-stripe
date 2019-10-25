<?php
namespace BengalStudio\EDD\GatewayStripe;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * GatewayStripe class.
 */
class GatewayStripe {

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
			'stripe_testmode'             => array(
				'id'   => 'stripe_testmode',
				'name' => __( 'Enable Test Mode', 'woocommerce-gateway-stripe' ),
				'desc' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-stripe' ),
				'type' => 'checkbox',
				'std'  => 1,
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

	}

	/**
	 * [is_setup description]
	 * @return boolean [description]
	 */
	public function is_setup() {
		if ( null !== $this->is_setup ) {
			return $this->is_setup;
		}

		return $this->is_setup;
	}

	/**
	 * Retrieve the URL for connecting Amazon account to EDD
	 * @return [type] [description]
	 */
	private function get_registration_url() {
		$base_url = 'https://payments.amazon.com/register';

		$query_args = array(
			'registration_source' => 'SPPD',
			'spId'                => 'A3JST9YM1SX7LB',
		);

		return add_query_arg( $query_args, $base_url );
	}
}

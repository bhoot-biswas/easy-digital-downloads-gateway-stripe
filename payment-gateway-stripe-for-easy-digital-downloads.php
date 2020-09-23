<?php
/**
 * Plugin Name:     Payment Gateway Stripe for Easy Digital Downloads
 * Plugin URI:      https://bengal-studio.com/plugins/payment-gateway-stripe-for-easy-digital-downloads/
 * Description:     Take credit card payments on your store using Stripe.
 * Author:          Bengal Studio
 * Author URI:      https://bengal-studio.com
 * Text Domain:     payment-gateway-stripe
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         EDD_Gateway_Stripe
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EDD_GATEWAY_STRIPE_PLUGIN_FILE' ) ) {
	define( 'EDD_GATEWAY_STRIPE_PLUGIN_FILE', __FILE__ );
}

// Include the main EDD_Gateway_Stripe class.
if ( ! class_exists( 'EDD_Gateway_Stripe', false ) ) {
	include_once dirname( EDD_GATEWAY_STRIPE_PLUGIN_FILE ) . '/includes/class-edd-gateway-stripe.php';
}

/**
 * The main function responsible for returning the one true EDD_Gateway_Stripe
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd_gateway_stripe = edd_gateway_stripe(); ?>
 *
 * @since  1.0.0
 *
 * @return object The one true EDD_Gateway_Stripe Instance
 */
function edd_gateway_stripe() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {

		if ( ! class_exists( 'EDD_Gateway_Stripe_Extension_Activation' ) ) {
			include_once 'includes/class-edd-gateway-stripe-extension-activation.php';
		}

		$activation = new EDD_Gateway_Stripe_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
		$activation = $activation->run();

	} else {
		return EDD_Gateway_Stripe::instance();
	}
}

add_action( 'plugins_loaded', 'edd_gateway_stripe', 100 );

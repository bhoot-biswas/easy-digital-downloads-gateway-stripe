<?php
/**
 * Plugin Name:     Payment Gateway Stripe for Easy Digital Downloads
 * Plugin URI:      https://bengal-studio.com/plugins/payment-gateway-stripe-for-easy-digital-downloads/
 * Description:     Take credit card payments on your store using Stripe.
 * Author:          Bengal Studio
 * Author URI:      https://bengal-studio.com
 * Text Domain:     edd-gateway-stripe
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         EDD_Stripe
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EDD_STRIPE_PLUGIN_FILE' ) ) {
	define( 'EDD_STRIPE_PLUGIN_FILE', __FILE__ );
}

// Include the main EDD_Stripe class.
if ( ! class_exists( 'EDD_Stripe', false ) ) {
	include_once dirname( EDD_STRIPE_PLUGIN_FILE ) . '/includes/class-edd-stripe.php';
}

/**
 * The main function responsible for returning the one true EDD_Stripe
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd_gateway_stripe = edd_gateway_stripe(); ?>
 *
 * @since  1.0.0
 *
 * @return object The one true EDD_Stripe Instance
 */
function edd_stripe() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {

		if ( ! class_exists( 'EDD_Stripe_Extension_Activation' ) ) {
			include_once 'includes/class-edd-stripe-extension-activation.php';
		}

		$activation = new EDD_Stripe_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
		$activation = $activation->run();

	} else {
		return EDD_Stripe::instance();
	}
}

add_action( 'plugins_loaded', 'edd_stripe', 100 );

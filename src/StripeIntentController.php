<?php
namespace BengalStudio\EDD\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;

/**
 * StripeIntentController class.
 *
 * Handles in-checkout AJAX calls, related to Payment Intents.
 */
class StripeIntentController {
	/**
	 * Holds an instance of the gateway class.
	 * @var [type]
	 */
	protected $gateway;

	/**
	 * Class constructor, adds the necessary hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_edd_stripe_verify_intent', array( $this, 'verify_intent' ) );
	}

	/**
	 * Returns an instantiated gateway.
	 * @return [type] [description]
	 */
	protected function get_gateway() {
		if ( ! isset( $this->gateway ) ) {
			$this->gateway = Gateways\Stripe::instance();
		}

		return $this->gateway;
	}

	/**
	 * Loads the payment from the current request.
	 * @return [type] [description]
	 */
	protected function get_payment_id_from_request() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'edd_stripe_confirm_pi' ) ) {
			throw new Exception( 'missing-nonce', __( 'CSRF verification failed.', 'payment-gateway-stripe' ) );
		}

		// Load the payment ID.
		$payment_id = 0;
		if ( isset( $_GET['payment_id'] ) && absint( $_GET['payment_id'] ) ) {
			$payment_id = absint( $_GET['payment_id'] );
		}

		if ( ! $payment_id ) {
			throw new Exception( 'missing-payment', __( 'Missing payment ID for payment confirmation', 'payment-gateway-stripe' ) );
		}

		return $payment_id;
	}

	/**
	 * Handles successful PaymentIntent authentications.
	 * @return [type] [description]
	 */
	public function verify_intent() {
		// Get gateway.
		$gateway = $this->get_gateway();

		try {
			$payment_id = $this->get_payment_id_from_request();
		} catch ( Exception $e ) {
			/* translators: Error message text */
			$message = sprintf( __( 'Payment verification error: %s', 'payment-gateway-stripe' ), $e->getLocalizedMessage() );
			// wc_add_notice( esc_html( $message ), 'error' );

			$redirect_url = EDD()->cart->is_empty() ? get_home_url() : edd_get_checkout_uri();

			$this->handle_error( $e, $redirect_url );
		}

		try {
			$gateway->verify_intent_after_checkout( $payment_id );

			if ( ! isset( $_GET['is_ajax'] ) ) {
				$redirect_url = isset( $_GET['redirect_to'] ) // wpcs: csrf ok.
					? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) // wpcs: csrf ok.
					: edd_get_success_page_uri();

				wp_safe_redirect( $redirect_url );
			}

			exit;
		} catch ( Exception $e ) {
			$this->handle_error( $e, edd_get_checkout_uri() );
		}
	}

	/**
	 * Handles exceptions during intent verification.
	 * @param  [type] $e            [description]
	 * @param  [type] $redirect_url [description]
	 * @return [type]               [description]
	 */
	protected function handle_error( $e, $redirect_url ) {
		// Log the exception before redirecting.
		$message = sprintf( 'PaymentIntent verification exception: %s', $e->getLocalizedMessage() );
		edd_debug_log( $message );

		// `is_ajax` is only used for PI error reporting, a response is not expected.
		if ( isset( $_GET['is_ajax'] ) ) {
			exit;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}
}

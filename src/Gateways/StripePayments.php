<?php
namespace BengalStudio\EDD\Stripe\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use BengalStudio\EDD\Stripe\StripeAPI;
use BengalStudio\EDD\Stripe\StripeCustomer;
use BengalStudio\EDD\Stripe\StripeHelper;
use EDD_Payment;
use EDD_Customer;

abstract class StripePayments {
	/**
	 * Get payment source. This can be a new token/source or existing token.
	 * If user is logged in and/or has EDD account, create an account on Stripe.
	 * This way we can attribute the payment to the user to better fight fraud.
	 * @param  [type]  $payment_id        [description]
	 * @param  [type]  $purchase_data     [description]
	 * @param  boolean $force_save_source [description]
	 * @return [type]                     [description]
	 */
	public function prepare_source( $payment_id, $purchase_data, $force_save_source = false ) {
		$edd_payment_customer_id = edd_get_payment_meta( $payment_id, '_edd_payment_customer_id' );
		$customer                = new StripeCustomer( $edd_payment_customer_id );
		$source_id               = '';
		$source_object           = '';

		if ( ! empty( $purchase_data['post_data']['stripe_source'] ) ) {
			$source_object = self::get_source_object( $purchase_data['post_data']['stripe_source'] );
			$source_id     = $source_object->id;
		}

		$customer_id = $customer->get_id();
		if ( ! $customer_id ) {
			$customer->set_id( $customer->create_customer() );
			$customer_id = $customer->get_id();
		}

		return (object) array(
			'customer'        => $customer_id,
			'customer_object' => $customer,
			'source'          => $source_id,
			'source_object'   => $source_object,
		);
	}

	/**
	 * Get source object by source id.
	 * @param  string $source_id [description]
	 * @return [type]            [description]
	 */
	public function get_source_object( $source_id = '' ) {
		if ( empty( $source_id ) ) {
			return '';
		}

		$source_object = StripeAPI::retrieve( 'sources/' . $source_id );

		if ( ! empty( $source_object->error ) ) {
			throw new Exception( print_r( $source_object, true ), $source_object->error->message );
		}

		return $source_object;
	}

	/**
	 * Retrieves the payment intent, associated with an payment.
	 * @param  [type] $payment_id [description]
	 * @return [type]             [description]
	 */
	public function get_payment_intent( $payment_id ) {
		$intent_id = edd_get_payment_meta( $payment_id, '_stripe_intent_id', true );
		if ( ! $intent_id ) {
			return false;
		}

		return StripeAPI::request( array(), "payment_intents/$intent_id", 'GET' );
	}

	/**
	 * Create a new payment intent.
	 * @param  [type] $payment_id      [description]
	 * @param  [type] $prepared_source [description]
	 * @param  [type] $purchase_data   [description]
	 * @return [type]                  [description]
	 */
	public function create_payment_intent( $payment_id, $prepared_source, $purchase_data ) {
		$customer           = new EDD_Customer( $prepared_source->customer_object->get_customer_id() );
		$names              = explode( ' ', $customer->name );
		$billing_first_name = ! empty( $names[0] ) ? $names[0] : '';
		$billing_last_name  = '';
		if ( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$billing_last_name = implode( ' ', $names );
		}

		$args = array(
			'source'               => $prepared_source->source,
			'amount'               => StripeHelper::get_stripe_amount( $purchase_data['price'] ),
			'currency'             => edd_get_currency(),
			/* translators: 1) blog name 2) payment id */
			'description'          => sprintf( __( '%1$s - Order %2$s', 'edd-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $payment_id ),
			'metadata'             => array(
				__( 'customer_name', 'edd-gateway-stripe' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
				__( 'customer_email', 'edd-gateway-stripe' ) => sanitize_email( $customer->email ),
				'payment_id' => $payment_id,
			),
			'capture_method'       => edd_get_option( 'stripe_capture', false ) ? 'automatic' : 'manual',
			'payment_method_types' => array(
				'card',
			),
		);

		if ( $prepared_source->customer ) {
			$args['customer'] = $prepared_source->customer;
		}

		// Create an intent that awaits an action.
		$payment_intent = StripeAPI::request( $args, 'payment_intents' );
		if ( ! empty( $payment_intent->error ) ) {
			return $payment_intent;
		}

		// Save the intent ID to the payment.
		edd_update_payment_meta( $payment_id, '_stripe_intent_id', $payment_intent->id );

		return $payment_intent;
	}

	/**
	 * Updates an existing intent with updated amount, source, and customer.
	 * @param  [type] $payment_intent  [description]
	 * @param  [type] $prepared_source [description]
	 * @param  [type] $purchase_data   [description]
	 * @return [type]                  [description]
	 */
	public function update_payment_intent( $payment_intent, $prepared_source, $purchase_data ) {
		$request = array();

		if ( $prepared_source->source !== $payment_intent->source ) {
			$request['source'] = $prepared_source->source;
		}

		$updated_amount = StripeHelper::get_stripe_amount( $purchase_data['price'] );
		if ( $payment_intent->amount !== $updated_amount ) {
			$request['amount'] = $updated_amount;
		}

		if ( $prepared_source->customer && ( $payment_intent->customer !== $prepared_source->customer ) ) {
			$request['customer'] = $prepared_source->customer;
		}

		if ( empty( $request ) ) {
			return $intent;
		}

		return StripeAPI::request( $request, "payment_intents/$payment_intent->id" );
	}

	/**
	 * Confirms an intent if it is the `requires_confirmation` state.
	 * @param  [type] $payment_intent  [description]
	 * @param  [type] $payment_id      [description]
	 * @param  [type] $prepared_source [description]
	 * @return [type]                  [description]
	 */
	public function confirm_payment_intent( $payment_intent, $payment_id, $prepared_source ) {
		// Exit early.
		if ( 'requires_confirmation' !== $payment_intent->status ) {
			return $payment_intent;
		}

		// Try to confirm the intent & capture the charge (if 3DS is not required).
		$request = array(
			'source' => $prepared_source->source,
		);

		$payment_intent_confirmed = StripeAPI::request( $request, "payment_intents/$payment_intent->id/confirm" );
		if ( ! empty( $payment_intent_confirmed->error ) ) {
			return $payment_intent_confirmed;
		}

		// Save a note about the status of the intent.
		if ( 'succeeded' === $payment_intent_confirmed->status ) {
			edd_debug_log( "Stripe PaymentIntent $payment_intent->id succeeded for order $payment_id" );
		} elseif ( 'requires_action' === $payment_intent_confirmed->status ) {
			edd_debug_log( "Stripe PaymentIntent $payment_intent->id requires authentication for order $payment_id" );
		}

		return $payment_intent_confirmed;
	}

}

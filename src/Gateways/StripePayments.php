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
			'customer_id'     => $customer_id,
			'source_id'       => $source_id,
			'customer_object' => $customer,
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
	 * Create a new PaymentIntent.
	 * @param  [type] $payment_id      [description]
	 * @param  [type] $prepared_source [description]
	 * @param  [type] $purchase_data   [description]
	 * @return [type]                  [description]
	 */
	public function create_intent( $payment_id, $prepared_source, $purchase_data ) {
		$customer           = new EDD_Customer( $prepared_source->customer_object->get_customer_id() );
		$names              = explode( ' ', $customer->name );
		$billing_first_name = ! empty( $names[0] ) ? $names[0] : '';
		$billing_last_name  = '';
		if ( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$billing_last_name = implode( ' ', $names );
		}

		$args = array(
			'source'               => $prepared_source->source_id,
			'amount'               => StripeHelper::get_stripe_amount( $purchase_data['price'] ),
			'currency'             => edd_get_currency(),
			/* translators: 1) blog name 2) payment id */
			'description'          => sprintf( __( '%1$s - Order %2$s', 'edd-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $payment_id ),
			'metadata'             => array(
				__( 'customer_name', 'edd-gateway-stripe' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
				__( 'customer_email', 'edd-gateway-stripe' ) => sanitize_email( $customer->email ),
				'payment_id' => $payment_id,
			),
			'capture_method'       => ( 'true' === edd_get_option( 'stripe_capture', true ) ) ? 'automatic' : 'manual',
			'payment_method_types' => array(
				'card',
			),
		);

		if ( $prepared_source->customer_id ) {
			$args['customer'] = $prepared_source->customer_id;
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

}

<?php
namespace BengalStudio\EDD\Stripe\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use BengalStudio\EDD\Stripe\StripeAPI;
use BengalStudio\EDD\Stripe\StripeCustomer;
use BengalStudio\EDD\Stripe\StripeHelper;
use EDD_Payment;
use EDD_Customer;
use Exception;

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

	/**
	 * Store extra meta data for an order from a Stripe Response.
	 * @param  [type] $response   [description]
	 * @param  [type] $payment_id [description]
	 * @return [type]             [description]
	 */
	public function process_response( $response, $payment_id ) {
		// edd_debug_log( 'Processing response: ' . print_r( $response, true ) );

		$captured = ( isset( $response->captured ) && $response->captured ) ? 'yes' : 'no';

		// Store charge data.
		edd_update_payment_meta( $payment_id, '_stripe_charge_captured', $captured );

		if ( 'yes' === $captured ) {
			/**
			 * Charge can be captured but in a pending state. Payment methods
			 * that are asynchronous may take couple days to clear. Webhook will
			 * take care of the status changes.
			 */
			if ( 'pending' === $response->status ) {
				edd_update_payment_status( $payment_id, 'pending' );

				/* translators: transaction id */
				$message = sprintf( __( 'Stripe charge awaiting payment: %s.', 'edd-gateway-stripe' ), $response->id );
				edd_insert_payment_note( $payment_id, $message );
			}

			if ( 'succeeded' === $response->status ) {
				edd_set_payment_transaction_id( $payment_id, $response->id );
				edd_update_payment_status( $payment_id, 'publish' );

				/* translators: transaction id */
				$message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'edd-gateway-stripe' ), $response->id );
				edd_insert_payment_note( $payment_id, $message );
			}

			if ( 'failed' === $response->status ) {
				$localized_message = __( 'Payment processing failed. Please retry.', 'edd-gateway-stripe' );
				edd_insert_payment_note( $payment_id, $localized_message );

				// Problems? send back
				edd_send_back_to_checkout( '?payment-mode=' . $this->gateway_id );
			}
		} else {
			edd_update_payment_status( $payment_id, 'publish' );
			/* translators: transaction id */
			edd_insert_payment_note( $payment_id, sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'edd-gateway-stripe' ), $response->id ) );
		}

		do_action( 'edd_gateway_stripe_process_response', $response, $payment_id );

		return $response;
	}

	/**
	 * Refund a charge.
	 * @param  [type] $order_id [description]
	 * @param  [type] $amount   [description]
	 * @param  string $reason   [description]
	 * @return [type]           [description]
	 */
	public function process_refund( $payment_id, $amount = null, $reason = '' ) {
		$payment = edd_get_payment( $payment_id );

		if ( ! $payment ) {
			return false;
		}

		$request = array();

		$payment_currency = $payment->currency;
		$captured         = $payment->get_meta( '_stripe_charge_captured', true );
		$charge_id        = $payment->transaction_id;

		if ( ! $charge_id ) {
			return false;
		}

		if ( ! is_null( $amount ) ) {
			$request['amount'] = StripeHelper::get_stripe_amount( $amount, $payment_currency );
		}

		// If order is only authorized, don't pass amount.
		if ( 'yes' !== $captured ) {
			unset( $request['amount'] );
		}

		if ( $reason ) {
			$request['metadata'] = array(
				'reason' => $reason,
			);
		}

		$request['charge'] = $charge_id;
		edd_debug_log( "Info: Beginning refund for order {$charge_id} for the amount of {$amount}" );

		$request = apply_filters( 'edd_stripe_refund_request', $request, $payment );

		$payment_intent           = $this->get_payment_intent( $payment_id );
		$payment_intent_cancelled = false;
		if ( $payment_intent ) {
			// If the order has a Payment Intent pending capture, then the Intent itself must be refunded (cancelled), not the Charge
			if ( ! empty( $payment_intent->error ) ) {
				$response                 = $payment_intent;
				$payment_intent_cancelled = true;
			} elseif ( 'requires_capture' === $payment_intent->status ) {
				$result                   = StripeAPI::request(
					array(),
					'payment_intents/' . $payment_intent->id . '/cancel'
				);
				$payment_intent_cancelled = true;

				if ( ! empty( $result->error ) ) {
					$response = $result;
				} else {
					$charge   = end( $result->charges->data );
					$response = end( $charge->refunds->data );
				}
			}
		}

		if ( ! $payment_intent_cancelled ) {
			$response = StripeAPI::request( $request, 'refunds' );
		}

		if ( ! empty( $response->error ) ) {
			edd_debug_log( 'Error: ' . $response->error->message );

			return $response;

		} elseif ( ! empty( $response->id ) ) {
			$payment->update_meta( '_stripe_refund_id', $response->id );

			$amount = $response->amount;

			if ( in_array( strtolower( $payment->currency ), StripeHelper::no_decimal_currencies() ) ) {
				$amount = $response->amount;
			}

			// if ( isset( $response->balance_transaction ) ) {
			// 	$this->update_fees( $payment, $response->balance_transaction );
			// }

			/* translators: 1) dollar amount 2) transaction id 3) refund message */
			$refund_message = ( isset( $captured ) && 'yes' === $captured ) ? sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'edd-gateway-stripe' ), $amount, $response->id, $reason ) : __( 'Pre-Authorization Released', 'edd-gateway-stripe' );

			$payment->add_note( $refund_message );
			edd_debug_log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );

			return true;
		}
	}

}

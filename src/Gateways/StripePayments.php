<?php
namespace BengalStudio\EDD\Stripe\Gateways;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use BengalStudio\EDD\Stripe\StripeAPI;
use BengalStudio\EDD\Stripe\StripeCustomer;

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
			'customer'      => $customer_id,
			'source'        => $source_id,
			'source_object' => $source_object,
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
}

<?php
namespace BengalStudio\EDD\Stripe;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use EDD_Customer;

/**
 * StripeCustomer class.
 */
class StripeCustomer {

	/**
	 * Stripe customer ID.
	 * @var [type]
	 */
	private $id = '';

	/**
	 * EDD customer ID.
	 * @var [type]
	 */
	private $customer_id = 0;

	/**
	 * Data from API
	 * @var [type]
	 */
	private $customer_data = array();

	/**
	 * Constructor.
	 * @param integer $customer_id [description]
	 */
	public function __construct( $customer_id = 0 ) {
		if ( $customer_id ) {
			$this->set_customer_id( $customer_id );
			$this->set_id( EDD()->customer_meta->get_meta( $customer_id, '_stripe_customer_id', true ) );
		}
	}

	/**
	 * Get Stripe customer ID.
	 * @return [type] [description]
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set Stripe customer ID.
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		// EDD()->customer_meta->update_meta( $this->get_customer_id(), '_stripe_customer_id', $id );
		$this->id = $id;
	}

	/**
	 * Customer ID in Easy Digital Downloads.
	 * @return [type] [description]
	 */
	public function get_customer_id() {
		return absint( $this->customer_id );
	}

	/**
	 * Set CustomerID used by Easy Digital Downloads.
	 * @param [type] $customer_id [description]
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = absint( $customer_id );
	}

	/**
	 * Get customer object.
	 * @return [type] [description]
	 */
	protected function get_customer() {
		return $this->get_customer_id() ? new EDD_Customer( $this->get_customer_id() ) : false;
	}

	/**
	 * Create a customer via API.
	 * @param  array  $args [description]
	 * @return [type]       [description]
	 */
	public function create_customer( $args = array() ) {
		$customer           = $this->get_customer();
		$names              = explode( ' ', $customer->name );
		$billing_first_name = ! empty( $names[0] ) ? $names[0] : '';
		$billing_last_name  = '';
		if ( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$billing_last_name = implode( ' ', $names );
		}

		// translators: %1$s First name, %2$s Second name, %3$s EDD customer ID.
		$description = sprintf( __( 'Name: %1$s %2$s, EDD customer ID: %3$s', 'payment-gateway-stripe' ), $billing_first_name, $billing_last_name, $customer->id );

		$defaults = array(
			'email'       => $customer->email,
			'description' => $description,
		);

		$metadata = array();

		$defaults['metadata'] = apply_filters( 'wc_stripe_customer_metadata', $metadata, $customer );

		$args     = wp_parse_args( $args, $defaults );
		$response = StripeAPI::request( apply_filters( 'wc_stripe_create_customer_args', $args ), 'customers' );

		if ( ! empty( $response->error ) ) {
			edd_debug_log( print_r( $response, true ) );
		}

		$this->set_id( $response->id );
		// $this->clear_cache();
		$this->set_customer_data( $response );

		EDD()->customer_meta->update_meta( $this->get_customer_id(), '_stripe_customer_id', $response->id );

		do_action( 'woocommerce_stripe_add_customer', $args, $response );

		return $response->id;
	}

	/**
	 * Store data from the Stripe API about this customer
	 */
	public function set_customer_data( $data ) {
		$this->customer_data = $data;
	}
}

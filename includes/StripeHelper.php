<?php
namespace BengalStudio\EDD\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 */
class StripeHelper {
	/**
	 * Get Stripe amount to pay.
	 * @param  [type] $total    [description]
	 * @param  string $currency [description]
	 * @return [type]           [description]
	 */
	public static function get_stripe_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = edd_get_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies() ) ) {
			return absint( $total );
		} else {
			return round( ( (float) $total * 100 ), edd_currency_decimal_filter() );
		}
	}

	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://stripe.com/docs/currencies#zero-decimal from https://stripe.com/docs/currencies#presentment-currencies
	 * @return [type] [description]
	 */
	public static function no_decimal_currencies() {
		return array(
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'ugx', // Ugandan Shilling
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		);
	}
}

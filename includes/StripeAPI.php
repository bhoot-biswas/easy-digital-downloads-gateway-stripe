<?php


namespace BengalStudio\EDD\Stripe;

class StripeAPI {
	/**
	 * Stripe API Endpoint
	 */
	const ENDPOINT           = 'https://api.stripe.com/v1/';
	const STRIPE_API_VERSION = '2019-09-09';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			self::set_secret_key( edd_is_test_mode() ? edd_get_option( 'stripe_test_secret_key', '' ) : edd_get_option( 'stripe_secret_key', '' ) );
		}

		return self::$secret_key;
	}

	/**
	 * Generates the user agent we use to pass to API request so
	 * Stripe can identify our application.
	 * @return [type] [description]
	 */
	public static function get_user_agent() {
		return array(
			'lang'         => 'php',
			'lang_version' => phpversion(),
			'uname'        => php_uname(),
			'application'  => array(
				'name'    => 'EDD Stripe Gateway',
				'version' => EDD_GATEWAY_STRIPE_VERSION_NUMBER,
				'url'     => 'https://bengal-studio.com/products/edd-stripe-gateway/',
			),
		);
	}

	/**
	 * Generates the headers to pass to API request.
	 */
	public static function get_headers() {
		// Get user agent.
		$user_agent = self::get_user_agent();
		$app_info   = $user_agent['application'];

		// Return headers.
		return apply_filters(
			'edd_stripe_request_headers',
			array(
				'Authorization'              => 'Basic ' . base64_encode( self::get_secret_key() . ':' ),
				'Stripe-Version'             => self::STRIPE_API_VERSION,
				'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
				'X-Stripe-Client-User-Agent' => json_encode( $user_agent ),
			)
		);
	}

	/**
	 * Send the request to Stripe's API
	 * @param  [type]  $request      [description]
	 * @param  string  $api          [description]
	 * @param  string  $method       [description]
	 * @param  boolean $with_headers [description]
	 * @return [type]                [description]
	 */
	public static function request( $request, $api = 'charges', $method = 'POST', $with_headers = false ) {
		$headers         = self::get_headers();
		$idempotency_key = '';

		if ( 'charges' === $api && 'POST' === $method ) {
			$customer        = ! empty( $request['customer'] ) ? $request['customer'] : '';
			$source          = ! empty( $request['source'] ) ? $request['source'] : $customer;
			$idempotency_key = apply_filters( 'edd_stripe_idempotency_key', $request['metadata']['order_id'] . '-' . $source, $request );

			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$response = wp_safe_remote_post(
			self::ENDPOINT . $api,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => apply_filters( 'edd_stripe_request_body', $request, $api ),
				'timeout' => 70,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			throw new Exception( print_r( $response, true ), __( 'There was a problem connecting to the Stripe API endpoint.', 'payment-gateway-stripe' ) );
		}

		if ( $with_headers ) {
			return array(
				'headers' => wp_remote_retrieve_headers( $response ),
				'body'    => json_decode( $response['body'] ),
			);
		}

		return json_decode( $response['body'] );
	}

	/**
	 * Retrieve API endpoint.
	 * @param  [type] $api [description]
	 * @return [type]      [description]
	 */
	public static function retrieve( $api ) {
		$response = wp_safe_remote_get(
			self::ENDPOINT . $api,
			array(
				'method'  => 'GET',
				'headers' => self::get_headers(),
				'timeout' => 70,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			return new WP_Error( 'stripe_error', __( 'There was a problem connecting to the Stripe API endpoint.', 'payment-gateway-stripe' ) );
		}

		return json_decode( $response['body'] );
	}
}

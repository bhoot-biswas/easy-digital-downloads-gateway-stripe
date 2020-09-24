<?php
/**
 * Stripe class.
 */
class EDD_Stripe_Gateway extends EDD_Stripe_Payment_Gateway {

	/**
	 * Gateway ID.
	 * @var string
	 */
	public $gateway_id;

	/**
	 * All the required settings have been filled out?
	 * @var [type]
	 */
	public $is_setup;

	/**
	 * API access publishable key.
	 * @var [type]
	 */
	public $publishable_key;

	/**
	 * API access secret key
	 * @var [type]
	 */
	public $secret_key;

	/**
	 * The single instance of the class.
	 * @var [type]
	 */
	private static $instance;

	/**
	 * Get class instance.
	 * @return [type] [description]
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * is not allowed to call from outside to prevent from creating multiple instances,
	 * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
	 */
	private function __construct() {
		// Update variables.
		$this->gateway_id = 'stripe';

		// Run this separate so we can ditch as early as possible
		$this->register();

		if ( ! edd_is_gateway_active( $this->gateway_id ) ) {
			return;
		}

		$this->set_api();
		$this->filters();
		$this->actions();
	}

	/**
	 * Register the payment gateway.
	 * @return [type] [description]
	 */
	private function register() {
		add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ), 1, 1 );
	}

	/**
	 * Register the gateway.
	 */
	public function register_gateway( $gateways ) {
		$default_stripe_info           = [];
		$default_stripe_info['stripe'] = array(
			'admin_label'    => __( 'Stripe', 'edd-gateway-stripe' ),
			'checkout_label' => __( 'Credit Card (Stripe)', 'edd-gateway-stripe' ),
			'supports'       => array(),
		);

		$default_stripe_info = apply_filters( 'edd_register_stripe_gateway', $default_stripe_info );
		$gateways            = array_merge( $gateways, $default_stripe_info );

		return $gateways;
	}

	/**
	 * Set Stripe API key.
	 */
	public function set_api() {
		if ( ! $this->is_setup() ) {
			return;
		}

		StripeAPI::set_secret_key( $this->secret_key );
	}

	/**
	 * Add filters.
	 * @return [type] [description]
	 */
	public function filters() {
		if ( is_admin() ) {
			add_filter( 'edd_settings_sections_gateways', array( $this, 'register_gateway_section' ), 1, 1 );
			add_filter( 'edd_settings_gateways', array( $this, 'register_gateway_settings' ), 1, 1 );
		}
	}

	/**
	 * Add actions.
	 * @return [type] [description]
	 */
	public function actions() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		// add_filter( 'edd_enabled_payment_gateways', array( $this, 'prepare_pay_page' ) );
		add_action( 'edd_stripe_cc_form', array( $this, 'get_cc_form' ) );
		add_action( 'edd_after_checkout_cart', array( $this, 'remove_actions' ) );
		add_action( 'edd_checkout_error_checks', array( $this, 'checkout_error_checks' ) );
		add_action( 'edd_gateway_stripe', array( $this, 'process_purchase' ) );
		add_action( 'edd_update_payment_status', array( $this, 'cancel_purchase' ), 200, 3 );
	}

	/**
	 * Register the payment gateways setting section.
	 * @param  [type] $gateway_sections [description]
	 * @return [type]                   [description]
	 */
	public function register_gateway_section( $gateway_sections ) {
		$gateway_sections['stripe'] = __( 'Stripe Payments', 'edd-gateway-stripe' );

		return $gateway_sections;
	}

	/**
	 * Register the gateway settings.
	 * @param  [type] $gateway_settings [description]
	 * @return [type]                   [description]
	 */
	public function register_gateway_settings( $gateway_settings ) {
		$default_stripe_settings = array(
			'stripe'                      => array(
				'id'   => 'stripe',
				'name' => '<strong>' . __( 'Stripe Payments Settings', 'edd-gateway-stripe' ) . '</strong>',
				'type' => 'header',
			),
			'stripe_test_publishable_key' => array(
				'id'   => 'stripe_test_publishable_key',
				'name' => __( 'Test Publishable Key', 'edd-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'edd-gateway-stripe' ),
				'type' => 'text',
				'std'  => '',
			),
			'stripe_test_secret_key'      => array(
				'id'   => 'stripe_test_secret_key',
				'name' => __( 'Test Secret Key', 'edd-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'edd-gateway-stripe' ),
				'type' => 'password',
				'std'  => '',
			),
			'stripe_publishable_key'      => array(
				'id'   => 'stripe_publishable_key',
				'name' => __( 'Live Publishable Key', 'edd-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'edd-gateway-stripe' ),
				'type' => 'text',
				'std'  => '',
			),
			'stripe_secret_key'           => array(
				'id'   => 'stripe_secret_key',
				'name' => __( 'Live Secret Key', 'edd-gateway-stripe' ),
				'desc' => __( 'Get your API keys from your stripe account.', 'edd-gateway-stripe' ),
				'type' => 'password',
				'std'  => '',
			),
			'stripe_capture'              => array(
				'id'    => 'stripe_capture',
				'name'  => __( 'Capture', 'edd-gateway-stripe' ),
				'label' => __( 'Capture charge immediately', 'edd-gateway-stripe' ),
				'desc'  => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'edd-gateway-stripe' ),
				'type'  => 'checkbox',
				'std'   => 1,
			),
		);

		$default_stripe_settings    = apply_filters( 'edd_default_stripe_settings', $default_stripe_settings );
		$gateway_settings['stripe'] = $default_stripe_settings;

		return $gateway_settings;
	}

	/**
	 * Method to check if all the required settings have been filled out, allowing us to not output information without it.
	 * @return boolean [description]
	 */
	public function is_setup() {
		if ( null !== $this->is_setup ) {
			return $this->is_setup;
		}

		$this->secret_key      = edd_is_test_mode() ? edd_get_option( 'stripe_test_secret_key', '' ) : edd_get_option( 'stripe_secret_key', '' );
		$this->publishable_key = edd_is_test_mode() ? edd_get_option( 'stripe_test_publishable_key', '' ) : edd_get_option( 'stripe_publishable_key', '' );
		$this->is_setup        = $this->are_keys_set();

		return $this->is_setup;
	}

	/**
	 * Checks if keys are set.
	 */
	public function are_keys_set() {
		if ( empty( $this->secret_key ) || empty( $this->publishable_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register scripts.
	 * @return [type] [description]
	 */
	public function register_scripts() {
		// Exit early.
		if ( ! $this->is_setup() || ! edd_is_checkout() ) {
			return;
		}

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script(
			'edd_stripe',
			Loader::get_url( 'stripe.js' ),
			array( 'jquery', 'stripe' ),
			Loader::get_file_version( 'stripe.js' ),
			true
		);

		$stripe_params = array(
			'key' => $this->publishable_key,
		);

		wp_localize_script(
			'edd_stripe',
			'edd_stripe_params',
			apply_filters( 'edd_stripe_params', $stripe_params )
		);

		wp_register_style(
			'edd_stripe',
			Loader::get_url( 'stripe.css' ),
			array(),
			Loader::get_file_version( 'stripe.css' )
		);
	}

	/**
	 * Load scripts.
	 * @return [type] [description]
	 */
	public function load_scripts() {
		wp_enqueue_script( 'edd_stripe' );
		wp_enqueue_style( 'edd_stripe' );
	}

	/**
	 * [checkout_error_checks description]
	 * @return [type] [description]
	 */
	public function checkout_error_checks() {
		// edd_set_error( 'missing_reference_id', __( 'Missing Reference ID, please try again', 'edd-gateway-stripe' ) );
		// edd_die();
	}

	/**
	 * Process the purchase and create the charge in Stripe.
	 * @param  [type] $purchase_data [description]
	 * @return [type]                [description]
	 */
	public function process_purchase( $purchase_data ) {
		if ( empty( $purchase_data['post_data']['stripe_source'] ) ) {
			edd_set_error( 'missing_source_id', __( 'Missing Source ID, please try again', 'edd-gateway-stripe' ) );
		}

		$errors = edd_get_errors();
		if ( $errors ) {
			edd_send_back_to_checkout( '?payment-mode=stripe' );
		}

		// Collect payment data
		$payment_data = array(
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => $this->gateway_id,
			'status'       => 'pending',
		);

		// Record the pending payment
		$payment_id = edd_insert_payment( $payment_data );

		// Check payment
		if ( ! $payment_id ) {
			// Problems? send back
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		}

		// Set the session data to recover this payment in the event of abandonment or error.
		EDD()->session->set( 'edd_resume_payment', $payment_id );

		// Prepare source.
		$prepared_source = $this->prepare_source( $payment_id, $purchase_data );

		// Update or create payment intent.
		$payment_intent = $this->get_payment_intent( $payment_id );
		if ( $payment_intent ) {
			$payment_intent = $this->update_payment_intent( $payment_intent, $prepared_source, $purchase_data );
		} else {
			$payment_intent = $this->create_payment_intent( $payment_id, $prepared_source, $purchase_data );
		}

		// Confirm payment intent.
		if ( empty( $payment_intent->error ) ) {
			$payment_intent = $this->confirm_payment_intent( $payment_intent, $payment_id, $prepared_source );
		}

		if ( ! empty( $payment_intent ) ) {
			// Use the last charge within the intent to proceed.
			$response = end( $payment_intent->charges->data );

			// If the intent requires a 3DS flow, redirect to it.
			if ( 'requires_action' === $payment_intent->status ) {
				edd_send_back_to_checkout(
					array(
						'edd-stripe-confirmation' => 1,
						'payment-pay'             => $payment_id,
					)
				);
			}
		}

		// Process valid response.
		$this->process_response( $response, $payment_id );

		// Empty the shopping cart
		edd_empty_cart();
		edd_send_to_success_page();
	}

	/**
	 * [get_cc_form description]
	 * @return [type] [description]
	 */
	public function get_cc_form() {
		ob_start(); ?>

		<?php do_action( 'edd_before_cc_fields' ); ?>

		<fieldset id="edd_cc_fields" class="edd-do-validate">
			<legend><?php _e( 'Credit Card Info', 'edd-gateway-stripe' ); ?></legend>
			<?php if ( is_ssl() ) : ?>
				<div id="edd_secure_site_wrapper">
					<span class="padlock">
						<svg class="edd-icon edd-icon-lock" xmlns="http://www.w3.org/2000/svg" width="18" height="28" viewBox="0 0 18 28" aria-hidden="true">
							<path d="M5 12h8V9c0-2.203-1.797-4-4-4S5 6.797 5 9v3zm13 1.5v9c0 .828-.672 1.5-1.5 1.5h-15C.672 24 0 23.328 0 22.5v-9c0-.828.672-1.5 1.5-1.5H2V9c0-3.844 3.156-7 7-7s7 3.156 7 7v3h.5c.828 0 1.5.672 1.5 1.5z"/>
						</svg>
					</span>
					<span><?php _e( 'This is a secure SSL encrypted payment.', 'edd-gateway-stripe' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( edd_is_test_mode() ) : ?>
				<?php printf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank">Testing Stripe documentation</a> for more card numbers.', 'edd-gateway-stripe' ), 'https://stripe.com/docs/testing' ); ?>
			<?php endif; ?>
			<p id="edd-card-number-wrap">
				<label for="card_number" class="edd-label">
					<?php _e( 'Card Number', 'edd-gateway-stripe' ); ?>
					<span class="edd-required-indicator">*</span>
					<span class="card-type"></span>
				</label>
				<span class="edd-description"><?php _e( 'The (typically) 16 digits on the front of your credit card.', 'edd-gateway-stripe' ); ?></span>
				<div id="stripe-card-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
				<i class="stripe-credit-card-brand stripe-card-brand" alt="Credit Card"></i>
			</p>

			<?php do_action( 'edd_before_cc_expiration' ); ?>
			<p class="card-expiration">
				<label for="card_exp_month" class="edd-label">
					<?php _e( 'Expiration (MM/YY)', 'edd-gateway-stripe' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The date your credit card expires, typically on the front of the card.', 'edd-gateway-stripe' ); ?></span>
				<div id="stripe-exp-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>
			<?php do_action( 'edd_after_cc_expiration' ); ?>

			<p id="edd-card-cvc-wrap">
				<label for="card_cvc" class="edd-label">
					<?php _e( 'CVC', 'edd-gateway-stripe' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The 3 digit (back) or 4 digit (front) value on your card.', 'edd-gateway-stripe' ); ?></span>
				<div id="stripe-cvc-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			</p>

		</fieldset>
		<?php
		do_action( 'edd_after_cc_fields' );

		echo ob_get_clean();
	}

	/**
	 * Cancel pre-auth on refund/cancellation.
	 * @param  [type] $payment_id [description]
	 * @param  [type] $new_status [description]
	 * @param  [type] $old_status [description]
	 * @return [type]             [description]
	 */
	public function cancel_purchase( $payment_id, $new_status, $old_status ) {
		// If the payment was not in publish or revoked status, don't decrement stats as they were never incremented
		if ( ( 'publish' != $old_status && 'revoked' != $old_status ) || 'refunded' != $new_status ) {
			return;
		}

		if ( 'stripe' !== edd_get_payment_gateway( $payment_id ) ) {
			return;
		}

		// If not captured, refund.
		if ( 'no' === edd_get_payment_meta( $payment_id, '_stripe_charge_captured', true ) ) {
			$this->process_refund( $payment_id );
		}

		// This hook fires when admin manually changes order status to cancel.
		do_action( 'edd_stripe_process_manual_cancel', $order );
	}

	/**
	 * [prepare_pay_page description]
	 * @param  [type] $gateways [description]
	 * @return [type]           [description]
	 */
	public function prepare_pay_page( $gateways ) {
		if ( ! edd_is_checkout() || ! isset( $_GET['edd-stripe-confirmation'] ) ) { // wpcs: csrf ok.
			return $gateways;
		}
		//
		// remove_all_actions( 'edd_purchase_form_after_cc_form' );
		// remove_all_actions( 'edd_purchase_form_after_user_info' );
		// remove_all_actions( 'edd_purchase_form_register_fields' );
		// remove_all_actions( 'edd_purchase_form_login_fields' );
		// remove_all_actions( 'edd_register_fields_before' );
		// remove_all_actions( 'edd_cc_form' );
		// remove_all_actions( 'edd_checkout_form_top' );

		// echo edd_get_chosen_gateway();
		//
		// add_filter( 'edd_enabled_payment_gateways', '__return_empty_array' );
		// add_action( 'edd_can_checkout', '__return_false' );

		return array();
	}

	/**
	 * [remove_actions description]
	 * @return [type] [description]
	 */
	public function remove_actions() {
		if ( ! edd_is_checkout() || ! isset( $_GET['edd-stripe-confirmation'] ) ) { // wpcs: csrf ok.
			return;
		}

		try {
			$this->prepare_intent_for_payment_pay_page();
		} catch ( Exception $e ) {
			// Just show the full order pay page if there was a problem preparing the Payment Intent
			return;
		}

		remove_all_actions( 'edd_checkout_form_top' );
		remove_all_actions( 'edd_payment_mode_select' );
		remove_all_actions( 'edd_payment_mode_top' );

		add_action( 'edd_checkout_form_bottom', array( $this, 'render_payment_intent_inputs' ) );

		// add_filter( 'edd_enabled_payment_gateways', '__return_empty_array' );
		// remove_all_actions( 'edd_purchase_form_after_cc_form' );
		// remove_all_actions( 'edd_purchase_form_after_user_info' );
		// remove_all_actions( 'edd_purchase_form_register_fields' );
		// remove_all_actions( 'edd_purchase_form_login_fields' );
		// remove_all_actions( 'edd_register_fields_before' );
		// remove_all_actions( 'edd_cc_form' );
		// remove_all_actions( 'edd_checkout_form_top' );
		// remove_all_actions( 'edd_payment_mode_select' );
	}

	/**
	 * Prepares the Payment Intent for it to be completed in the "Pay for Order" page.
	 * @param  integer $payment_id [description]
	 * @return [type]              [description]
	 */
	public function prepare_intent_for_payment_pay_page( $payment_id = 0 ) {
		if ( ! $payment_id ) {
			$payment_id = absint( get_query_var( 'payment-pay' ) );
			$payment_id = absint( $_GET['payment-pay'] );
		}

		$payment_intent = $this->get_payment_intent( $payment_id );

		if ( ! $payment_intent ) {
			// throw new Exception( 'Payment Intent not found', __( 'Payment Intent not found for payment #' . $payment_id, 'woocommerce-gateway-stripe' ) );
		}

		if ( 'requires_payment_method' === $payment_intent->status && isset( $payment_intent->last_payment_error ) && 'authentication_required' === $payment_intent->last_payment_error->code ) {
			$payment_intent = StripeAPI::request(
				array(
					'payment_method' => $payment_intent->last_payment_error->source->id,
				),
				'payment_intents/' . $payment_intent->id . '/confirm'
			);

			if ( isset( $payment_intent->error ) ) {
				throw new Exception( print_r( $payment_intent, true ), $payment_intent->error->message );
			}
		}

		$this->payment_pay_intent = $payment_intent;
	}

	/**
	 * Renders hidden inputs on the "Pay for Order" page in order to let Stripe handle PaymentIntents.
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	public function render_payment_intent_inputs( $payment_id = 0 ) {
		if ( ! $payment_id ) {
			$payment_id = absint( get_query_var( 'payment-pay' ) );
			$payment_id = absint( $_GET['payment-pay'] );
		}

		if ( ! isset( $this->payment_pay_intent ) ) {
			$this->prepare_intent_for_payment_pay_page( $payment_id );
		}

		$verification_url = add_query_arg(
			array(
				'action'             => 'edd_stripe_verify_intent',
				'payment_id'         => $payment_id,
				'nonce'              => wp_create_nonce( 'edd_stripe_confirm_pi' ),
				'redirect_to'        => rawurlencode( edd_get_success_page_uri() ),
				// 'redirect_to'        => edd_get_checkout_uri(),
				'is_pay_for_payment' => true,
			),
			admin_url( 'admin-ajax.php' )
		);

		echo '<input type="hidden" id="stripe-intent-id" value="' . esc_attr( $this->payment_pay_intent->client_secret ) . '" />';
		echo '<input type="hidden" id="stripe-intent-return" value="' . esc_attr( $verification_url ) . '" />';
	}

	/**
	 * Executed between the "Checkout" and "Thank you" pages, this
	 * method updates orders based on the status of associated PaymentIntents.
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	public function verify_intent_after_checkout( $payment_id ) {
		$payment_gateway = edd_get_payment_gateway( $payment_id );
		if ( $payment_gateway !== $this->gateway_id ) {
			// If this is not the payment method, an intent would not be available.
			return;
		}

		$payment_intent = $this->get_payment_intent( $payment_id );
		if ( ! $payment_intent ) {
			// No intent, redirect to the order received page for further actions.
			return;
		}

		// A webhook might have modified or locked the order while the intent was retreived. This ensures we are reading the right status.
		clean_post_cache( $payment_id );
		$payment_status = edd_get_payment_status( $payment_id );

		edd_debug_log( $payment_status );
		edd_debug_log( print_r( $payment_intent, true ) );

		if ( 'pending' !== $payment_status && 'failed' !== $payment_status ) {
			// If payment has already been completed, this function is redundant.
			return;
		}

		if ( 'setup_intent' === $payment_intent->object && 'succeeded' === $payment_intent->status ) {
			// Empty the shopping cart
			edd_empty_cart();
			edd_update_payment_status( $payment_id, 'publish' );
		} elseif ( 'succeeded' === $payment_intent->status || 'requires_capture' === $payment_intent->status ) {
			// Proceed with the payment completion.
			$this->process_response( end( $payment_intent->charges->data ), $payment_id );
		} elseif ( 'requires_payment_method' === $payment_intent->status ) {
			// `requires_payment_method` means that SCA got denied for the current payment method.
			$this->failed_sca_auth( $payment_id, $payment_intent );
		}
	}

	/**
	 * Checks if the payment intent associated with an order failed and records the event.
	 * @param  [type] $payment_id     [description]
	 * @param  [type] $payment_intent [description]
	 * @return [type]                 [description]
	 */
	public function failed_sca_auth( $payment_id, $payment_intent ) {
		// If the order has already failed, do not repeat the same message.
		if ( 'failed' === edd_get_payment_status( $payment_id ) ) {
			return;
		}

		edd_update_payment_status( $payment_id, 'failed' );

		// Load the right message and update the status.
		$status_message = isset( $payment_intent->last_payment_error )
			/* translators: 1) The error message that was received from Stripe. */
			? sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'edd-gateway-stripe' ), $payment_intent->last_payment_error->message )
			: __( 'Stripe SCA authentication failed.', 'edd-gateway-stripe' );

		edd_insert_payment_note( $payment_id, $status_message );
	}

}

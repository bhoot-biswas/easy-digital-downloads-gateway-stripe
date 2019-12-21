<?php
/**
 * Plugin Name:     Payment Gateway Stripe for Easy Digital Downloads
 * Plugin URI:      https://bengal-studio.com/plugins/payment-gateway-stripe-for-easy-digital-downloads/
 * Description:     Take credit card payments on your store using Stripe.
 * Author:          Bengal Studio
 * Author URI:      https://bengal-studio.com
 * Text Domain:     payment-gateway-stripe
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         EDD_Gateway_Stripe
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use BengalStudio\EDD\Stripe\Plugin;

/**
 * Autoload packages.
 *
 * We want to fail gracefully if `composer install` has not been executed yet, so we are checking for the autoloader.
 * If the autoloader is not present, let's log the failure and display a nice admin notice.
 */
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
	require $autoloader;
} else {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log(  // phpcs:ignore
			sprintf(
				/* translators: 1: composer command. 2: plugin directory */
				esc_html__( 'Your installation of the Payment Gateway Stripe for Easy Digital Downloads plugin is incomplete. Please run %1$s within the %2$s directory.', 'payment-gateway-stripe' ),
				'`composer install`',
				'`' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '`'
			)
		);
	}
	/**
	 * Outputs an admin notice if composer install has not been ran.
	 */
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: 1: composer command. 2: plugin directory */
						esc_html__( 'Your installation of the Payment Gateway Stripe for Easy Digital Downloads plugin is incomplete. Please run %1$s within the %2$s directory.', 'payment-gateway-stripe' ),
						'<code>composer install</code>',
						'<code>' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '</code>'
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

Plugin::instance()->init();

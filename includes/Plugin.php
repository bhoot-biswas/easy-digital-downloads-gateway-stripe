<?php
/**
 * EDD Gateway Stripe: Feature plugin main class.
 *
 * @package EDD Gateway Stripe
 */

namespace BengalStudio\EDD\Stripe;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Plugin main class.
 *
 * @internal This file will not be bundled with woo core, only the feature plugin.
 * @internal Note this is not called WC_Admin due to a class already existing in core with that name.
 */
class Plugin {

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
	private function __construct() {}

	/**
	 * Init the feature plugin, only if we can detect both Gutenberg and Easy Digital Downloads.
	 */
	public function init() {
		$this->define_constants();
		register_activation_hook( EDD_GATEWAY_STRIPE_PLUGIN_FILE, array( $this, 'on_activation' ) );
		register_deactivation_hook( EDD_GATEWAY_STRIPE_PLUGIN_FILE, array( $this, 'on_deactivation' ) );
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Define Constants.
	 */
	protected function define_constants() {
		$this->define( 'EDD_GATEWAY_STRIPE_ABSPATH', dirname( __DIR__ ) . '/' );
		$this->define( 'EDD_GATEWAY_STRIPE_DIST_JS_FOLDER', 'dist/' );
		$this->define( 'EDD_GATEWAY_STRIPE_DIST_CSS_FOLDER', 'dist/' );
		$this->define( 'EDD_GATEWAY_STRIPE_PLUGIN_FILE', EDD_GATEWAY_STRIPE_ABSPATH . 'easy-digital-downloads-gateway-stripe.php' );
		// WARNING: Do not directly edit this version number constant.
		// It is updated as part of the prebuild process from the package.json value.
		$this->define( 'EDD_GATEWAY_STRIPE_VERSION_NUMBER', '0.0.1' );
	}

	/**
	 * Install DB and create cron events when activated.
	 *
	 * @return void
	 */
	public function on_activation() {}

	/**
	 * Remove Payment Gateway Stripe for Easy Digital Downloads scheduled actions on deactivate.
	 *
	 * @return void
	 */
	public function on_deactivation() {}

	/**
	 * Setup plugin once all other plugins are loaded.
	 *
	 * @return void
	 */
	public function on_plugins_loaded() {
		$this->load_plugin_textdomain();

		if ( ! $this->check_dependencies() ) {
			add_action( 'admin_init', array( $this, 'deactivate_self' ) );
			add_action( 'admin_notices', array( $this, 'render_dependencies_notice' ) );
			return;
		}

		if ( ! $this->check_build() ) {
			add_action( 'admin_notices', array( $this, 'render_build_notice' ) );
		}

		// edd_debug_log( print_r( edd_get_option( 'stripe_capture', false ), true ) );

		// Let's roll.
		$this->load();
	}

	/**
	 * Load Localisation files.
	 */
	protected function load_plugin_textdomain() {
		load_plugin_textdomain( 'edd-gateway-stripe', false, basename( dirname( __DIR__ ) ) . '/languages' );
	}

	/**
	 * Load classes.
	 */
	public function load() {
		Loader::instance();
		Gateways\Stripe::instance();

		// Intent controller.
		new StripeIntentController();
	}

	/**
	 * Returns true if all dependencies for the wc-admin plugin are loaded.
	 *
	 * @return bool
	 */
	protected function check_dependencies() {
		$woocommerce_minimum_met = class_exists( 'Easy_Digital_Downloads' ) && version_compare( EDD_VERSION, '2.0', '>=' );
		if ( ! $woocommerce_minimum_met ) {
			return false;
		}

		$wordpress_version = get_bloginfo( 'version' );
		return version_compare( $wordpress_version, '5.2.0', '>=' );
	}

	/**
	 * Returns true if build file exists.
	 *
	 * @return bool
	 */
	protected function check_build() {
		return file_exists( plugin_dir_path( __DIR__ ) . '/dist/app.js' );
	}

	/**
	 * Deactivates this plugin.
	 */
	public function deactivate_self() {
		deactivate_plugins( plugin_basename( EDD_GATEWAY_STRIPE_PLUGIN_FILE ) );
		unset( $_GET['activate'] );
	}

	/**
	 * Notify users of the plugin requirements.
	 */
	public function render_dependencies_notice() {
		// The notice varies by WordPress version.
		$wordpress_version    = get_bloginfo( 'version' );
		$has_valid_wp_version = version_compare( $wordpress_version, '5.2.0', '>=' );

		if ( $has_valid_wp_version ) {
			$message = sprintf(
				/* translators: URL of Easy Digital Downloads plugin */
				__( 'The Payment Gateway Stripe for Easy Digital Downloads plugin requires <a href="%s">Easy Digital Downloads</a> 2.0 or greater to be installed and active.', 'payment-gateway-stripe' ),
				'https://wordpress.org/plugins/easy-digital-downloads/'
			);
		} else {
			$message = sprintf(
				/* translators: 1: URL of WordPress.org, 2: URL of Easy Digital Downloads plugin */
				__( 'The Payment Gateway Stripe for Easy Digital Downloads plugin requires both <a href="%1$s">WordPress</a> 5.2 or greater and <a href="%2$s">Easy Digital Downloads</a> 3.6 or greater to be installed and active.', 'payment-gateway-stripe' ),
				'https://wordpress.org/',
				'https://wordpress.org/plugins/easy-digital-downloads/'
			);
		}
		printf( '<div class="error"><p>%s</p></div>', $message ); /* WPCS: xss ok. */
	}

	/**
	 * Notify users that the plugin needs to be built.
	 */
	public function render_build_notice() {
		$message_one = __( 'You have installed a development version of Payment Gateway Stripe for Easy Digital Downloads which requires files to be built. From the plugin directory, run <code>npm install</code> to install dependencies, <code>npm run build</code> to build the files.', 'payment-gateway-stripe' );
		$message_two = sprintf(
			/* translators: 1: URL of GitHub Repository build page */
			__( 'Or you can download a pre-built version of the plugin by visiting <a href="%1$s">the releases page in the repository</a>.', 'payment-gateway-stripe' ),
			'https://github.com/BegnalStudio/edd-gateway-stripe/releases'
		);
		printf( '<div class="error"><p>%s %s</p></div>', $message_one, $message_two ); /* WPCS: xss ok. */
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	protected function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	private function __wakeup() {}
}

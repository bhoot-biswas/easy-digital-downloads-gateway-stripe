<?php
/**
 * Activation handler
 *
 * @package EDD_Stripe\ActivationHandler
 * @version 1.0.0
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * EDD Extension Activation Handler Class
 *
 * @since       1.0.0
 */
class EDD_Stripe_Extension_Activation {

	public $plugin_name, $plugin_path, $plugin_file, $has_edd;

	/**
	 * Setup the activation class
	 *
	 * @access      public
	 * @since       1.0.0
	 * @return      void
	 */
	public function __construct( $plugin_path, $plugin_file ) {
		// We need plugin.php!
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugins = get_plugins();

		// Set plugin directory
		$plugin_path       = array_filter( explode( '/', $plugin_path ) );
		$this->plugin_path = end( $plugin_path );

		// Set plugin file
		$this->plugin_file = $plugin_file;

		// Set plugin name
		if ( isset( $plugins[ $this->plugin_path . '/' . $this->plugin_file ]['Name'] ) ) {
			$this->plugin_name = str_replace( ' for Easy Digital Downloads', '', $plugins[ $this->plugin_path . '/' . $this->plugin_file ]['Name'] );
		} else {
			$this->plugin_name = __( 'This plugin', 'edd-gateway-stripe' );
		}

		// Is EDD installed?
		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( 'Easy Digital Downloads' === $plugin['Name'] ) {
				$this->has_edd = true;
				break;
			}
		}
	}


	/**
	 * Process plugin deactivation
	 *
	 * @access      public
	 * @since       1.0.0
	 * @return      void
	 */
	public function run() {
		// Display notice
		add_action( 'admin_notices', array( $this, 'missing_edd_notice' ) );
	}

	/**
	 * Display notice if EDD isn't installed
	 *
	 * @access      public
	 * @since       1.0.0
	 * @return      string The notice to display
	 */
	public function missing_edd_notice() {
		if ( $this->has_edd ) {
			echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires %1$sEasy Digital Downloads%2$s. Please activate it to continue.', 'edd-gateway-stripe' ), '<a href="https://easydigitaldownloads.com/" title="Easy Digital Downloads" target="_blank">', '</a>' ) . '</p></div>';
		} else {
			echo '<div class="error"><p>' . $this->plugin_name . sprintf( __( ' requires %1$sEasy Digital Downloads%2$s. Please install it to continue.', 'edd-gateway-stripe' ), '<a href="https://easydigitaldownloads.com/" title="Easy Digital Downloads" target="_blank">', '</a>' ) . '</p></div>';
		}
	}
}

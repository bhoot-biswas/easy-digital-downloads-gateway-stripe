<?php
/**
 * Register the scripts, styles, and includes needed for pieces of the WooCommerce Admin experience.
 *
 * @package EDD Gateway Stripe
 */

namespace BengalStudio\EDD\Stripe;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Loader Class.
 */
class Loader {

	/**
	 * The single instance of the class.
	 * @var [type]
	 */
	private static $instance;

	/**
	 * An array of classes to load from the includes folder.
	 * @var [type]
	 */
	private static $classes = array();

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
	 * Class constructor.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
	}

	/**
	 * Registers all the neccessary scripts and styles to show the admin experience.
	 * @return [type] [description]
	 */
	public function register_scripts() {
		wp_register_script(
			'manifest',
			self::get_url( 'manifest.js' ),
			array(),
			self::get_file_version( 'manifest.js' ),
			true
		);

		wp_register_script(
			'vendor',
			self::get_url( 'vendor.js' ),
			array(),
			self::get_file_version( 'vendor.js' ),
			true
		);

		wp_register_script(
			'app',
			self::get_url( 'app.js' ),
			array(),
			self::get_file_version( 'app.js' ),
			true
		);
	}

	/**
	 * Loads the required scripts on the correct pages.
	 * @return [type] [description]
	 */
	public static function load_scripts() {
		wp_enqueue_script( 'manifest' );
		wp_enqueue_script( 'vendor' );
		wp_enqueue_script( 'app' );
	}

	/**
	 * Gets the URL to an asset file.
	 * @param  [type] $file [description]
	 * @return [type]       [description]
	 */
	public static function get_url( $file ) {
		return plugins_url( self::get_path( $file ) . $file, EDD_GATEWAY_STRIPE_PLUGIN_FILE );
	}

	/**
	 * Gets the file modified time as a cache buster if we're in dev mode, or the plugin version otherwise.
	 * @param  [type] $file [description]
	 * @return [type]       [description]
	 */
	public static function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$file = trim( $file, '/' );
			return filemtime( EDD_GATEWAY_STRIPE_ABSPATH . self::get_path( $file ) . $file );
		}
		return EDD_GATEWAY_STRIPE_VERSION_NUMBER;
	}

	/**
	 * Gets the path for the asset depending on file type.
	 *
	 * @param  string $file name.
	 * @return string Folder path of asset.
	 */
	private static function get_path( $file ) {
		return '.css' === substr( $file, -4 ) ? EDD_GATEWAY_STRIPE_DIST_CSS_FOLDER : EDD_GATEWAY_STRIPE_DIST_JS_FOLDER;
	}
}

<?php
/**
 * Pro bootstrap + autoloader.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader for the FormsKitPro namespace.
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'FormsKitPro\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( 'FormsKitPro\\' ) );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$parts    = explode( '\\', $relative );
		$file     = 'class-' . array_pop( $parts ) . '.php';
		$sub      = $parts ? implode( '/', $parts ) . '/' : '';
		$path     = FORMSKIT_PRO_PATH . 'includes/' . $sub . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Pro controller.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: wire up Pro modules.
	 */
	private function __construct() {
		load_plugin_textdomain( 'wp-formskit-pro', false, dirname( FORMSKIT_PRO_BASENAME ) . '/languages' );

		// Flag Pro as active for the core `is_pro()` helper and upsell logic.
		add_filter( 'formskit_is_pro', '__return_true' );

		// Enqueue Pro assets before modules attach inline scripts to them.
		add_action( 'formskit_enqueue_assets', array( $this, 'front_assets' ), 5 );
		add_action( 'formskit_admin_assets', array( $this, 'builder_assets' ), 5 );

		// Modules.
		new Extra_Fields();
		new File_Upload();
		new Conditional_Logic();
		new Multistep();
		new Recaptcha();
		new Integrations();
		new Payments();
		new License();
	}

	/**
	 * Enqueue Pro front-end assets (only fires when a form renders).
	 */
	public function front_assets() {
		wp_enqueue_style( 'formskit-pro', FORMSKIT_PRO_URL . 'assets/css/pro.css', array( 'formskit' ), FORMSKIT_PRO_VERSION );
		wp_enqueue_script( 'formskit-pro', FORMSKIT_PRO_URL . 'assets/js/pro-frontend.js', array( 'formskit' ), FORMSKIT_PRO_VERSION, true );
	}

	/**
	 * Enqueue Pro builder assets.
	 */
	public function builder_assets() {
		wp_enqueue_style( 'formskit-pro-admin', FORMSKIT_PRO_URL . 'assets/css/pro-admin.css', array( 'formskit-admin' ), FORMSKIT_PRO_VERSION );
		wp_enqueue_script( 'formskit-pro-builder', FORMSKIT_PRO_URL . 'assets/js/pro-builder.js', array( 'formskit-admin-builder' ), FORMSKIT_PRO_VERSION, true );
	}
}

<?php
/**
 * Core bootstrap: autoloader + main plugin controller.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Simple PSR-4-ish autoloader for the FormsKit namespace.
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'FormsKit\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( 'FormsKit\\' ) );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$parts    = explode( '\\', $relative );
		$file     = 'class-' . array_pop( $parts ) . '.php';
		$sub      = $parts ? implode( '/', $parts ) . '/' : '';

		$path = FORMSKIT_PATH . 'includes/' . $sub . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

require_once FORMSKIT_PATH . 'includes/helpers.php';

/**
 * Main plugin controller.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Field registry.
	 *
	 * @var Fields
	 */
	public $fields;

	/**
	 * Entries data store.
	 *
	 * @var Entries
	 */
	public $entries;

	/**
	 * Get the singleton.
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
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin components.
	 */
	public function init() {
		load_plugin_textdomain( 'wp-formskit', false, dirname( FORMSKIT_BASENAME ) . '/languages' );

		$this->fields  = new Fields();
		$this->entries = new Entries();

		new Post_Type();
		new Shortcode();
		new Block();
		new Submission_Handler();

		if ( is_admin() ) {
			new Admin\Admin();
			new Admin\Settings();
			new Admin\Form_Builder();
		}

		/**
		 * Fires once WP FormsKit core is loaded. Add-ons (e.g. Pro) hook here.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'formskit_loaded', $this );
	}

	/**
	 * Read merged plugin settings.
	 *
	 * @param string $key     Optional setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key = null, $default = null ) {
		$defaults = array(
			'recaptcha_site_key'       => '',
			'recaptcha_secret_key'     => '',
			'delete_data_on_uninstall' => 0,
		);
		$settings = wp_parse_args( get_option( 'formskit_settings', array() ), $defaults );

		if ( null === $key ) {
			return $settings;
		}
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}

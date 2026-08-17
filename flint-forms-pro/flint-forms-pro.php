<?php
/**
 * Plugin Name:       Flint Forms Pro
 * Plugin URI:        https://wpformskit.com/pro/
 * Description:       Supercharges Flint Forms with conditional logic, multi-step forms, file uploads, reCAPTCHA, integrations and Stripe payments. Requires Flint Forms.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  flint-forms
 * Author:            Flint Forms
 * Author URI:        https://wpformskit.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flint-forms-pro
 * Domain Path:       /languages
 *
 * @package WPFormsKitPro
 */

defined( 'ABSPATH' ) || exit;

define( 'FORMSKIT_PRO_VERSION', '1.0.0' );
define( 'FORMSKIT_PRO_FILE', __FILE__ );
define( 'FORMSKIT_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORMSKIT_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'FORMSKIT_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Boot Pro once the free core is loaded. If the free plugin is missing,
 * show an admin notice and bail — Pro is an add-on, not a standalone plugin.
 */
add_action(
	'formskit_loaded',
	function () {
		require_once FORMSKIT_PRO_PATH . 'includes/class-formskit-pro.php';
		\FormsKitPro\Plugin::instance();
	}
);

add_action(
	'admin_init',
	function () {
		if ( did_action( 'formskit_loaded' ) ) {
			return;
		}
		// Core did not load — either not installed or not active.
		add_action(
			'admin_notices',
			function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Flint Forms Pro requires the free Flint Forms plugin to be installed and active.', 'flint-forms-pro' );
				echo '</p></div>';
			}
		);
	},
	20
);

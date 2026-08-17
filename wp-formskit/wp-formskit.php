<?php
/**
 * Plugin Name:       WP FormsKit
 * Plugin URI:        https://wpformskit.com/
 * Description:       A fast, lightweight form builder for WordPress. Build forms, collect entries, get email notifications and block spam — for free.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WP FormsKit
 * Author URI:        https://wpformskit.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-formskit
 * Domain Path:       /languages
 *
 * @package WPFormsKit
 */

defined( 'ABSPATH' ) || exit;

define( 'FORMSKIT_VERSION', '1.0.0' );
define( 'FORMSKIT_FILE', __FILE__ );
define( 'FORMSKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORMSKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'FORMSKIT_BASENAME', plugin_basename( __FILE__ ) );

require_once FORMSKIT_PATH . 'includes/class-formskit.php';

/**
 * Activation / deactivation.
 */
register_activation_hook( __FILE__, array( '\FormsKit\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\FormsKit\Activator', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return \FormsKit\Plugin
 */
function formskit() {
	return \FormsKit\Plugin::instance();
}

formskit();

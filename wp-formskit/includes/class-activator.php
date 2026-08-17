<?php
/**
 * Activation / deactivation routines.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Handles install-time setup.
 */
class Activator {

	const DB_VERSION = '1.0.0';

	/**
	 * Run on activation.
	 */
	public static function activate() {
		self::create_tables();
		// Ensure the CPT exists before flushing rewrite rules.
		require_once FORMSKIT_PATH . 'includes/class-post-type.php';
		( new Post_Type() )->register();
		flush_rewrite_rules();
	}

	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create the entries table.
	 */
	public static function create_tables() {
		global $wpdb;

		$table           = $wpdb->prefix . 'formskit_entries';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			fields LONGTEXT NULL,
			meta LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'unread',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ip_address VARCHAR(100) NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'formskit_db_version', self::DB_VERSION );
	}
}

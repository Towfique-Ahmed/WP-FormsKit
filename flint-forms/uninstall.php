<?php
/**
 * Uninstall Flint Forms.
 *
 * Removes plugin data only when the user has opted in via settings
 * (Settings → "Delete all data on uninstall").
 *
 * @package WPFormsKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'formskit_settings', array() );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Delete forms (CPT).
$form_ids = get_posts(
	array(
		'post_type'      => 'formskit_form',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
	)
);
foreach ( (array) $form_ids as $form_id ) {
	wp_delete_post( $form_id, true );
}

// Drop the entries table.
$table = $wpdb->prefix . 'formskit_entries';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

// Remove options.
delete_option( 'formskit_settings' );
delete_option( 'formskit_db_version' );

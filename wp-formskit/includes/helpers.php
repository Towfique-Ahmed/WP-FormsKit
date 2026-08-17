<?php
/**
 * Shared helper functions.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Load the config array for a form.
 *
 * @param int $form_id Form post ID.
 * @return array{title:string,fields:array,settings:array}
 */
function get_form_config( $form_id ) {
	$form_id = absint( $form_id );
	$raw     = get_post_meta( $form_id, '_formskit_config', true );

	$config = is_array( $raw ) ? $raw : array();

	$config = wp_parse_args(
		$config,
		array(
			'fields'   => array(),
			'settings' => array(),
		)
	);

	$config['title']    = get_the_title( $form_id );
	$config['settings'] = wp_parse_args(
		$config['settings'],
		array(
			'submit_label'  => __( 'Submit', 'wp-formskit' ),
			'ajax'          => true,
			'store_entries' => true,
			'confirmation'  => array(
				'type'         => 'message',
				'message'      => __( 'Thanks! Your submission has been received.', 'wp-formskit' ),
				'redirect_url' => '',
			),
			'notification'  => array(
				'enabled'  => true,
				'to'       => '{admin_email}',
				'subject'  => sprintf( /* translators: %s: form title */ __( 'New submission: %s', 'wp-formskit' ), $config['title'] ),
				'message'  => '{all_fields}',
				'reply_to' => '',
			),
			'spam'          => array(
				'honeypot' => true,
				'min_time' => 3,
			),
		)
	);

	if ( ! is_array( $config['fields'] ) ) {
		$config['fields'] = array();
	}

	/**
	 * Filter the resolved form configuration.
	 *
	 * @param array $config  Form config.
	 * @param int   $form_id Form ID.
	 */
	return apply_filters( 'formskit_form_config', $config, $form_id );
}

/**
 * Determine whether the Pro add-on is active.
 *
 * @return bool
 */
function is_pro() {
	return (bool) apply_filters( 'formskit_is_pro', false );
}

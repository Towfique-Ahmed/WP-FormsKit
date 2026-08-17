<?php
/**
 * Registers the "form" custom post type.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Form custom post type.
 */
class Post_Type {

	const POST_TYPE = 'formskit_form';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the CPT.
	 */
	public function register() {
		$labels = array(
			'name'               => __( 'Forms', 'wp-formskit' ),
			'singular_name'      => __( 'Form', 'wp-formskit' ),
			'add_new'            => __( 'Add New', 'wp-formskit' ),
			'add_new_item'       => __( 'Add New Form', 'wp-formskit' ),
			'edit_item'          => __( 'Edit Form', 'wp-formskit' ),
			'new_item'           => __( 'New Form', 'wp-formskit' ),
			'view_item'          => __( 'View Form', 'wp-formskit' ),
			'search_items'       => __( 'Search Forms', 'wp-formskit' ),
			'not_found'          => __( 'No forms found.', 'wp-formskit' ),
			'not_found_in_trash' => __( 'No forms found in Trash.', 'wp-formskit' ),
			'menu_name'          => __( 'FormsKit', 'wp-formskit' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-feedback',
				'menu_position'   => 26,
				'capability_type' => 'post',
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}
}

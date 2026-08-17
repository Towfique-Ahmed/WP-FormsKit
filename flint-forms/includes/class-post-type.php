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
			'name'               => __( 'Forms', 'flint-forms' ),
			'singular_name'      => __( 'Form', 'flint-forms' ),
			'add_new'            => __( 'Add New', 'flint-forms' ),
			'add_new_item'       => __( 'Add New Form', 'flint-forms' ),
			'edit_item'          => __( 'Edit Form', 'flint-forms' ),
			'new_item'           => __( 'New Form', 'flint-forms' ),
			'view_item'          => __( 'View Form', 'flint-forms' ),
			'search_items'       => __( 'Search Forms', 'flint-forms' ),
			'not_found'          => __( 'No forms found.', 'flint-forms' ),
			'not_found_in_trash' => __( 'No forms found in Trash.', 'flint-forms' ),
			'menu_name'          => __( 'Flint Forms', 'flint-forms' ),
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

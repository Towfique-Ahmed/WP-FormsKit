<?php
/**
 * Gutenberg block that embeds a form.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration.
 */
class Block {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * Localize the editor script with the list of available forms.
	 */
	public function editor_assets() {
		$forms = get_posts(
			array(
				'post_type'      => Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'numberposts'    => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$list = array();
		foreach ( $forms as $form ) {
			$list[] = array(
				'id'    => $form->ID,
				'title' => $form->post_title ? $form->post_title : sprintf( /* translators: %d: form id */ __( 'Form #%d', 'wp-formskit' ), $form->ID ),
			);
		}

		wp_localize_script( 'formskit-block-editor', 'FormsKitBlock', array( 'forms' => $list ) );
	}

	/**
	 * Register the block with a server-side render callback.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register the editor script early so the block.json handle resolves.
		wp_register_script(
			'formskit-block-editor',
			FORMSKIT_URL . 'blocks/form/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			FORMSKIT_VERSION,
			true
		);

		register_block_type(
			FORMSKIT_PATH . 'blocks/form',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Server render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$form_id = isset( $attributes['formId'] ) ? absint( $attributes['formId'] ) : 0;
		if ( ! $form_id ) {
			return '';
		}
		return ( new Form_Renderer() )->render( $form_id );
	}
}

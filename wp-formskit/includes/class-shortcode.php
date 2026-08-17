<?php
/**
 * The [formskit] shortcode.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode handler.
 */
class Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'formskit', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'formskit' );

		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '';
		}

		$renderer = new Form_Renderer();

		$args = array();

		// Re-populate on a failed non-AJAX submission.
		if ( isset( $GLOBALS['formskit_last_errors'] ) && ! empty( $_POST['formskit_form_id'] ) && absint( $_POST['formskit_form_id'] ) === $form_id ) { // phpcs:ignore WordPress.Security
			$args['errors'] = $GLOBALS['formskit_last_errors'];
			$args['values'] = isset( $_POST['formskit_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['formskit_fields'] ) ) : array(); // phpcs:ignore WordPress.Security
		}

		// Show the success message inline for non-AJAX forms.
		if ( isset( $GLOBALS['formskit_last_success'] ) && ! empty( $_POST['formskit_form_id'] ) && absint( $_POST['formskit_form_id'] ) === $form_id ) { // phpcs:ignore WordPress.Security
			$msg = $GLOBALS['formskit_last_success']['confirmation']['message'];
			return '<div class="formskit-wrap"><div class="formskit-success" role="alert">' . wp_kses_post( wpautop( $msg ) ) . '</div></div>';
		}

		return $renderer->render( $form_id, $args );
	}
}

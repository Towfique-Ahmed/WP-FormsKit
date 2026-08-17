<?php
/**
 * Multi-step forms — persist the toggle; the front-end script splits pages
 * on "Page Break" fields and renders the progress bar.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-step module.
 */
class Multistep {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'formskit_sanitize_settings_meta', array( $this, 'sanitize_settings' ), 10, 2 );
		add_action( 'formskit_form_start', array( $this, 'add_flag' ), 5, 2 );
	}

	/**
	 * Persist multi-step settings.
	 *
	 * @param array $clean Clean settings.
	 * @param array $raw   Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $clean, $raw ) {
		$clean['multistep'] = array(
			'enabled'  => ! empty( $raw['multistep']['enabled'] ),
			'progress' => ! isset( $raw['multistep']['progress'] ) || ! empty( $raw['multistep']['progress'] ),
		);
		return $clean;
	}

	/**
	 * Tag the form element so the front-end script can pick it up.
	 *
	 * @param array $config  Config.
	 * @param int   $form_id Form ID.
	 */
	public function add_flag( $config, $form_id ) {
		unset( $form_id );
		if ( empty( $config['settings']['multistep']['enabled'] ) ) {
			return;
		}
		$progress = ! empty( $config['settings']['multistep']['progress'] ) ? '1' : '0';
		printf(
			'<span class="formskit-multistep-flag" data-progress="%s" hidden></span>',
			esc_attr( $progress )
		);
	}
}

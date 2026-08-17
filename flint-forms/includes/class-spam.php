<?php
/**
 * Baseline spam protection: honeypot + time-trap.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Spam checks.
 */
class Spam {

	const HONEYPOT_FIELD = 'formskit_hp';

	/**
	 * Run spam checks against a submission.
	 *
	 * @param array $config  Form config.
	 * @param array $request Request data.
	 * @return true|\WP_Error True if OK.
	 */
	public static function check( array $config, array $request ) {
		$spam = isset( $config['settings']['spam'] ) ? $config['settings']['spam'] : array();

		// Honeypot: a hidden field that only bots fill in.
		if ( ! empty( $spam['honeypot'] ) && ! empty( $request[ self::HONEYPOT_FIELD ] ) ) {
			return new \WP_Error( 'formskit_spam', __( 'Your submission was flagged as spam.', 'flint-forms' ) );
		}

		// Time-trap: reject submissions faster than a human could complete.
		$min_time = isset( $spam['min_time'] ) ? absint( $spam['min_time'] ) : 0;
		if ( $min_time > 0 && ! empty( $request['formskit_ts'] ) ) {
			$elapsed = time() - absint( $request['formskit_ts'] );
			if ( $elapsed < $min_time ) {
				return new \WP_Error( 'formskit_spam', __( 'Your submission was sent too quickly. Please try again.', 'flint-forms' ) );
			}
		}

		/**
		 * Allow add-ons to run extra spam checks.
		 *
		 * @param true|\WP_Error $result  Current result.
		 * @param array          $config  Form config.
		 * @param array          $request Request data.
		 */
		return apply_filters( 'formskit_spam_check', true, $config, $request );
	}

	/**
	 * Output the honeypot markup. Hooked to form start.
	 */
	public static function render_honeypot() {
		printf(
			'<div class="formskit-hp-wrap" aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:-9999px!important;">
				<label>%1$s<input type="text" name="%2$s" tabindex="-1" autocomplete="off" value="" /></label>
			</div>',
			esc_html__( 'Leave this field empty', 'flint-forms' ),
			esc_attr( self::HONEYPOT_FIELD )
		);
	}
}

add_action(
	'formskit_form_start',
	function ( $config ) {
		if ( ! empty( $config['settings']['spam']['honeypot'] ) ) {
			Spam::render_honeypot();
		}
	}
);

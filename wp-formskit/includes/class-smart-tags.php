<?php
/**
 * Smart tag replacement for notifications and confirmations.
 *
 * Supported tags:
 *   {field:name}   — a single submitted field value
 *   {all_fields}   — a formatted table of all fields
 *   {admin_email}  — site admin email
 *   {site_name}    — blog name
 *   {form_title}   — form title
 *   {date}         — current date
 *   {ip}           — submitter IP
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Smart tags helper.
 */
class Smart_Tags {

	/**
	 * Replace smart tags in a string.
	 *
	 * @param string $text    Text containing tags.
	 * @param array  $values  Field values keyed by name.
	 * @param array  $labels  Field labels keyed by name.
	 * @param int    $form_id Form ID.
	 * @param bool   $html    Whether output is HTML (for {all_fields}).
	 * @return string
	 */
	public static function replace( $text, array $values, array $labels = array(), $form_id = 0, $html = false ) {
		if ( '' === (string) $text ) {
			return '';
		}

		$globals = array(
			'{admin_email}' => get_option( 'admin_email' ),
			'{site_name}'   => get_bloginfo( 'name' ),
			'{form_title}'  => $form_id ? get_the_title( $form_id ) : '',
			'{date}'        => date_i18n( get_option( 'date_format' ) ),
			'{ip}'          => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		);
		$text = str_replace( array_keys( $globals ), array_values( $globals ), $text );

		// {all_fields}
		if ( false !== strpos( $text, '{all_fields}' ) ) {
			$text = str_replace( '{all_fields}', self::format_all( $values, $labels, $html ), $text );
		}

		// {field:name}
		$text = preg_replace_callback(
			'/\{field:([a-zA-Z0-9_\-]+)\}/',
			function ( $m ) use ( $values ) {
				$key = $m[1];
				if ( ! isset( $values[ $key ] ) ) {
					return '';
				}
				$val = $values[ $key ];
				return is_array( $val ) ? implode( ', ', $val ) : $val;
			},
			$text
		);

		return $text;
	}

	/**
	 * Format all fields as text or an HTML table.
	 *
	 * @param array $values Values.
	 * @param array $labels Labels.
	 * @param bool  $html   HTML output.
	 * @return string
	 */
	public static function format_all( array $values, array $labels, $html = false ) {
		if ( empty( $values ) ) {
			return '';
		}

		if ( $html ) {
			$out = '<table cellpadding="8" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">';
			foreach ( $values as $name => $value ) {
				$label = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
				$value = is_array( $value ) ? implode( ', ', $value ) : $value;
				$out  .= sprintf(
					'<tr><td style="border-bottom:1px solid #eee;font-weight:bold;vertical-align:top;">%s</td><td style="border-bottom:1px solid #eee;">%s</td></tr>',
					esc_html( $label ),
					nl2br( esc_html( $value ) )
				);
			}
			$out .= '</table>';
			return $out;
		}

		$lines = array();
		foreach ( $values as $name => $value ) {
			$label   = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
			$value   = is_array( $value ) ? implode( ', ', $value ) : $value;
			$lines[] = $label . ': ' . $value;
		}
		return implode( "\n", $lines );
	}
}

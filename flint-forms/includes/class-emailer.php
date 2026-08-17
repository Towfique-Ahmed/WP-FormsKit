<?php
/**
 * Sends email notifications for submissions.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Email notifications.
 */
class Emailer {

	/**
	 * Send the admin notification for a submission.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $config  Form config.
	 * @param array $values  Clean field values.
	 * @param array $labels  Field labels.
	 * @return bool
	 */
	public function send_notification( $form_id, array $config, array $values, array $labels ) {
		$n = wp_parse_args(
			isset( $config['settings']['notification'] ) ? $config['settings']['notification'] : array(),
			array(
				'to'       => '{admin_email}',
				'subject'  => '',
				'message'  => '{all_fields}',
				'reply_to' => '',
			)
		);

		$to      = Smart_Tags::replace( $n['to'], $values, $labels, $form_id );
		$subject = Smart_Tags::replace( $n['subject'], $values, $labels, $form_id );
		$body    = wpautop( Smart_Tags::replace( $n['message'], $values, $labels, $form_id, true ) );

		$to = array_filter( array_map( 'trim', explode( ',', $to ) ), 'is_email' );
		if ( empty( $to ) ) {
			$to = array( get_option( 'admin_email' ) );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$reply_to = trim( Smart_Tags::replace( $n['reply_to'], $values, $labels, $form_id ) );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/**
		 * Filter notification email arguments.
		 *
		 * @param array $args    Email args (to, subject, body, headers).
		 * @param int   $form_id Form ID.
		 * @param array $values  Values.
		 */
		$args = apply_filters(
			'formskit_notification_email',
			compact( 'to', 'subject', 'body', 'headers' ),
			$form_id,
			$values
		);

		return wp_mail( $args['to'], $args['subject'], $args['body'], $args['headers'] );
	}
}

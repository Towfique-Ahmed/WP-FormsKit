<?php
/**
 * Third-party integrations: outgoing webhook, Slack, Mailchimp.
 *
 * Per-form integration config lives in form settings under
 * settings.integrations and is dispatched after a successful submission.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * Integrations module.
 */
class Integrations {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'formskit_sanitize_settings_meta', array( $this, 'sanitize_settings' ), 10, 2 );
		add_action( 'formskit_after_submission', array( $this, 'dispatch' ), 10, 4 );
	}

	/**
	 * Persist per-form integration settings.
	 *
	 * @param array $clean Clean settings.
	 * @param array $raw   Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $clean, $raw ) {
		$in = isset( $raw['integrations'] ) && is_array( $raw['integrations'] ) ? $raw['integrations'] : array();

		$clean['integrations'] = array(
			'webhook'   => array(
				'enabled' => ! empty( $in['webhook']['enabled'] ),
				'url'     => isset( $in['webhook']['url'] ) ? esc_url_raw( $in['webhook']['url'] ) : '',
			),
			'slack'     => array(
				'enabled' => ! empty( $in['slack']['enabled'] ),
				'url'     => isset( $in['slack']['url'] ) ? esc_url_raw( $in['slack']['url'] ) : '',
			),
			'mailchimp' => array(
				'enabled'   => ! empty( $in['mailchimp']['enabled'] ),
				'api_key'   => isset( $in['mailchimp']['api_key'] ) ? sanitize_text_field( $in['mailchimp']['api_key'] ) : '',
				'list_id'   => isset( $in['mailchimp']['list_id'] ) ? sanitize_text_field( $in['mailchimp']['list_id'] ) : '',
				'email_key' => isset( $in['mailchimp']['email_key'] ) ? sanitize_key( $in['mailchimp']['email_key'] ) : 'email',
			),
		);

		return $clean;
	}

	/**
	 * Dispatch all enabled integrations.
	 *
	 * @param int   $entry_id Entry ID.
	 * @param array $values   Values.
	 * @param array $config   Config.
	 * @param int   $form_id  Form ID.
	 */
	public function dispatch( $entry_id, $values, $config, $form_id ) {
		$integrations = isset( $config['settings']['integrations'] ) ? $config['settings']['integrations'] : array();

		if ( ! empty( $integrations['webhook']['enabled'] ) && ! empty( $integrations['webhook']['url'] ) ) {
			$this->send_webhook( $integrations['webhook']['url'], $entry_id, $values, $form_id );
		}

		if ( ! empty( $integrations['slack']['enabled'] ) && ! empty( $integrations['slack']['url'] ) ) {
			$this->send_slack( $integrations['slack']['url'], $values, $config, $form_id );
		}

		if ( ! empty( $integrations['mailchimp']['enabled'] ) ) {
			$this->send_mailchimp( $integrations['mailchimp'], $values );
		}
	}

	/**
	 * POST the entry as JSON to a webhook URL.
	 *
	 * @param string $url      URL.
	 * @param int    $entry_id Entry ID.
	 * @param array  $values   Values.
	 * @param int    $form_id  Form ID.
	 */
	protected function send_webhook( $url, $entry_id, $values, $form_id ) {
		wp_remote_post(
			$url,
			array(
				'timeout'  => 15,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'entry_id' => $entry_id,
						'form_id'  => $form_id,
						'form'     => get_the_title( $form_id ),
						'fields'   => $values,
						'date'     => current_time( 'mysql' ),
					)
				),
			)
		);
	}

	/**
	 * Send a Slack incoming-webhook message.
	 *
	 * @param string $url     Webhook URL.
	 * @param array  $values  Values.
	 * @param array  $config  Config.
	 * @param int    $form_id Form ID.
	 */
	protected function send_slack( $url, $values, $config, $form_id ) {
		$labels = array();
		foreach ( $config['fields'] as $field ) {
			$labels[ $field['name'] ] = $field['label'];
		}

		$lines = array( '*' . sprintf( /* translators: %s: form title */ __( 'New submission: %s', 'wp-formskit-pro' ), get_the_title( $form_id ) ) . '*' );
		foreach ( $values as $name => $value ) {
			$label   = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
			$value   = is_array( $value ) ? implode( ', ', $value ) : $value;
			$lines[] = '• *' . $label . ':* ' . $value;
		}

		wp_remote_post(
			$url,
			array(
				'timeout'  => 15,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'text' => implode( "\n", $lines ) ) ),
			)
		);
	}

	/**
	 * Subscribe the submitter to a Mailchimp audience.
	 *
	 * @param array $settings Mailchimp settings.
	 * @param array $values   Values.
	 */
	protected function send_mailchimp( $settings, $values ) {
		$api_key = $settings['api_key'];
		$list_id = $settings['list_id'];
		$email   = isset( $values[ $settings['email_key'] ] ) ? $values[ $settings['email_key'] ] : '';

		if ( ! $api_key || ! $list_id || ! is_email( $email ) || false === strpos( $api_key, '-' ) ) {
			return;
		}

		$dc  = substr( strrchr( $api_key, '-' ), 1 );
		$url = sprintf( 'https://%s.api.mailchimp.com/3.0/lists/%s/members', $dc, rawurlencode( $list_id ) );

		wp_remote_post(
			$url,
			array(
				'timeout'  => 15,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				),
				'body'     => wp_json_encode(
					array(
						'email_address' => $email,
						'status'        => 'subscribed',
					)
				),
			)
		);
	}
}

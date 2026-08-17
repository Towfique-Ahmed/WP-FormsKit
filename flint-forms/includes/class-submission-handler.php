<?php
/**
 * Handles form submissions (AJAX and standard POST).
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Submission handler.
 */
class Submission_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_formskit_submit', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_formskit_submit', array( $this, 'handle_ajax' ) );
		add_action( 'template_redirect', array( $this, 'handle_post' ) );
	}

	/**
	 * AJAX entry point.
	 */
	public function handle_ajax() {
		$result = $this->process( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'fields'  => $result->get_error_data(),
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Standard (non-AJAX) POST entry point.
	 */
	public function handle_post() {
		if ( empty( $_POST['formskit_form_id'] ) || ! isset( $_POST['formskit_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$result = $this->process( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( is_wp_error( $result ) ) {
			$GLOBALS['formskit_last_errors'] = $result->get_error_data();
			$GLOBALS['formskit_last_message'] = $result->get_error_message();
			return;
		}

		if ( 'redirect' === $result['confirmation']['type'] && ! empty( $result['confirmation']['redirect_url'] ) ) {
			wp_safe_redirect( $result['confirmation']['redirect_url'] );
			exit;
		}

		$GLOBALS['formskit_last_success'] = $result;
	}

	/**
	 * Core processing pipeline.
	 *
	 * @param array $request Raw request data.
	 * @return array|\WP_Error
	 */
	public function process( array $request ) {
		$form_id = isset( $request['formskit_form_id'] ) ? absint( $request['formskit_form_id'] ) : 0;

		if ( ! $form_id || Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			return new \WP_Error( 'formskit_invalid_form', __( 'Invalid form.', 'flint-forms' ) );
		}

		// Verify nonce.
		$nonce = isset( $request['formskit_nonce'] ) ? sanitize_text_field( wp_unslash( $request['formskit_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'formskit_submit_' . $form_id ) ) {
			return new \WP_Error( 'formskit_bad_nonce', __( 'Security check failed. Please reload and try again.', 'flint-forms' ) );
		}

		$config = get_form_config( $form_id );

		// Spam checks (honeypot + time-trap).
		$spam = Spam::check( $config, $request );
		if ( is_wp_error( $spam ) ) {
			return $spam;
		}

		/**
		 * Extension point for additional gatekeeping (e.g. Pro reCAPTCHA).
		 * Return a WP_Error to reject the submission.
		 *
		 * @param null|\WP_Error $gate    Null to allow.
		 * @param array          $config  Form config.
		 * @param array          $request Request data.
		 */
		$gate = apply_filters( 'formskit_pre_validate', null, $config, $request );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		// Validate + sanitize each field.
		$submitted = isset( $request['formskit_fields'] ) && is_array( $request['formskit_fields'] ) ? $request['formskit_fields'] : array();
		$fields    = formskit()->fields;

		$clean  = array();
		$labels = array();
		$errors = array();

		/**
		 * Filter the fields to process for this submission. Pro conditional
		 * logic removes fields that are hidden by their rules so they are not
		 * validated or stored.
		 *
		 * @param array $fields    Field definitions.
		 * @param array $submitted Submitted values.
		 * @param array $config    Form config.
		 */
		$process_fields = apply_filters( 'formskit_submission_fields', $config['fields'], $submitted, $config );

		// Display-only field types never carry a value.
		$skip_types = array( 'html', 'section', 'pagebreak' );

		foreach ( $process_fields as $field ) {
			if ( in_array( $field['type'], $skip_types, true ) ) {
				continue;
			}
			$name  = $field['name'];
			$raw   = isset( $submitted[ $name ] ) ? $submitted[ $name ] : '';
			$value = $fields->sanitize( $field, $raw );
			$error = $fields->validate( $field, $value );

			if ( $error ) {
				$errors[ $name ] = $error;
				continue;
			}

			$clean[ $name ]  = $value;
			$labels[ $name ] = $field['label'];
		}

		if ( $errors ) {
			return new \WP_Error(
				'formskit_validation',
				__( 'Please correct the highlighted fields.', 'flint-forms' ),
				$errors
			);
		}

		/**
		 * Filter the clean submission data before storage/notification.
		 * Pro uses this to inject file URLs, payment IDs, etc.
		 *
		 * @param array $clean   Clean values keyed by field name.
		 * @param array $config  Form config.
		 * @param array $request Raw request.
		 */
		$clean = apply_filters( 'formskit_submission_data', $clean, $config, $request );

		$meta = array(
			'ip'         => $this->get_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referer'    => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'labels'     => $labels,
		);

		// Store entry.
		$entry_id = 0;
		if ( ! empty( $config['settings']['store_entries'] ) ) {
			$entry_id = formskit()->entries->insert( $form_id, $clean, $meta );
		}

		// Email notification.
		if ( ! empty( $config['settings']['notification']['enabled'] ) ) {
			( new Emailer() )->send_notification( $form_id, $config, $clean, $labels );
		}

		/**
		 * Fires after a successful submission. Pro integrations (webhooks,
		 * Mailchimp, Slack, Stripe capture) hook here.
		 *
		 * @param int   $entry_id Entry ID (0 if not stored).
		 * @param array $clean    Clean field values.
		 * @param array $config   Form config.
		 * @param int   $form_id  Form ID.
		 */
		do_action( 'formskit_after_submission', $entry_id, $clean, $config, $form_id );

		$confirmation = $config['settings']['confirmation'];
		$confirmation['message'] = Smart_Tags::replace( $confirmation['message'], $clean, $labels, $form_id );

		$result = array(
			'entry_id'     => $entry_id,
			'confirmation' => $confirmation,
			'message'      => $confirmation['message'],
		);

		/**
		 * Filter the final submission result. Pro payments use this to swap the
		 * confirmation for a redirect to a hosted checkout page.
		 *
		 * @param array $result  Result array.
		 * @param array $config  Form config.
		 * @param int   $form_id Form ID.
		 * @param array $clean   Clean field values.
		 */
		return apply_filters( 'formskit_submission_result', $result, $config, $form_id, $clean );
	}

	/**
	 * Best-effort client IP.
	 *
	 * @return string
	 */
	protected function get_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}

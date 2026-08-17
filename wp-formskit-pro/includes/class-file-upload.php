<?php
/**
 * File upload field + secure upload handling.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * File upload module.
 */
class File_Upload {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'formskit_register_fields', array( $this, 'register' ) );
		add_filter( 'formskit_field_is_empty', array( $this, 'is_empty' ), 10, 2 );
		add_filter( 'formskit_validate_field', array( $this, 'validate' ), 10, 3 );
		add_filter( 'formskit_submission_data', array( $this, 'process_uploads' ), 10, 3 );
	}

	/**
	 * Register the file field type.
	 *
	 * @param \FormsKit\Fields $fields Registry.
	 */
	public function register( $fields ) {
		$fields->register(
			'file',
			array(
				'label'  => __( 'File Upload', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-upload',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render the file input.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render( $field, $value, $name ) {
		unset( $value );
		$accept = isset( $field['accept'] ) ? $field['accept'] : '';
		return sprintf(
			'<input type="file" id="%1$s" name="%2$s" class="formskit-input formskit-file" %3$s %4$s />',
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			$accept ? 'accept="' . esc_attr( $accept ) . '"' : '',
			! empty( $field['required'] ) ? 'aria-required="true"' : ''
		);
	}

	/**
	 * Report a file field as non-empty when a file was actually uploaded.
	 *
	 * @param bool  $is_empty Current emptiness.
	 * @param array $field    Field.
	 * @return bool
	 */
	public function is_empty( $is_empty, $field ) {
		if ( 'file' !== $field['type'] ) {
			return $is_empty;
		}
		$name = $field['name'];
		return empty( $_FILES['formskit_fields']['name'][ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Validate a required file field.
	 *
	 * @param string $error Current error.
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @return string
	 */
	public function validate( $error, $field, $value ) {
		unset( $value );
		if ( 'file' !== $field['type'] ) {
			return $error;
		}

		$name       = $field['name'];
		$has_upload = isset( $_FILES['formskit_fields'] ) && ! empty( $_FILES['formskit_fields']['name'][ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! empty( $field['required'] ) && ! $has_upload ) {
			return __( 'Please choose a file to upload.', 'wp-formskit-pro' );
		}

		if ( $has_upload ) {
			$err = isset( $_FILES['formskit_fields']['error'][ $name ] ) ? (int) $_FILES['formskit_fields']['error'][ $name ] : UPLOAD_ERR_NO_FILE; // phpcs:ignore WordPress.Security.NonceVerification
			if ( UPLOAD_ERR_OK !== $err ) {
				return __( 'The file could not be uploaded. Please try again.', 'wp-formskit-pro' );
			}
		}

		return $error;
	}

	/**
	 * Move uploaded files into the media library and store their URLs.
	 *
	 * @param array $clean   Clean values.
	 * @param array $config  Form config.
	 * @param array $request Raw request.
	 * @return array
	 */
	public function process_uploads( $clean, $config, $request ) {
		unset( $request );

		if ( empty( $_FILES['formskit_fields'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $clean;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( $config['fields'] as $field ) {
			if ( 'file' !== $field['type'] ) {
				continue;
			}
			$name = $field['name'];

			if ( empty( $_FILES['formskit_fields']['name'][ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				continue;
			}

			// Re-shape the nested $_FILES entry into the flat structure WP expects.
			$file = array(
				'name'     => sanitize_file_name( $_FILES['formskit_fields']['name'][ $name ] ), // phpcs:ignore WordPress.Security
				'type'     => isset( $_FILES['formskit_fields']['type'][ $name ] ) ? $_FILES['formskit_fields']['type'][ $name ] : '', // phpcs:ignore WordPress.Security
				'tmp_name' => isset( $_FILES['formskit_fields']['tmp_name'][ $name ] ) ? $_FILES['formskit_fields']['tmp_name'][ $name ] : '', // phpcs:ignore WordPress.Security
				'error'    => isset( $_FILES['formskit_fields']['error'][ $name ] ) ? (int) $_FILES['formskit_fields']['error'][ $name ] : UPLOAD_ERR_NO_FILE, // phpcs:ignore WordPress.Security
				'size'     => isset( $_FILES['formskit_fields']['size'][ $name ] ) ? (int) $_FILES['formskit_fields']['size'][ $name ] : 0, // phpcs:ignore WordPress.Security
			);

			$_FILES['formskit_single_upload'] = $file;

			$overrides = array(
				'test_form' => false,
				/**
				 * Filter allowed MIME types for form uploads.
				 *
				 * @param array $mimes Allowed mimes.
				 * @param array $field Field.
				 */
				'mimes'     => apply_filters( 'formskit_upload_mimes', get_allowed_mime_types(), $field ),
			);

			$attachment_id = media_handle_upload( 'formskit_single_upload', 0, array(), $overrides );
			unset( $_FILES['formskit_single_upload'] );

			if ( is_wp_error( $attachment_id ) ) {
				$clean[ $name ] = '';
				continue;
			}

			$clean[ $name ] = wp_get_attachment_url( $attachment_id );
		}

		return $clean;
	}
}

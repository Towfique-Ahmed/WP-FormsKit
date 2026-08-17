<?php
/**
 * reCAPTCHA v3 spam gate.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * reCAPTCHA integration.
 */
class Recaptcha {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'formskit_settings_fields', array( $this, 'settings_fields' ) );
		add_filter( 'formskit_sanitize_settings', array( $this, 'save_settings' ), 10, 2 );
		add_action( 'formskit_enqueue_assets', array( $this, 'enqueue' ) );
		add_action( 'formskit_before_submit', array( $this, 'token_field' ), 10, 2 );
		add_filter( 'formskit_pre_validate', array( $this, 'verify' ), 10, 3 );
	}

	/**
	 * Whether keys are configured.
	 *
	 * @return bool
	 */
	protected function enabled() {
		return (bool) formskit()->get_setting( 'recaptcha_site_key' ) && (bool) formskit()->get_setting( 'recaptcha_secret_key' );
	}

	/**
	 * Render settings rows.
	 *
	 * @param array $settings Settings.
	 */
	public function settings_fields( $settings ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'reCAPTCHA v3 Site Key', 'wp-formskit-pro' ); ?></th>
			<td><input type="text" class="regular-text" name="formskit_settings[recaptcha_site_key]" value="<?php echo esc_attr( isset( $settings['recaptcha_site_key'] ) ? $settings['recaptcha_site_key'] : '' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'reCAPTCHA v3 Secret Key', 'wp-formskit-pro' ); ?></th>
			<td><input type="password" class="regular-text" name="formskit_settings[recaptcha_secret_key]" value="<?php echo esc_attr( isset( $settings['recaptcha_secret_key'] ) ? $settings['recaptcha_secret_key'] : '' ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Persist keys.
	 *
	 * @param array $clean Clean settings.
	 * @param array $input Raw input.
	 * @return array
	 */
	public function save_settings( $clean, $input ) {
		$clean['recaptcha_site_key']   = isset( $input['recaptcha_site_key'] ) ? sanitize_text_field( $input['recaptcha_site_key'] ) : '';
		$clean['recaptcha_secret_key'] = isset( $input['recaptcha_secret_key'] ) ? sanitize_text_field( $input['recaptcha_secret_key'] ) : '';
		return $clean;
	}

	/**
	 * Enqueue the Google script + config.
	 */
	public function enqueue() {
		if ( ! $this->enabled() ) {
			return;
		}
		$site_key = formskit()->get_setting( 'recaptcha_site_key' );
		wp_enqueue_script( 'formskit-recaptcha-api', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_add_inline_script(
			'formskit-pro',
			'window.FormsKitRecaptcha = ' . wp_json_encode( array( 'siteKey' => $site_key ) ) . ';',
			'before'
		);
	}

	/**
	 * Add the hidden token field to each form.
	 *
	 * @param array $config  Config.
	 * @param int   $form_id Form ID.
	 */
	public function token_field( $config, $form_id ) {
		unset( $config, $form_id );
		if ( ! $this->enabled() ) {
			return;
		}
		echo '<input type="hidden" name="formskit_recaptcha_token" class="formskit-recaptcha-token" value="" />';
	}

	/**
	 * Verify the token server-side before accepting the submission.
	 *
	 * @param null|\WP_Error $gate    Current gate.
	 * @param array          $config  Config.
	 * @param array          $request Request.
	 * @return null|\WP_Error
	 */
	public function verify( $gate, $config, $request ) {
		unset( $config );
		if ( is_wp_error( $gate ) || ! $this->enabled() ) {
			return $gate;
		}

		$token = isset( $request['formskit_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $request['formskit_recaptcha_token'] ) ) : '';
		if ( '' === $token ) {
			return new \WP_Error( 'formskit_recaptcha', __( 'reCAPTCHA verification failed. Please try again.', 'wp-formskit-pro' ) );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => formskit()->get_setting( 'recaptcha_secret_key' ),
					'response' => $token,
					'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Fail open on network errors so genuine users are never blocked.
			return $gate;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		/**
		 * Filter the minimum acceptable reCAPTCHA v3 score.
		 *
		 * @param float $threshold Score threshold (0–1).
		 */
		$threshold = apply_filters( 'formskit_recaptcha_threshold', 0.5 );

		if ( empty( $body['success'] ) || ( isset( $body['score'] ) && (float) $body['score'] < $threshold ) ) {
			return new \WP_Error( 'formskit_recaptcha', __( 'Your submission looked automated and was blocked.', 'wp-formskit-pro' ) );
		}

		return $gate;
	}
}

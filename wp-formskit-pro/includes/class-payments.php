<?php
/**
 * Stripe payments via hosted Checkout Sessions.
 *
 * A "Payment" field sets an amount; on submit, if Stripe keys are configured
 * and the form has a payment field, a Checkout Session is created and the user
 * is redirected to Stripe's hosted payment page.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * Payments module.
 */
class Payments {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'formskit_register_fields', array( $this, 'register_field' ) );
		add_filter( 'formskit_sanitize_field', array( $this, 'sanitize_field' ), 10, 2 );
		add_action( 'formskit_settings_fields', array( $this, 'settings_fields' ) );
		add_filter( 'formskit_sanitize_settings', array( $this, 'save_settings' ), 10, 2 );
		add_filter( 'formskit_submission_result', array( $this, 'maybe_checkout' ), 10, 4 );

		// Allow non-AJAX submissions to redirect to Stripe's hosted checkout.
		add_filter(
			'allowed_redirect_hosts',
			function ( $hosts ) {
				$hosts[] = 'checkout.stripe.com';
				return $hosts;
			}
		);
	}

	/**
	 * Register the payment field type.
	 *
	 * @param \FormsKit\Fields $fields Registry.
	 */
	public function register_field( $fields ) {
		$fields->register(
			'payment',
			array(
				'label'  => __( 'Payment (Stripe)', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-money-alt',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render_field' ),
			)
		);
	}

	/**
	 * Render the payment field (shows the amount that will be charged).
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_field( $field, $value, $name ) {
		unset( $value );
		$amount   = isset( $field['amount'] ) ? (float) $field['amount'] : 0;
		$currency = isset( $field['currency'] ) ? strtoupper( $field['currency'] ) : 'USD';

		return sprintf(
			'<div class="formskit-payment"><span class="formskit-payment-amount">%1$s %2$s</span><input type="hidden" name="%3$s" value="%4$s" /></div>',
			esc_html( number_format_i18n( $amount, 2 ) ),
			esc_html( $currency ),
			esc_attr( $name ),
			esc_attr( $amount . ' ' . $currency )
		);
	}

	/**
	 * Keep amount/currency on save.
	 *
	 * @param array $item Clean field.
	 * @param array $raw  Raw field.
	 * @return array
	 */
	public function sanitize_field( $item, $raw ) {
		if ( isset( $item['type'] ) && 'payment' === $item['type'] ) {
			$item['amount']   = isset( $raw['amount'] ) ? (float) $raw['amount'] : 0;
			$item['currency'] = isset( $raw['currency'] ) ? sanitize_text_field( $raw['currency'] ) : 'USD';
		}
		return $item;
	}

	/**
	 * Render Stripe key settings.
	 *
	 * @param array $settings Settings.
	 */
	public function settings_fields( $settings ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Stripe Publishable Key', 'wp-formskit-pro' ); ?></th>
			<td><input type="text" class="regular-text" name="formskit_settings[stripe_pk]" value="<?php echo esc_attr( isset( $settings['stripe_pk'] ) ? $settings['stripe_pk'] : '' ); ?>" placeholder="pk_live_…" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Stripe Secret Key', 'wp-formskit-pro' ); ?></th>
			<td><input type="password" class="regular-text" name="formskit_settings[stripe_sk]" value="<?php echo esc_attr( isset( $settings['stripe_sk'] ) ? $settings['stripe_sk'] : '' ); ?>" placeholder="sk_live_…" /></td>
		</tr>
		<?php
	}

	/**
	 * Persist Stripe keys.
	 *
	 * @param array $clean Clean settings.
	 * @param array $input Raw input.
	 * @return array
	 */
	public function save_settings( $clean, $input ) {
		$clean['stripe_pk'] = isset( $input['stripe_pk'] ) ? sanitize_text_field( $input['stripe_pk'] ) : '';
		$clean['stripe_sk'] = isset( $input['stripe_sk'] ) ? sanitize_text_field( $input['stripe_sk'] ) : '';
		return $clean;
	}

	/**
	 * If the form has a payment field, create a Checkout Session and redirect.
	 *
	 * @param array $result  Result.
	 * @param array $config  Config.
	 * @param int   $form_id Form ID.
	 * @param array $clean   Values.
	 * @return array
	 */
	public function maybe_checkout( $result, $config, $form_id, $clean ) {
		unset( $clean );

		$payment_field = null;
		foreach ( $config['fields'] as $field ) {
			if ( 'payment' === $field['type'] ) {
				$payment_field = $field;
				break;
			}
		}

		$secret = formskit()->get_setting( 'stripe_sk' );
		if ( ! $payment_field || ! $secret ) {
			return $result;
		}

		$amount   = isset( $payment_field['amount'] ) ? (float) $payment_field['amount'] : 0;
		$currency = isset( $payment_field['currency'] ) ? strtolower( $payment_field['currency'] ) : 'usd';
		if ( $amount <= 0 ) {
			return $result;
		}

		$success_url = ! empty( $config['settings']['confirmation']['redirect_url'] )
			? $config['settings']['confirmation']['redirect_url']
			: home_url( '/?formskit_payment=success' );
		$cancel_url  = wp_get_referer() ? wp_get_referer() : home_url();

		$body = array(
			'mode'                                 => 'payment',
			'success_url'                          => $success_url,
			'cancel_url'                           => $cancel_url,
			'line_items[0][price_data][currency]'  => $currency,
			'line_items[0][price_data][product_data][name]' => get_the_title( $form_id ),
			'line_items[0][price_data][unit_amount]' => (int) round( $amount * 100 ),
			'line_items[0][quantity]'              => 1,
			'metadata[form_id]'                    => $form_id,
			'metadata[entry_id]'                   => isset( $result['entry_id'] ) ? $result['entry_id'] : 0,
		);

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $result;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['url'] ) ) {
			return $result;
		}

		// Redirect the user to Stripe's hosted checkout.
		$result['confirmation'] = array(
			'type'         => 'redirect',
			'redirect_url' => esc_url_raw( $data['url'] ),
			'message'      => $result['confirmation']['message'],
		);

		return $result;
	}
}

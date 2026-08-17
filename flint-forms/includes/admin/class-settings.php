<?php
/**
 * Settings screen (registers a settings group and renders the page).
 *
 * @package WPFormsKit
 */

namespace FormsKit\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the setting + sanitizer.
	 */
	public function register() {
		register_setting(
			'formskit_settings_group',
			'formskit_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = get_option( 'formskit_settings', array() );

		$clean['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		/**
		 * Allow add-ons to sanitize their own settings fields.
		 *
		 * @param array $clean Sanitized settings so far.
		 * @param array $input Raw input.
		 */
		return apply_filters( 'formskit_sanitize_settings', $clean, $input );
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		$settings = formskit()->get_setting();
		?>
		<div class="wrap formskit-settings">
			<h1><?php esc_html_e( 'Flint Forms Settings', 'flint-forms' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'formskit_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Delete data on uninstall', 'flint-forms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="formskit_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
								<?php esc_html_e( 'Permanently remove all forms and entries when the plugin is deleted.', 'flint-forms' ); ?>
							</label>
						</td>
					</tr>

					<?php
					/**
					 * Add-ons (Pro) render extra settings rows here.
					 *
					 * @param array $settings Current settings.
					 */
					do_action( 'formskit_settings_fields', $settings );
					?>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php if ( ! \FormsKit\is_pro() ) : ?>
				<div class="formskit-upsell">
					<h2><?php esc_html_e( 'Unlock more with Flint Forms Pro', 'flint-forms' ); ?></h2>
					<p><?php esc_html_e( 'Conditional logic, multi-step forms, file uploads, reCAPTCHA, Stripe payments and integrations — all your free features stay included.', 'flint-forms' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

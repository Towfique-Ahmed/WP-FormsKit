<?php
/**
 * License key handling (settings field + validation scaffold).
 *
 * The remote activation endpoint is intentionally pluggable so the storefront
 * can wire it to its own licensing API.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * License module.
 */
class License {

	const OPTION = 'formskit_pro_license';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'formskit_admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form' ) );
	}

	/**
	 * Register the license submenu.
	 *
	 * @param string $parent Parent slug.
	 */
	public function menu( $parent ) {
		add_submenu_page(
			$parent,
			__( 'License', 'wp-formskit-pro' ),
			__( 'License', 'wp-formskit-pro' ),
			'manage_options',
			'formskit-license',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Current license data.
	 *
	 * @return array
	 */
	protected function get_license() {
		return wp_parse_args(
			get_option( self::OPTION, array() ),
			array(
				'key'    => '',
				'status' => 'inactive',
			)
		);
	}

	/**
	 * Handle activate/deactivate form posts.
	 */
	public function handle_form() {
		if ( empty( $_POST['formskit_license_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['formskit_license_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formskit_license_nonce'] ) ), 'formskit_license' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-formskit-pro' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['formskit_license_action'] ) );
		$key    = isset( $_POST['formskit_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['formskit_license_key'] ) ) : '';

		if ( 'deactivate' === $action ) {
			update_option( self::OPTION, array( 'key' => '', 'status' => 'inactive' ) );
		} else {
			$status = $this->remote_activate( $key );
			update_option( self::OPTION, array( 'key' => $key, 'status' => $status ) );
		}

		wp_safe_redirect( add_query_arg( array( 'post_type' => 'formskit_form', 'page' => 'formskit-license' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Validate a key with the licensing server.
	 *
	 * @param string $key License key.
	 * @return string 'active' | 'invalid'
	 */
	protected function remote_activate( $key ) {
		/**
		 * Filter the license activation result. The storefront replaces this
		 * with a real API call to its licensing endpoint.
		 *
		 * @param string $status Activation status.
		 * @param string $key    License key.
		 */
		$status = apply_filters( 'formskit_pro_activate_license', '', $key );
		if ( $status ) {
			return 'invalid' === $status ? 'invalid' : 'active';
		}

		// Local fallback: accept a well-formed key so the plugin is usable
		// out of the box during development / self-hosting.
		return ( strlen( $key ) >= 16 ) ? 'active' : 'invalid';
	}

	/**
	 * Render the license admin page.
	 */
	public function render_page() {
		$license = $this->get_license();
		$active  = 'active' === $license['status'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP FormsKit Pro License', 'wp-formskit-pro' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'formskit_license', 'formskit_license_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'License Key', 'wp-formskit-pro' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="formskit_license_key" value="<?php echo esc_attr( $license['key'] ); ?>" <?php disabled( $active ); ?> />
							<p class="description">
								<?php
								if ( $active ) {
									echo '<span style="color:#15803d;font-weight:600;">' . esc_html__( 'Active', 'wp-formskit-pro' ) . '</span>';
								} elseif ( 'invalid' === $license['status'] ) {
									echo '<span style="color:#b91c1c;font-weight:600;">' . esc_html__( 'Invalid key', 'wp-formskit-pro' ) . '</span>';
								} else {
									esc_html_e( 'Enter your license key to receive automatic updates.', 'wp-formskit-pro' );
								}
								?>
							</p>
						</td>
					</tr>
				</table>

				<?php if ( $active ) : ?>
					<button type="submit" name="formskit_license_action" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'wp-formskit-pro' ); ?></button>
				<?php else : ?>
					<button type="submit" name="formskit_license_action" value="activate" class="button button-primary"><?php esc_html_e( 'Activate License', 'wp-formskit-pro' ); ?></button>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}

<?php
/**
 * Renders a form to HTML on the front end.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Form renderer.
 */
class Form_Renderer {

	/**
	 * Track whether front-end assets have been enqueued.
	 *
	 * @var bool
	 */
	protected static $assets_enqueued = false;

	/**
	 * Render a form by ID.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $args    Optional overrides (errors, values).
	 * @return string
	 */
	public function render( $form_id, array $args = array() ) {
		$form_id = absint( $form_id );

		if ( ! $form_id || Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="formskit-notice">' . esc_html__( 'WP FormsKit: form not found.', 'wp-formskit' ) . '</p>'
				: '';
		}

		$config = get_form_config( $form_id );
		$fields = $config['fields'];

		if ( empty( $fields ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="formskit-notice">' . esc_html__( 'WP FormsKit: this form has no fields yet.', 'wp-formskit' ) . '</p>'
				: '';
		}

		$this->enqueue_assets();

		$errors = isset( $args['errors'] ) ? (array) $args['errors'] : array();
		$values = isset( $args['values'] ) ? (array) $args['values'] : array();
		$ajax   = ! empty( $config['settings']['ajax'] );

		$instance = 'formskit-form-' . $form_id . '-' . wp_rand( 1000, 9999 );

		ob_start();
		?>
		<div class="formskit-wrap" id="<?php echo esc_attr( $instance ); ?>">
			<form class="formskit-form<?php echo $ajax ? ' formskit-ajax' : ''; ?>"
				method="post"
				enctype="multipart/form-data"
				data-form-id="<?php echo esc_attr( $form_id ); ?>"
				novalidate>

				<?php
				wp_nonce_field( 'formskit_submit_' . $form_id, 'formskit_nonce' );
				/**
				 * Fires just inside the opening <form> tag. Pro uses this for
				 * multi-step wrappers, honeypot markup, etc.
				 *
				 * @param array $config  Form config.
				 * @param int   $form_id Form ID.
				 */
				do_action( 'formskit_form_start', $config, $form_id );
				?>

				<input type="hidden" name="formskit_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
				<input type="hidden" name="formskit_ts" value="<?php echo esc_attr( time() ); ?>" />

				<div class="formskit-fields">
					<?php
					foreach ( $fields as $field ) {
						echo $this->render_field( $field, $values, $errors ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>

				<?php
				/**
				 * Fires before the submit button (e.g. captcha, payment summary).
				 *
				 * @param array $config  Form config.
				 * @param int   $form_id Form ID.
				 */
				do_action( 'formskit_before_submit', $config, $form_id );
				?>

				<div class="formskit-actions">
					<button type="submit" class="formskit-submit">
						<?php echo esc_html( $config['settings']['submit_label'] ); ?>
					</button>
					<span class="formskit-spinner" aria-hidden="true"></span>
				</div>

				<div class="formskit-message" role="alert" aria-live="polite"></div>
			</form>
		</div>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter the rendered form HTML.
		 *
		 * @param string $html    Rendered HTML.
		 * @param array  $config  Form config.
		 * @param int    $form_id Form ID.
		 */
		return apply_filters( 'formskit_rendered_form', $html, $config, $form_id );
	}

	/**
	 * Render one field (wrapper + label + control + error).
	 *
	 * @param array $field  Field definition.
	 * @param array $values Submitted values.
	 * @param array $errors Field errors keyed by name.
	 * @return string
	 */
	public function render_field( array $field, array $values = array(), array $errors = array() ) {
		$field = wp_parse_args(
			$field,
			array(
				'id'          => 'field_' . wp_rand( 1000, 9999 ),
				'type'        => 'text',
				'label'       => '',
				'name'        => '',
				'required'    => false,
				'placeholder' => '',
				'description' => '',
				'width'       => 'full',
				'css_class'   => '',
			)
		);

		$name  = 'formskit_fields[' . $field['name'] . ']';
		$value = isset( $values[ $field['name'] ] ) ? $values[ $field['name'] ] : ( isset( $field['default'] ) ? $field['default'] : '' );
		$error = isset( $errors[ $field['name'] ] ) ? $errors[ $field['name'] ] : '';

		$fields  = formskit()->fields;
		$control = $fields->render_control( $field, $value, $name );

		/**
		 * Filter the field types that render without a label wrapper
		 * (display-only or hidden fields).
		 *
		 * @param array $types Field type slugs.
		 */
		$no_label_types = apply_filters( 'formskit_no_label_types', array( 'hidden', 'html', 'section', 'pagebreak' ) );
		$no_label       = in_array( $field['type'], $no_label_types, true );

		$classes = array(
			'formskit-field',
			'formskit-type-' . sanitize_html_class( $field['type'] ),
			'formskit-width-' . sanitize_html_class( $field['width'] ),
		);
		if ( $field['required'] ) {
			$classes[] = 'formskit-required';
		}
		if ( $error ) {
			$classes[] = 'formskit-has-error';
		}
		if ( $field['css_class'] ) {
			$classes[] = $field['css_class'];
		}

		// Data attributes let Pro attach conditional logic without touching core.
		$data_attrs = apply_filters( 'formskit_field_data_attributes', '', $field );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-field="<?php echo esc_attr( $field['name'] ); ?>" <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php if ( ! $no_label && '' !== $field['label'] ) : ?>
				<label class="formskit-label" for="<?php echo esc_attr( $field['id'] ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( $field['required'] ) : ?>
						<span class="formskit-required-mark" aria-hidden="true">*</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>

			<div class="formskit-control">
				<?php echo $control; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>

			<?php if ( '' !== $field['description'] ) : ?>
				<p class="formskit-description"><?php echo esc_html( $field['description'] ); ?></p>
			<?php endif; ?>

			<span class="formskit-field-error"><?php echo esc_html( $error ); ?></span>
		</div>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter a single rendered field.
		 *
		 * @param string $html  Field HTML.
		 * @param array  $field Field definition.
		 */
		return apply_filters( 'formskit_rendered_field', $html, $field );
	}

	/**
	 * Enqueue front-end assets (once per request, only when a form renders).
	 */
	public function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		wp_enqueue_style( 'formskit', FORMSKIT_URL . 'assets/css/formskit.css', array(), FORMSKIT_VERSION );
		wp_enqueue_script( 'formskit', FORMSKIT_URL . 'assets/js/formskit.js', array(), FORMSKIT_VERSION, true );

		wp_localize_script(
			'formskit',
			'FormsKit',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'error'      => __( 'Something went wrong. Please try again.', 'wp-formskit' ),
					'required'   => __( 'This field is required.', 'wp-formskit' ),
					'validating' => __( 'Sending…', 'wp-formskit' ),
				),
			)
		);

		/**
		 * Fires after core front-end assets enqueue (Pro adds its own here).
		 */
		do_action( 'formskit_enqueue_assets' );
	}
}

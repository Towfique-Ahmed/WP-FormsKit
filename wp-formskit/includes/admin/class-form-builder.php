<?php
/**
 * Form builder meta boxes on the form edit screen + save routine.
 *
 * @package WPFormsKit
 */

namespace FormsKit\Admin;

use FormsKit\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Form builder UI.
 */
class Form_Builder {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'formskit-builder',
			__( 'Form Builder', 'wp-formskit' ),
			array( $this, 'render_builder' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'formskit-embed',
			__( 'Embed', 'wp-formskit' ),
			array( $this, 'render_embed' ),
			Post_Type::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the builder root (populated by JS).
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_builder( $post ) {
		$config = get_post_meta( $post->ID, '_formskit_config', true );
		$config = is_array( $config ) ? $config : array( 'fields' => array(), 'settings' => array() );

		wp_nonce_field( 'formskit_save_form', 'formskit_form_nonce' );
		?>
		<div id="formskit-builder-app" class="formskit-builder">
			<div class="formskit-builder-toolbar">
				<button type="button" class="button" data-formskit-tab="fields"><?php esc_html_e( 'Fields', 'wp-formskit' ); ?></button>
				<button type="button" class="button" data-formskit-tab="settings"><?php esc_html_e( 'Settings', 'wp-formskit' ); ?></button>
				<button type="button" class="button" data-formskit-tab="notifications"><?php esc_html_e( 'Notifications', 'wp-formskit' ); ?></button>
			</div>

			<div class="formskit-builder-body">
				<aside class="formskit-field-palette">
					<h3><?php esc_html_e( 'Add Fields', 'wp-formskit' ); ?></h3>
					<div class="formskit-palette-list"><!-- populated by JS --></div>
				</aside>

				<main class="formskit-builder-canvas">
					<div class="formskit-panel" data-panel="fields">
						<div class="formskit-field-list"><!-- populated by JS --></div>
						<p class="formskit-empty-hint"><?php esc_html_e( 'Click a field on the left to add it here.', 'wp-formskit' ); ?></p>
					</div>
					<div class="formskit-panel" data-panel="settings" hidden>
						<!-- populated by JS -->
					</div>
					<div class="formskit-panel" data-panel="notifications" hidden>
						<!-- populated by JS -->
					</div>
				</main>
			</div>
		</div>

		<textarea id="formskit-config" name="formskit_config" class="hidden" style="display:none;"><?php echo esc_textarea( wp_json_encode( $config ) ); ?></textarea>
		<?php
	}

	/**
	 * Render the embed side box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_embed( $post ) {
		$shortcode = sprintf( '[formskit id="%d"]', $post->ID );
		?>
		<p><?php esc_html_e( 'Copy this shortcode into any post, page or widget:', 'wp-formskit' ); ?></p>
		<input type="text" class="widefat code" readonly value="<?php echo esc_attr( $shortcode ); ?>" onclick="this.select();" />
		<p class="description"><?php esc_html_e( 'Or add the “WP FormsKit Form” block in the editor.', 'wp-formskit' ); ?></p>
		<?php
	}

	/**
	 * Persist the form config on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( $post_id, $post ) {
		unset( $post );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['formskit_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formskit_form_nonce'] ) ), 'formskit_save_form' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['formskit_config'] ) ) {
			return;
		}

		$raw    = wp_unslash( $_POST['formskit_config'] ); // phpcs:ignore WordPress.Security.ValidationSlashesInputVar
		$config = json_decode( $raw, true );

		if ( ! is_array( $config ) ) {
			return;
		}

		$clean = $this->sanitize_config( $config );

		update_post_meta( $post_id, '_formskit_config', $clean );
	}

	/**
	 * Deep-sanitize a submitted config array.
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	protected function sanitize_config( array $config ) {
		$clean = array(
			'fields'   => array(),
			'settings' => array(),
		);

		$registered = formskit()->fields;

		$fields = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : array();
		foreach ( $fields as $index => $field ) {
			if ( empty( $field['type'] ) || ! $registered->exists( $field['type'] ) ) {
				// Unknown type may belong to a deactivated add-on; keep it as-is but sanitized minimally.
				if ( empty( $field['type'] ) ) {
					continue;
				}
			}

			$name = ! empty( $field['name'] ) ? sanitize_key( $field['name'] ) : 'field_' . ( $index + 1 );

			$item = array(
				'id'          => 'formskit-' . $name,
				'type'        => sanitize_key( $field['type'] ),
				'name'        => $name,
				'label'       => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '',
				'placeholder' => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
				'description' => isset( $field['description'] ) ? sanitize_text_field( $field['description'] ) : '',
				'required'    => ! empty( $field['required'] ),
				'width'       => isset( $field['width'] ) && in_array( $field['width'], array( 'full', 'half', 'third' ), true ) ? $field['width'] : 'full',
				'css_class'   => isset( $field['css_class'] ) ? sanitize_html_class( $field['css_class'] ) : '',
			);

			if ( isset( $field['default'] ) ) {
				$item['default'] = sanitize_text_field( $field['default'] );
			}
			if ( 'html' === $item['type'] && isset( $field['content'] ) ) {
				$item['content'] = wp_kses_post( $field['content'] );
			}
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$item['options'] = array();
				foreach ( $field['options'] as $opt ) {
					$label = is_array( $opt ) ? ( isset( $opt['label'] ) ? $opt['label'] : '' ) : $opt;
					$value = is_array( $opt ) && isset( $opt['value'] ) ? $opt['value'] : $label;
					if ( '' === trim( (string) $label ) ) {
						continue;
					}
					$item['options'][] = array(
						'label' => sanitize_text_field( $label ),
						'value' => sanitize_text_field( $value ),
					);
				}
			}

			/**
			 * Let add-ons sanitize/keep their own field properties (e.g. conditional logic).
			 *
			 * @param array $item  Clean field.
			 * @param array $field Raw field.
			 */
			$item = apply_filters( 'formskit_sanitize_field', $item, $field );

			$clean['fields'][] = $item;
		}

		$settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();

		$clean['settings'] = array(
			'submit_label'  => isset( $settings['submit_label'] ) && '' !== trim( $settings['submit_label'] ) ? sanitize_text_field( $settings['submit_label'] ) : __( 'Submit', 'wp-formskit' ),
			'ajax'          => ! empty( $settings['ajax'] ),
			'store_entries' => ! empty( $settings['store_entries'] ),
			'confirmation'  => array(
				'type'         => isset( $settings['confirmation']['type'] ) && 'redirect' === $settings['confirmation']['type'] ? 'redirect' : 'message',
				'message'      => isset( $settings['confirmation']['message'] ) ? wp_kses_post( $settings['confirmation']['message'] ) : '',
				'redirect_url' => isset( $settings['confirmation']['redirect_url'] ) ? esc_url_raw( $settings['confirmation']['redirect_url'] ) : '',
			),
			'notification'  => array(
				'enabled'  => ! empty( $settings['notification']['enabled'] ),
				'to'       => isset( $settings['notification']['to'] ) ? sanitize_text_field( $settings['notification']['to'] ) : '{admin_email}',
				'subject'  => isset( $settings['notification']['subject'] ) ? sanitize_text_field( $settings['notification']['subject'] ) : '',
				'message'  => isset( $settings['notification']['message'] ) ? sanitize_textarea_field( $settings['notification']['message'] ) : '{all_fields}',
				'reply_to' => isset( $settings['notification']['reply_to'] ) ? sanitize_text_field( $settings['notification']['reply_to'] ) : '',
			),
			'spam'          => array(
				'honeypot' => ! isset( $settings['spam']['honeypot'] ) || ! empty( $settings['spam']['honeypot'] ),
				'min_time' => isset( $settings['spam']['min_time'] ) ? absint( $settings['spam']['min_time'] ) : 3,
			),
		);

		/**
		 * Let add-ons persist extra settings (e.g. Pro multi-step, reCAPTCHA toggle).
		 *
		 * @param array $clean_settings Clean settings.
		 * @param array $settings       Raw settings.
		 */
		$clean['settings'] = apply_filters( 'formskit_sanitize_settings_meta', $clean['settings'], $settings );

		return $clean;
	}
}

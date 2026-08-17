<?php
/**
 * Field type registry — rendering, sanitizing and validating fields.
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of available field types.
 */
class Fields {

	/**
	 * Registered field types.
	 *
	 * @var array<string,array>
	 */
	protected $types = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_defaults();

		// Fire the registration action on `init` so add-ons (loaded on
		// `formskit_loaded`) have already attached their listeners.
		add_action( 'init', array( $this, 'register_addon_fields' ), 5 );
	}

	/**
	 * Let add-ons register field types.
	 */
	public function register_addon_fields() {
		/**
		 * Allow add-ons to register field types.
		 *
		 * @param Fields $fields The registry instance.
		 */
		do_action( 'formskit_register_fields', $this );
	}

	/**
	 * Register a field type.
	 *
	 * @param string $type Type slug.
	 * @param array  $args Type definition (label, icon, group, has_options, render, sanitize, validate).
	 */
	public function register( $type, array $args ) {
		$this->types[ $type ] = wp_parse_args(
			$args,
			array(
				'label'       => ucfirst( $type ),
				'icon'        => 'dashicons-editor-textcolor',
				'group'       => 'standard',
				'has_options' => false,
				'render'      => null,
				'sanitize'    => null,
				'validate'    => null,
			)
		);
	}

	/**
	 * Get all registered types.
	 *
	 * @return array<string,array>
	 */
	public function all() {
		/**
		 * Filter the full list of registered field types.
		 *
		 * @param array $types Registered types.
		 */
		return apply_filters( 'formskit_field_types', $this->types );
	}

	/**
	 * Whether a type is registered.
	 *
	 * @param string $type Type slug.
	 * @return bool
	 */
	public function exists( $type ) {
		return isset( $this->types[ $type ] );
	}

	/**
	 * Register the built-in field types.
	 */
	protected function register_defaults() {
		$text_like = array( 'text', 'email', 'number', 'tel', 'url', 'date' );

		$this->register( 'text', array( 'label' => __( 'Single Line Text', 'wp-formskit' ), 'icon' => 'dashicons-editor-textcolor' ) );
		$this->register( 'textarea', array( 'label' => __( 'Paragraph Text', 'wp-formskit' ), 'icon' => 'dashicons-editor-paragraph', 'render' => array( $this, 'render_textarea' ) ) );
		$this->register( 'email', array( 'label' => __( 'Email', 'wp-formskit' ), 'icon' => 'dashicons-email', 'sanitize' => 'sanitize_email', 'validate' => array( $this, 'validate_email' ) ) );
		$this->register( 'number', array( 'label' => __( 'Number', 'wp-formskit' ), 'icon' => 'dashicons-calculator' ) );
		$this->register( 'tel', array( 'label' => __( 'Phone', 'wp-formskit' ), 'icon' => 'dashicons-phone' ) );
		$this->register( 'url', array( 'label' => __( 'Website / URL', 'wp-formskit' ), 'icon' => 'dashicons-admin-links', 'sanitize' => 'esc_url_raw' ) );
		$this->register( 'date', array( 'label' => __( 'Date', 'wp-formskit' ), 'icon' => 'dashicons-calendar-alt' ) );
		$this->register( 'name', array( 'label' => __( 'Name', 'wp-formskit' ), 'icon' => 'dashicons-admin-users' ) );
		$this->register( 'select', array( 'label' => __( 'Dropdown', 'wp-formskit' ), 'icon' => 'dashicons-menu', 'has_options' => true, 'render' => array( $this, 'render_select' ) ) );
		$this->register( 'radio', array( 'label' => __( 'Multiple Choice', 'wp-formskit' ), 'icon' => 'dashicons-marker', 'has_options' => true, 'render' => array( $this, 'render_choices' ) ) );
		$this->register( 'checkbox', array( 'label' => __( 'Checkboxes', 'wp-formskit' ), 'icon' => 'dashicons-yes', 'has_options' => true, 'render' => array( $this, 'render_choices' ), 'sanitize' => array( $this, 'sanitize_array' ) ) );
		$this->register( 'hidden', array( 'label' => __( 'Hidden', 'wp-formskit' ), 'icon' => 'dashicons-hidden', 'render' => array( $this, 'render_hidden' ) ) );
		$this->register( 'html', array( 'label' => __( 'HTML Block', 'wp-formskit' ), 'icon' => 'dashicons-editor-code', 'group' => 'layout', 'render' => array( $this, 'render_html' ) ) );

		unset( $text_like );
	}

	/**
	 * Sanitize a submitted value for a field.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function sanitize( array $field, $value ) {
		$type = $field['type'];
		$def  = isset( $this->types[ $type ] ) ? $this->types[ $type ] : array();

		if ( ! empty( $def['sanitize'] ) && is_callable( $def['sanitize'] ) ) {
			return call_user_func( $def['sanitize'], $value );
		}

		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', wp_unslash( $value ) );
		}

		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( wp_unslash( $value ) );
		}

		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Validate a submitted value. Returns an error string or empty string.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Sanitized value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		$is_empty = is_array( $value ) ? 0 === count( array_filter( $value, 'strlen' ) ) : ( '' === trim( (string) $value ) );

		/**
		 * Filter whether a field is considered empty. File uploads use this so
		 * a required file field checks $_FILES instead of the POST value.
		 *
		 * @param bool  $is_empty Whether empty.
		 * @param array $field    Field definition.
		 * @param mixed $value    Submitted value.
		 */
		$is_empty = (bool) apply_filters( 'formskit_field_is_empty', $is_empty, $field, $value );

		if ( ! empty( $field['required'] ) && $is_empty ) {
			return __( 'This field is required.', 'wp-formskit' );
		}

		if ( $is_empty ) {
			return '';
		}

		$def = isset( $this->types[ $field['type'] ] ) ? $this->types[ $field['type'] ] : array();
		if ( ! empty( $def['validate'] ) && is_callable( $def['validate'] ) ) {
			$error = call_user_func( $def['validate'], $value, $field );
			if ( $error ) {
				return $error;
			}
		}

		/**
		 * Filter the validation result for a field (used by Pro for file uploads etc.).
		 *
		 * @param string $error Error message (empty if valid).
		 * @param array  $field Field definition.
		 * @param mixed  $value Submitted value.
		 */
		return apply_filters( 'formskit_validate_field', '', $field, $value );
	}

	/* --------------------------------------------------------------------- *
	 * Validators
	 * --------------------------------------------------------------------- */

	/**
	 * Validate an email value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public function validate_email( $value ) {
		return is_email( $value ) ? '' : __( 'Please enter a valid email address.', 'wp-formskit' );
	}

	/* --------------------------------------------------------------------- *
	 * Control rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Render the input control for a field (no wrapper/label).
	 *
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 * @param string $name  HTML name attribute.
	 * @return string
	 */
	public function render_control( array $field, $value, $name ) {
		$def = isset( $this->types[ $field['type'] ] ) ? $this->types[ $field['type'] ] : array();

		if ( ! empty( $def['render'] ) && is_callable( $def['render'] ) ) {
			return (string) call_user_func( $def['render'], $field, $value, $name );
		}

		// Default: a single-line input mapped from the field type.
		$html_types = array(
			'text'   => 'text',
			'email'  => 'email',
			'number' => 'number',
			'tel'    => 'tel',
			'url'    => 'url',
			'date'   => 'date',
			'name'   => 'text',
		);
		$input_type = isset( $html_types[ $field['type'] ] ) ? $html_types[ $field['type'] ] : 'text';

		return sprintf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" class="formskit-input" %6$s />',
			esc_attr( $input_type ),
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			esc_attr( is_array( $value ) ? '' : $value ),
			esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
			! empty( $field['required'] ) ? 'aria-required="true"' : ''
		);
	}

	/**
	 * Render a textarea control.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_textarea( $field, $value, $name ) {
		return sprintf(
			'<textarea id="%1$s" name="%2$s" rows="5" placeholder="%3$s" class="formskit-input" %4$s>%5$s</textarea>',
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
			! empty( $field['required'] ) ? 'aria-required="true"' : '',
			esc_textarea( is_array( $value ) ? '' : $value )
		);
	}

	/**
	 * Render a select control.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_select( $field, $value, $name ) {
		$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$out     = sprintf(
			'<select id="%1$s" name="%2$s" class="formskit-input" %3$s>',
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			! empty( $field['required'] ) ? 'aria-required="true"' : ''
		);
		$out .= '<option value="">' . esc_html( isset( $field['placeholder'] ) && $field['placeholder'] ? $field['placeholder'] : __( '— Select —', 'wp-formskit' ) ) . '</option>';
		foreach ( $options as $option ) {
			$label = is_array( $option ) ? $option['label'] : $option;
			$val   = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $label;
			$out  .= sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $val ), selected( $value, $val, false ), esc_html( $label ) );
		}
		$out .= '</select>';
		return $out;
	}

	/**
	 * Render radio/checkbox choices.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_choices( $field, $value, $name ) {
		$is_checkbox = 'checkbox' === $field['type'];
		$input_type  = $is_checkbox ? 'checkbox' : 'radio';
		$options     = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$selected    = (array) $value;
		$field_name  = $is_checkbox ? $name . '[]' : $name;

		$out = '<span class="formskit-choices" role="group">';
		foreach ( $options as $i => $option ) {
			$label   = is_array( $option ) ? $option['label'] : $option;
			$val     = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $label;
			$item_id = esc_attr( $field['id'] . '-' . $i );
			$out    .= sprintf(
				'<label class="formskit-choice" for="%1$s"><input type="%2$s" id="%1$s" name="%3$s" value="%4$s" %5$s /> <span>%6$s</span></label>',
				$item_id,
				esc_attr( $input_type ),
				esc_attr( $field_name ),
				esc_attr( $val ),
				in_array( (string) $val, array_map( 'strval', $selected ), true ) ? 'checked' : '',
				esc_html( $label )
			);
		}
		$out .= '</span>';
		return $out;
	}

	/**
	 * Render a hidden field.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_hidden( $field, $value, $name ) {
		$default = isset( $field['default'] ) ? $field['default'] : '';
		return sprintf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$s" />',
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			esc_attr( '' !== (string) $value ? $value : $default )
		);
	}

	/**
	 * Render an HTML block (no input).
	 *
	 * @param array $field Field.
	 * @return string
	 */
	public function render_html( $field ) {
		$content = isset( $field['content'] ) ? $field['content'] : '';
		return '<div class="formskit-html-block">' . wp_kses_post( $content ) . '</div>';
	}

	/**
	 * Sanitize a checkbox array.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	public function sanitize_array( $value ) {
		return is_array( $value ) ? array_map( 'sanitize_text_field', wp_unslash( $value ) ) : array();
	}
}

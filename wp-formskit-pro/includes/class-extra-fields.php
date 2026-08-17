<?php
/**
 * Registers Pro-only field types.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * Advanced field types.
 */
class Extra_Fields {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'formskit_register_fields', array( $this, 'register' ) );
	}

	/**
	 * Register the Pro field types on the core registry.
	 *
	 * @param \FormsKit\Fields $fields Registry.
	 */
	public function register( $fields ) {
		$fields->register(
			'rating',
			array(
				'label'  => __( 'Star Rating', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-star-filled',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render_rating' ),
			)
		);

		$fields->register(
			'range',
			array(
				'label'  => __( 'Range Slider', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-leftright',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render_range' ),
			)
		);

		$fields->register(
			'password',
			array(
				'label'  => __( 'Password', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-lock',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render_password' ),
			)
		);

		$fields->register(
			'section',
			array(
				'label'  => __( 'Section Divider', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-minus',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render_section' ),
			)
		);

		$fields->register(
			'pagebreak',
			array(
				'label'  => __( 'Page Break', 'wp-formskit-pro' ),
				'icon'   => 'dashicons-flag',
				'group'  => 'pro',
				'pro'    => true,
				'render' => array( $this, 'render_pagebreak' ),
			)
		);
	}

	/**
	 * Star rating control.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_rating( $field, $value, $name ) {
		$max   = isset( $field['max'] ) ? absint( $field['max'] ) : 5;
		$value = absint( $value );
		$out   = '<span class="formskit-rating" role="radiogroup">';
		for ( $i = 1; $i <= $max; $i++ ) {
			$id   = esc_attr( $field['id'] . '-' . $i );
			$out .= sprintf(
				'<label class="formskit-rating-star" for="%1$s"><input type="radio" id="%1$s" name="%2$s" value="%3$d" %4$s /><span class="dashicons dashicons-star-filled" aria-hidden="true"></span></label>',
				$id,
				esc_attr( $name ),
				$i,
				checked( $value, $i, false )
			);
		}
		$out .= '</span>';
		return $out;
	}

	/**
	 * Range slider control.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_range( $field, $value, $name ) {
		$min  = isset( $field['min'] ) ? (int) $field['min'] : 0;
		$max  = isset( $field['max'] ) ? (int) $field['max'] : 100;
		$step = isset( $field['step'] ) ? (int) $field['step'] : 1;
		$val  = '' !== (string) $value ? (int) $value : $min;
		return sprintf(
			'<span class="formskit-range"><input type="range" id="%1$s" name="%2$s" min="%3$d" max="%4$d" step="%5$d" value="%6$d" class="formskit-range-input" oninput="this.nextElementSibling.textContent=this.value" /><output class="formskit-range-output">%6$d</output></span>',
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			$min,
			$max,
			$step,
			$val
		);
	}

	/**
	 * Password control.
	 *
	 * @param array  $field Field.
	 * @param mixed  $value Value.
	 * @param string $name  Name.
	 * @return string
	 */
	public function render_password( $field, $value, $name ) {
		unset( $value );
		return sprintf(
			'<input type="password" id="%1$s" name="%2$s" placeholder="%3$s" class="formskit-input" autocomplete="new-password" %4$s />',
			esc_attr( $field['id'] ),
			esc_attr( $name ),
			esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
			! empty( $field['required'] ) ? 'aria-required="true"' : ''
		);
	}

	/**
	 * Section divider (display only).
	 *
	 * @param array $field Field.
	 * @return string
	 */
	public function render_section( $field ) {
		$title = isset( $field['label'] ) ? $field['label'] : '';
		$desc  = isset( $field['description'] ) ? $field['description'] : '';
		$out   = '<div class="formskit-section-divider">';
		if ( '' !== $title ) {
			$out .= '<h3 class="formskit-section-title">' . esc_html( $title ) . '</h3>';
		}
		if ( '' !== $desc ) {
			$out .= '<p class="formskit-section-desc">' . esc_html( $desc ) . '</p>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Page break marker (consumed by the multi-step front-end script).
	 *
	 * @param array $field Field.
	 * @return string
	 */
	public function render_pagebreak( $field ) {
		$next = isset( $field['next_label'] ) && $field['next_label'] ? $field['next_label'] : __( 'Next', 'wp-formskit-pro' );
		$prev = isset( $field['prev_label'] ) && $field['prev_label'] ? $field['prev_label'] : __( 'Previous', 'wp-formskit-pro' );
		return sprintf(
			'<span class="formskit-pagebreak" data-next="%s" data-prev="%s" hidden></span>',
			esc_attr( $next ),
			esc_attr( $prev )
		);
	}
}

<?php
/**
 * Conditional logic — persist per-field rules and expose them to the front end.
 *
 * @package WPFormsKitPro
 */

namespace FormsKitPro;

defined( 'ABSPATH' ) || exit;

/**
 * Conditional logic module.
 */
class Conditional_Logic {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'formskit_sanitize_field', array( $this, 'sanitize_field' ), 10, 2 );
		add_filter( 'formskit_field_data_attributes', array( $this, 'data_attributes' ), 10, 2 );
		add_filter( 'formskit_submission_fields', array( $this, 'filter_hidden_fields' ), 10, 2 );
	}

	/**
	 * Remove fields hidden by their conditional rules so they are not validated
	 * or stored server-side (mirrors the front-end evaluation).
	 *
	 * @param array $fields    Field definitions.
	 * @param array $submitted Submitted values keyed by field name.
	 * @return array
	 */
	public function filter_hidden_fields( $fields, $submitted ) {
		$visible = array();
		foreach ( $fields as $field ) {
			if ( empty( $field['conditional']['enabled'] ) || empty( $field['conditional']['rules'] ) ) {
				$visible[] = $field;
				continue;
			}
			if ( $this->is_visible( $field['conditional'], $submitted ) ) {
				$visible[] = $field;
			}
		}
		return $visible;
	}

	/**
	 * Evaluate a conditional config against submitted values.
	 *
	 * @param array $conditional Conditional config.
	 * @param array $submitted   Submitted values.
	 * @return bool Whether the field should be visible.
	 */
	protected function is_visible( array $conditional, array $submitted ) {
		$results = array();
		foreach ( $conditional['rules'] as $rule ) {
			$results[] = $this->test_rule( $rule, $submitted );
		}

		$matched = ( 'any' === $conditional['logic'] )
			? in_array( true, $results, true )
			: ! in_array( false, $results, true );

		return ( 'hide' === $conditional['action'] ) ? ! $matched : $matched;
	}

	/**
	 * Test a single rule.
	 *
	 * @param array $rule      Rule.
	 * @param array $submitted Submitted values.
	 * @return bool
	 */
	protected function test_rule( array $rule, array $submitted ) {
		$raw = isset( $submitted[ $rule['field'] ] ) ? $submitted[ $rule['field'] ] : '';
		$val = is_array( $raw ) ? implode( ',', $raw ) : (string) $raw;
		$cmp = isset( $rule['value'] ) ? (string) $rule['value'] : '';

		switch ( $rule['operator'] ) {
			case 'is':
				return $val === $cmp;
			case 'is_not':
				return $val !== $cmp;
			case 'contains':
				return '' !== $cmp && false !== strpos( $val, $cmp );
			case 'gt':
				return (float) $val > (float) $cmp;
			case 'lt':
				return (float) $val < (float) $cmp;
			case 'empty':
				return '' === $val;
			case 'not_empty':
				return '' !== $val;
			default:
				return false;
		}
	}

	/**
	 * Keep conditional-logic config when a field is saved.
	 *
	 * @param array $item  Clean field.
	 * @param array $raw   Raw field.
	 * @return array
	 */
	public function sanitize_field( $item, $raw ) {
		if ( empty( $raw['conditional'] ) || ! is_array( $raw['conditional'] ) ) {
			return $item;
		}

		$c = $raw['conditional'];

		$rules = array();
		if ( ! empty( $c['rules'] ) && is_array( $c['rules'] ) ) {
			foreach ( $c['rules'] as $rule ) {
				if ( empty( $rule['field'] ) ) {
					continue;
				}
				$rules[] = array(
					'field'    => sanitize_key( $rule['field'] ),
					'operator' => isset( $rule['operator'] ) && in_array( $rule['operator'], array( 'is', 'is_not', 'contains', 'gt', 'lt', 'empty', 'not_empty' ), true ) ? $rule['operator'] : 'is',
					'value'    => isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '',
				);
			}
		}

		if ( empty( $rules ) ) {
			return $item;
		}

		$item['conditional'] = array(
			'enabled' => ! empty( $c['enabled'] ),
			'action'  => isset( $c['action'] ) && 'hide' === $c['action'] ? 'hide' : 'show',
			'logic'   => isset( $c['logic'] ) && 'any' === $c['logic'] ? 'any' : 'all',
			'rules'   => $rules,
		);

		return $item;
	}

	/**
	 * Emit a data attribute carrying the conditional config for the front end.
	 *
	 * @param string $attrs Existing attributes.
	 * @param array  $field Field.
	 * @return string
	 */
	public function data_attributes( $attrs, $field ) {
		if ( empty( $field['conditional']['enabled'] ) || empty( $field['conditional']['rules'] ) ) {
			return $attrs;
		}
		$json   = wp_json_encode( $field['conditional'] );
		$attrs .= " data-formskit-conditional='" . esc_attr( $json ) . "'";
		return $attrs;
	}
}

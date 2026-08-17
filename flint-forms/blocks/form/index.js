( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	var forms = ( window.FormsKitBlock && window.FormsKitBlock.forms ) || [];

	var options = [ { label: __( '— Select a form —', 'flint-forms' ), value: 0 } ].concat(
		forms.map( function ( f ) {
			return { label: f.title, value: parseInt( f.id, 10 ) };
		} )
	);

	registerBlockType( 'formskit/form', {
		edit: function ( props ) {
			var formId = props.attributes.formId;
			var blockProps = useBlockProps();

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Form Settings', 'flint-forms' ) },
					el( SelectControl, {
						label: __( 'Select form', 'flint-forms' ),
						value: formId,
						options: options,
						onChange: function ( value ) {
							props.setAttributes( { formId: parseInt( value, 10 ) } );
						},
					} )
				)
			);

			var body;
			if ( ! formId ) {
				body = el(
					Placeholder,
					{
						icon: 'feedback',
						label: __( 'Flint Forms', 'flint-forms' ),
						instructions: __( 'Choose a form to display.', 'flint-forms' ),
					},
					el( SelectControl, {
						value: formId,
						options: options,
						onChange: function ( value ) {
							props.setAttributes( { formId: parseInt( value, 10 ) } );
						},
					} )
				);
			} else {
				body = el( ServerSideRender, {
					block: 'formskit/form',
					attributes: { formId: formId },
				} );
			}

			return el( 'div', blockProps, inspector, body );
		},
		save: function () {
			return null; // Rendered server-side.
		},
	} );
} )( window.wp );

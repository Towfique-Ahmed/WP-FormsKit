/* Pro builder enhancements: per-field conditional logic + form-level settings. */
( function () {
	'use strict';

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	var OPERATORS = [
		[ 'is', 'is' ],
		[ 'is_not', 'is not' ],
		[ 'contains', 'contains' ],
		[ 'gt', 'greater than' ],
		[ 'lt', 'less than' ],
		[ 'empty', 'is empty' ],
		[ 'not_empty', 'is not empty' ],
	];

	/* ---------------- Conditional logic per field ---------------- */

	document.addEventListener( 'formskit:render-field-card', function ( e ) {
		var field = e.detail.field;
		var body = e.detail.body;
		var allFields = e.detail.fields || [];
		var sync = e.detail.sync;

		if ( ! body || field.type === 'html' || field.type === 'pagebreak' || field.type === 'section' ) {
			return;
		}

		field.conditional = field.conditional || { enabled: false, action: 'show', logic: 'all', rules: [] };
		var c = field.conditional;

		var others = allFields.filter( function ( f ) {
			return f.name !== field.name && f.type !== 'html' && f.type !== 'pagebreak' && f.type !== 'section';
		} );

		var box = document.createElement( 'div' );
		box.className = 'formskit-conditional-box';

		function fieldOptions( selected ) {
			return others
				.map( function ( f ) {
					return '<option value="' + esc( f.name ) + '"' + ( f.name === selected ? ' selected' : '' ) + '>' + esc( f.label || f.name ) + '</option>';
				} )
				.join( '' );
		}

		function opOptions( selected ) {
			return OPERATORS.map( function ( o ) {
				return '<option value="' + o[ 0 ] + '"' + ( o[ 0 ] === selected ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>';
			} ).join( '' );
		}

		function render() {
			var rulesHtml = c.rules
				.map( function ( rule, i ) {
					return (
						'<div class="formskit-cond-rule" data-rule="' + i + '">' +
						'<select data-cond="field">' + fieldOptions( rule.field ) + '</select> ' +
						'<select data-cond="operator">' + opOptions( rule.operator ) + '</select> ' +
						'<input type="text" data-cond="value" value="' + esc( rule.value || '' ) + '" placeholder="value" /> ' +
						'<button type="button" class="button-link formskit-cond-remove" data-rule="' + i + '">&times;</button>' +
						'</div>'
					);
				} )
				.join( '' );

			box.innerHTML =
				'<hr /><div class="formskit-row"><label><input type="checkbox" data-cond="enabled"' + ( c.enabled ? ' checked' : '' ) + '> ' + esc( 'Enable conditional logic' ) + '</label></div>' +
				'<div class="formskit-cond-config"' + ( c.enabled ? '' : ' hidden' ) + '>' +
				'<div class="formskit-row formskit-inline">' +
				'<select data-cond="action"><option value="show"' + ( c.action === 'show' ? ' selected' : '' ) + '>Show</option><option value="hide"' + ( c.action === 'hide' ? ' selected' : '' ) + '>Hide</option></select>' +
				'<span>this field if</span>' +
				'<select data-cond="logic"><option value="all"' + ( c.logic === 'all' ? ' selected' : '' ) + '>all</option><option value="any"' + ( c.logic === 'any' ? ' selected' : '' ) + '>any</option></select>' +
				'<span>of the following match:</span>' +
				'</div>' +
				'<div class="formskit-cond-rules">' + ( rulesHtml || '<em>No rules yet.</em>' ) + '</div>' +
				'<button type="button" class="button formskit-cond-add">' + esc( '+ Add rule' ) + '</button>' +
				'</div>';

			bind();
		}

		function bind() {
			box.querySelector( '[data-cond="enabled"]' ).addEventListener( 'change', function () {
				c.enabled = this.checked;
				sync();
				render();
			} );

			[ 'action', 'logic' ].forEach( function ( key ) {
				var el = box.querySelector( '[data-cond="' + key + '"]' );
				if ( el ) {
					el.addEventListener( 'change', function () {
						c[ key ] = this.value;
						sync();
					} );
				}
			} );

			var addBtn = box.querySelector( '.formskit-cond-add' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					c.rules.push( { field: others.length ? others[ 0 ].name : '', operator: 'is', value: '' } );
					sync();
					render();
				} );
			}

			box.querySelectorAll( '.formskit-cond-rule' ).forEach( function ( ruleEl ) {
				var i = parseInt( ruleEl.getAttribute( 'data-rule' ), 10 );
				ruleEl.querySelectorAll( '[data-cond]' ).forEach( function ( input ) {
					input.addEventListener( 'change', function () {
						c.rules[ i ][ input.getAttribute( 'data-cond' ) ] = input.value;
						sync();
					} );
					input.addEventListener( 'input', function () {
						c.rules[ i ][ input.getAttribute( 'data-cond' ) ] = input.value;
						sync();
					} );
				} );
			} );

			box.querySelectorAll( '.formskit-cond-remove' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					c.rules.splice( parseInt( this.getAttribute( 'data-rule' ), 10 ), 1 );
					sync();
					render();
				} );
			} );
		}

		render();
		body.appendChild( box );
	} );

	/* ---------------- Form-level Pro settings ---------------- */

	document.addEventListener( 'formskit:render-settings', function ( e ) {
		var state = e.detail.state;
		var container = e.detail.container;
		var sync = e.detail.sync;
		if ( ! container ) {
			return;
		}

		state.settings.multistep = state.settings.multistep || { enabled: false, progress: true };
		state.settings.integrations = state.settings.integrations || {};
		var ms = state.settings.multistep;
		var integ = state.settings.integrations;
		integ.webhook = integ.webhook || { enabled: false, url: '' };
		integ.slack = integ.slack || { enabled: false, url: '' };

		container.innerHTML =
			'<hr /><h3>' + esc( 'Multi-step (Pro)' ) + '</h3>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-ms-on"' + ( ms.enabled ? ' checked' : '' ) + '> ' + esc( 'Enable multi-step (add "Page Break" fields to split pages)' ) + '</label></div>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-ms-progress"' + ( ms.progress ? ' checked' : '' ) + '> ' + esc( 'Show progress bar' ) + '</label></div>' +
			'<hr /><h3>' + esc( 'Integrations (Pro)' ) + '</h3>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-wh-on"' + ( integ.webhook.enabled ? ' checked' : '' ) + '> ' + esc( 'Send to webhook URL' ) + '</label>' +
			'<input type="url" class="widefat" id="fk-wh-url" value="' + esc( integ.webhook.url || '' ) + '" placeholder="https://…" /></div>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-sl-on"' + ( integ.slack.enabled ? ' checked' : '' ) + '> ' + esc( 'Send to Slack' ) + '</label>' +
			'<input type="url" class="widefat" id="fk-sl-url" value="' + esc( integ.slack.url || '' ) + '" placeholder="https://hooks.slack.com/…" /></div>';

		function on( id, handler, evt ) {
			var el = container.querySelector( '#' + id );
			if ( el ) {
				el.addEventListener( evt || 'change', function () {
					handler( el );
					sync();
				} );
			}
		}

		on( 'fk-ms-on', function ( el ) { ms.enabled = el.checked; } );
		on( 'fk-ms-progress', function ( el ) { ms.progress = el.checked; } );
		on( 'fk-wh-on', function ( el ) { integ.webhook.enabled = el.checked; } );
		on( 'fk-wh-url', function ( el ) { integ.webhook.url = el.value; }, 'input' );
		on( 'fk-sl-on', function ( el ) { integ.slack.enabled = el.checked; } );
		on( 'fk-sl-url', function ( el ) { integ.slack.url = el.value; }, 'input' );
	} );
} )();

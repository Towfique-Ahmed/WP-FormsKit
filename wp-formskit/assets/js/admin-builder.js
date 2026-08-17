/* global FormsKitAdmin, jQuery */
( function ( $ ) {
	'use strict';

	var app = document.getElementById( 'formskit-builder-app' );
	if ( ! app || typeof FormsKitAdmin === 'undefined' ) {
		return;
	}

	var configField = document.getElementById( 'formskit-config' );
	var types = FormsKitAdmin.fieldTypes || {};
	var i18n = FormsKitAdmin.i18n || {};

	var state;
	try {
		state = JSON.parse( configField.value || '{}' );
	} catch ( e ) {
		state = {};
	}
	state.fields = Array.isArray( state.fields ) ? state.fields : [];
	state.settings = state.settings || {};

	var listEl = app.querySelector( '.formskit-field-list' );
	var paletteEl = app.querySelector( '.formskit-palette-list' );
	var hintEl = app.querySelector( '.formskit-empty-hint' );
	var panels = {
		fields: app.querySelector( '[data-panel="fields"]' ),
		settings: app.querySelector( '[data-panel="settings"]' ),
		notifications: app.querySelector( '[data-panel="notifications"]' ),
	};

	/* ---------- helpers ---------- */

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function slugify( s ) {
		return String( s || '' )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' );
	}

	function uniqueName( base ) {
		var name = base || 'field';
		var i = 1;
		var used = state.fields.map( function ( f ) {
			return f.name;
		} );
		var candidate = name;
		while ( used.indexOf( candidate ) !== -1 ) {
			i++;
			candidate = name + '_' + i;
		}
		return candidate;
	}

	function sync() {
		configField.value = JSON.stringify( state );
	}

	/* ---------- palette ---------- */

	function renderPalette() {
		paletteEl.innerHTML = '';
		Object.keys( types ).forEach( function ( slug ) {
			var t = types[ slug ];
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'formskit-palette-item' + ( t.pro && ! FormsKitAdmin.isPro ? ' is-pro' : '' );
			btn.setAttribute( 'data-type', slug );
			btn.innerHTML =
				'<span class="dashicons ' + esc( t.icon ) + '"></span> <span>' + esc( t.label ) + '</span>' +
				( t.pro && ! FormsKitAdmin.isPro ? '<span class="formskit-pro-badge">PRO</span>' : '' );
			btn.addEventListener( 'click', function () {
				if ( t.pro && ! FormsKitAdmin.isPro ) {
					window.alert( i18n.proField );
					return;
				}
				addField( slug );
			} );
			paletteEl.appendChild( btn );
		} );
	}

	/* ---------- fields ---------- */

	function addField( type ) {
		var t = types[ type ] || {};
		var name = uniqueName( slugify( t.label || type ) );
		var field = {
			type: type,
			name: name,
			label: t.label || i18n.newField,
			placeholder: '',
			description: '',
			required: false,
			width: 'full',
		};
		if ( t.has_options ) {
			field.options = [
				{ label: i18n.option + ' 1', value: i18n.option + ' 1' },
				{ label: i18n.option + ' 2', value: i18n.option + ' 2' },
			];
		}
		if ( type === 'html' ) {
			field.content = '';
		}
		state.fields.push( field );
		renderFields();
		sync();
	}

	function removeField( index ) {
		if ( ! window.confirm( i18n.confirmDel ) ) {
			return;
		}
		state.fields.splice( index, 1 );
		renderFields();
		sync();
	}

	function fieldCard( field, index ) {
		var t = types[ field.type ] || {};
		var card = document.createElement( 'div' );
		card.className = 'formskit-field-card';
		card.setAttribute( 'data-index', index );

		var optionsHtml = '';
		if ( t.has_options ) {
			optionsHtml =
				'<div class="formskit-row formskit-options-row"><label>' + esc( 'Options (one per line)' ) + '</label>' +
				'<textarea class="widefat" data-prop="options" rows="4">' +
				esc(
					( field.options || [] )
						.map( function ( o ) {
							return typeof o === 'object' ? o.label : o;
						} )
						.join( '\n' )
				) +
				'</textarea></div>';
		}

		var htmlContent = '';
		if ( field.type === 'html' ) {
			htmlContent =
				'<div class="formskit-row"><label>' + esc( 'HTML content' ) + '</label>' +
				'<textarea class="widefat" data-prop="content" rows="4">' + esc( field.content || '' ) + '</textarea></div>';
		}

		card.innerHTML =
			'<div class="formskit-field-card-head">' +
			'<span class="dashicons ' + esc( t.icon || 'dashicons-menu' ) + '"></span>' +
			'<strong class="formskit-field-title">' + esc( field.label || i18n.untitled ) + '</strong>' +
			'<span class="formskit-field-type-badge">' + esc( t.label || field.type ) + '</span>' +
			'<button type="button" class="button-link formskit-toggle-edit">' + esc( 'Edit' ) + '</button>' +
			'<button type="button" class="button-link formskit-remove-field" title="Remove">&times;</button>' +
			'</div>' +
			'<div class="formskit-field-card-body" hidden>' +
			'<div class="formskit-row"><label>' + esc( 'Label' ) + '</label><input type="text" class="widefat" data-prop="label" value="' + esc( field.label ) + '" /></div>' +
			'<div class="formskit-row"><label>' + esc( 'Field key' ) + '</label><input type="text" class="widefat" data-prop="name" value="' + esc( field.name ) + '" /></div>' +
			( field.type !== 'html' && field.type !== 'hidden'
				? '<div class="formskit-row"><label>' + esc( 'Placeholder' ) + '</label><input type="text" class="widefat" data-prop="placeholder" value="' + esc( field.placeholder || '' ) + '" /></div>'
				: '' ) +
			'<div class="formskit-row"><label>' + esc( 'Description' ) + '</label><input type="text" class="widefat" data-prop="description" value="' + esc( field.description || '' ) + '" /></div>' +
			optionsHtml +
			htmlContent +
			'<div class="formskit-row formskit-inline">' +
			'<label><input type="checkbox" data-prop="required"' + ( field.required ? ' checked' : '' ) + ' /> ' + esc( 'Required' ) + '</label>' +
			'<label>' + esc( 'Width' ) + ' <select data-prop="width">' +
			[ 'full', 'half', 'third' ]
				.map( function ( w ) {
					return '<option value="' + w + '"' + ( field.width === w ? ' selected' : '' ) + '>' + w + '</option>';
				} )
				.join( '' ) +
			'</select></label>' +
			'</div>' +
			'</div>';

		// Interactions.
		card.querySelector( '.formskit-remove-field' ).addEventListener( 'click', function () {
			removeField( index );
		} );
		card.querySelector( '.formskit-toggle-edit' ).addEventListener( 'click', function () {
			var body = card.querySelector( '.formskit-field-card-body' );
			body.hidden = ! body.hidden;
		} );

		card.querySelectorAll( '[data-prop]' ).forEach( function ( input ) {
			input.addEventListener( 'input', function () {
				updateFieldProp( index, input );
			} );
			input.addEventListener( 'change', function () {
				updateFieldProp( index, input );
			} );
		} );

		// Extension point: add-ons (Pro) can append UI to a field card body.
		document.dispatchEvent(
			new CustomEvent( 'formskit:render-field-card', {
				detail: {
					field: field,
					index: index,
					card: card,
					body: card.querySelector( '.formskit-field-card-body' ),
					fields: state.fields,
					sync: sync,
				},
			} )
		);

		return card;
	}

	function updateFieldProp( index, input ) {
		var prop = input.getAttribute( 'data-prop' );
		var field = state.fields[ index ];
		if ( ! field ) {
			return;
		}

		if ( prop === 'required' ) {
			field.required = input.checked;
		} else if ( prop === 'options' ) {
			field.options = input.value
				.split( '\n' )
				.map( function ( line ) {
					return line.trim();
				} )
				.filter( Boolean )
				.map( function ( label ) {
					return { label: label, value: label };
				} );
		} else if ( prop === 'name' ) {
			field.name = slugify( input.value ) || field.name;
		} else {
			field[ prop ] = input.value;
		}

		if ( prop === 'label' ) {
			var title = input.closest( '.formskit-field-card' ).querySelector( '.formskit-field-title' );
			if ( title ) {
				title.textContent = input.value || i18n.untitled;
			}
		}

		sync();
	}

	function renderFields() {
		listEl.innerHTML = '';
		if ( ! state.fields.length ) {
			hintEl.style.display = '';
		} else {
			hintEl.style.display = 'none';
		}
		state.fields.forEach( function ( field, index ) {
			listEl.appendChild( fieldCard( field, index ) );
		} );

		// Sortable reorder.
		if ( $.fn.sortable ) {
			$( listEl ).sortable( {
				handle: '.formskit-field-card-head',
				axis: 'y',
				update: function () {
					var newOrder = [];
					$( listEl )
						.children( '.formskit-field-card' )
						.each( function () {
							newOrder.push( state.fields[ parseInt( this.getAttribute( 'data-index' ), 10 ) ] );
						} );
					state.fields = newOrder;
					renderFields();
					sync();
				},
			} );
		}
	}

	/* ---------- settings panel ---------- */

	function bindSetting( selector, path, isCheckbox ) {
		var el = panels.settings.querySelector( selector ) || panels.notifications.querySelector( selector );
		if ( ! el ) {
			return;
		}
		el.addEventListener( isCheckbox ? 'change' : 'input', function () {
			setPath( state.settings, path, isCheckbox ? el.checked : el.value );
			sync();
		} );
	}

	function setPath( obj, path, value ) {
		var parts = path.split( '.' );
		var cur = obj;
		for ( var i = 0; i < parts.length - 1; i++ ) {
			cur[ parts[ i ] ] = cur[ parts[ i ] ] || {};
			cur = cur[ parts[ i ] ];
		}
		cur[ parts[ parts.length - 1 ] ] = value;
	}

	function getPath( obj, path, fallback ) {
		return path.split( '.' ).reduce( function ( o, k ) {
			return o && o[ k ] != null ? o[ k ] : undefined;
		}, obj ) ?? fallback;
	}

	function renderSettings() {
		var s = state.settings;
		panels.settings.innerHTML =
			'<div class="formskit-row"><label>' + esc( 'Submit button label' ) + '</label>' +
			'<input type="text" class="regular-text" id="fk-submit-label" value="' + esc( getPath( s, 'submit_label', 'Submit' ) ) + '" /></div>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-ajax"' + ( getPath( s, 'ajax', true ) ? ' checked' : '' ) + ' /> ' + esc( 'Submit without page reload (AJAX)' ) + '</label></div>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-store"' + ( getPath( s, 'store_entries', true ) ? ' checked' : '' ) + ' /> ' + esc( 'Store submissions in Entries' ) + '</label></div>' +
			'<hr />' +
			'<h3>' + esc( 'Confirmation' ) + '</h3>' +
			'<div class="formskit-row"><label>' + esc( 'After submit' ) + '</label>' +
			'<select id="fk-conf-type">' +
			'<option value="message"' + ( getPath( s, 'confirmation.type', 'message' ) === 'message' ? ' selected' : '' ) + '>' + esc( 'Show a message' ) + '</option>' +
			'<option value="redirect"' + ( getPath( s, 'confirmation.type' ) === 'redirect' ? ' selected' : '' ) + '>' + esc( 'Redirect to a URL' ) + '</option>' +
			'</select></div>' +
			'<div class="formskit-row"><label>' + esc( 'Confirmation message' ) + '</label>' +
			'<textarea class="widefat" id="fk-conf-msg" rows="3">' + esc( getPath( s, 'confirmation.message', 'Thanks! Your submission has been received.' ) ) + '</textarea></div>' +
			'<div class="formskit-row"><label>' + esc( 'Redirect URL' ) + '</label>' +
			'<input type="url" class="widefat" id="fk-conf-url" value="' + esc( getPath( s, 'confirmation.redirect_url', '' ) ) + '" /></div>' +
			'<hr />' +
			'<h3>' + esc( 'Spam Protection' ) + '</h3>' +
			'<div class="formskit-row"><label><input type="checkbox" id="fk-hp"' + ( getPath( s, 'spam.honeypot', true ) ? ' checked' : '' ) + ' /> ' + esc( 'Enable honeypot' ) + '</label></div>' +
			'<div class="formskit-row"><label>' + esc( 'Minimum fill time (seconds)' ) + '</label>' +
			'<input type="number" min="0" id="fk-mintime" value="' + esc( getPath( s, 'spam.min_time', 3 ) ) + '" /></div>' +
			'<div id="formskit-pro-settings"></div>';

		bindSetting( '#fk-submit-label', 'submit_label' );
		bindSetting( '#fk-ajax', 'ajax', true );
		bindSetting( '#fk-store', 'store_entries', true );
		bindSetting( '#fk-conf-type', 'confirmation.type' );
		bindSetting( '#fk-conf-msg', 'confirmation.message' );
		bindSetting( '#fk-conf-url', 'confirmation.redirect_url' );
		bindSetting( '#fk-hp', 'spam.honeypot', true );
		bindSetting( '#fk-mintime', 'spam.min_time' );

		// Allow Pro to inject settings UI.
		document.dispatchEvent(
			new CustomEvent( 'formskit:render-settings', {
				detail: { state: state, container: panels.settings.querySelector( '#formskit-pro-settings' ), sync: sync },
			} )
		);
	}

	function renderNotifications() {
		var s = state.settings;
		panels.notifications.innerHTML =
			'<div class="formskit-row"><label><input type="checkbox" id="fk-note-on"' + ( getPath( s, 'notification.enabled', true ) ? ' checked' : '' ) + ' /> ' + esc( 'Send email notification on submit' ) + '</label></div>' +
			'<div class="formskit-row"><label>' + esc( 'Send to' ) + '</label>' +
			'<input type="text" class="widefat" id="fk-note-to" value="' + esc( getPath( s, 'notification.to', '{admin_email}' ) ) + '" />' +
			'<p class="description">' + esc( 'Comma-separated. Smart tags allowed, e.g. {admin_email} or {field:email}.' ) + '</p></div>' +
			'<div class="formskit-row"><label>' + esc( 'Subject' ) + '</label>' +
			'<input type="text" class="widefat" id="fk-note-subject" value="' + esc( getPath( s, 'notification.subject', '' ) ) + '" /></div>' +
			'<div class="formskit-row"><label>' + esc( 'Message' ) + '</label>' +
			'<textarea class="widefat" id="fk-note-msg" rows="4">' + esc( getPath( s, 'notification.message', '{all_fields}' ) ) + '</textarea>' +
			'<p class="description">' + esc( 'Use {all_fields} for a full summary, or {field:key} for a single field.' ) + '</p></div>' +
			'<div class="formskit-row"><label>' + esc( 'Reply-To' ) + '</label>' +
			'<input type="text" class="widefat" id="fk-note-reply" value="' + esc( getPath( s, 'notification.reply_to', '' ) ) + '" /></div>';

		bindSetting( '#fk-note-on', 'notification.enabled', true );
		bindSetting( '#fk-note-to', 'notification.to' );
		bindSetting( '#fk-note-subject', 'notification.subject' );
		bindSetting( '#fk-note-msg', 'notification.message' );
		bindSetting( '#fk-note-reply', 'notification.reply_to' );
	}

	/* ---------- tabs ---------- */

	function showTab( tab ) {
		Object.keys( panels ).forEach( function ( key ) {
			panels[ key ].hidden = key !== tab;
		} );
		app.querySelectorAll( '[data-formskit-tab]' ).forEach( function ( b ) {
			b.classList.toggle( 'button-primary', b.getAttribute( 'data-formskit-tab' ) === tab );
		} );
	}

	app.querySelectorAll( '[data-formskit-tab]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			showTab( btn.getAttribute( 'data-formskit-tab' ) );
		} );
	} );

	/* ---------- init ---------- */

	renderPalette();
	renderFields();
	renderSettings();
	renderNotifications();
	showTab( 'fields' );
	sync();

	// Guarantee serialization right before the post form submits.
	var postForm = document.getElementById( 'post' );
	if ( postForm ) {
		postForm.addEventListener( 'submit', sync );
	}

	window.FormsKitBuilder = { state: state, sync: sync };
} )( jQuery );

/* global FormsKit */
( function () {
	'use strict';

	function serialize( form ) {
		return new FormData( form );
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.formskit-has-error' ).forEach( function ( el ) {
			el.classList.remove( 'formskit-has-error' );
		} );
		form.querySelectorAll( '.formskit-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
	}

	function showFieldErrors( form, errors ) {
		Object.keys( errors || {} ).forEach( function ( name ) {
			var field = form.querySelector( '[data-field="' + name + '"]' );
			if ( ! field ) {
				return;
			}
			field.classList.add( 'formskit-has-error' );
			var slot = field.querySelector( '.formskit-field-error' );
			if ( slot ) {
				slot.textContent = errors[ name ];
			}
		} );
	}

	function setMessage( form, text, type ) {
		var box = form.querySelector( '.formskit-message' );
		if ( ! box ) {
			return;
		}
		box.className = 'formskit-message formskit-message-' + type;
		box.innerHTML = text;
	}

	function submit( form ) {
		var formId = form.getAttribute( 'data-form-id' );
		var btn = form.querySelector( '.formskit-submit' );
		var wrap = form.closest( '.formskit-wrap' );

		clearErrors( form );
		setMessage( form, '', 'none' );
		form.classList.add( 'formskit-submitting' );
		if ( btn ) {
			btn.disabled = true;
		}

		var data = serialize( form );
		data.append( 'action', 'formskit_submit' );

		fetch( FormsKit.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				form.classList.remove( 'formskit-submitting' );
				if ( btn ) {
					btn.disabled = false;
				}

				if ( res && res.success ) {
					var conf = res.data.confirmation || {};
					if ( conf.type === 'redirect' && conf.redirect_url ) {
						window.location.href = conf.redirect_url;
						return;
					}
					if ( wrap ) {
						wrap.innerHTML =
							'<div class="formskit-success" role="alert">' +
							( res.data.message || '' ) +
							'</div>';
						wrap.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					}
				} else {
					var payload = ( res && res.data ) || {};
					if ( payload.fields ) {
						showFieldErrors( form, payload.fields );
					}
					setMessage(
						form,
						payload.message || FormsKit.i18n.error,
						'error'
					);
				}
			} )
			.catch( function () {
				form.classList.remove( 'formskit-submitting' );
				if ( btn ) {
					btn.disabled = false;
				}
				setMessage( form, FormsKit.i18n.error, 'error' );
			} );
	}

	function basicValidate( form ) {
		var ok = true;
		clearErrors( form );
		form.querySelectorAll( '.formskit-required' ).forEach( function ( field ) {
			// Skip fields hidden by conditional logic or an inactive step.
			if ( field.offsetParent === null || field.style.display === 'none' ) {
				return;
			}
			var inputs = field.querySelectorAll( 'input, textarea, select' );
			var filled = false;
			inputs.forEach( function ( input ) {
				if ( input.type === 'checkbox' || input.type === 'radio' ) {
					if ( input.checked ) {
						filled = true;
					}
				} else if ( input.value && input.value.trim() !== '' ) {
					filled = true;
				}
			} );
			if ( ! filled ) {
				ok = false;
				field.classList.add( 'formskit-has-error' );
				var slot = field.querySelector( '.formskit-field-error' );
				if ( slot ) {
					slot.textContent = FormsKit.i18n.required;
				}
			}
		} );
		return ok;
	}

	function init() {
		document.querySelectorAll( '.formskit-form.formskit-ajax' ).forEach( function ( form ) {
			if ( form.dataset.formskitBound ) {
				return;
			}
			form.dataset.formskitBound = '1';
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();

				/**
				 * Pro can hook validation via this custom event; calling
				 * preventDefault() on it cancels submission.
				 */
				var evt = new CustomEvent( 'formskit:validate', { cancelable: true, detail: { form: form } } );
				var proceed = form.dispatchEvent( evt );

				if ( ! proceed ) {
					return;
				}
				if ( ! basicValidate( form ) ) {
					return;
				}
				submit( form );
			} );
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}

	window.FormsKitFront = { init: init, submit: submit };
} )();

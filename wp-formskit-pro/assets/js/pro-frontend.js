/* global grecaptcha, FormsKitRecaptcha */
( function () {
	'use strict';

	/* ------------------------------------------------------------------ *
	 * Conditional logic
	 * ------------------------------------------------------------------ */

	function fieldValue( form, name ) {
		var wrap = form.querySelector( '[data-field="' + name + '"]' );
		if ( ! wrap ) {
			return '';
		}
		var checked = wrap.querySelectorAll( 'input[type=checkbox]:checked, input[type=radio]:checked' );
		if ( checked.length ) {
			return Array.prototype.map
				.call( checked, function ( c ) {
					return c.value;
				} )
				.join( ',' );
		}
		var input = wrap.querySelector( 'input, textarea, select' );
		return input ? input.value : '';
	}

	function testRule( form, rule ) {
		var val = fieldValue( form, rule.field );
		switch ( rule.operator ) {
			case 'is':
				return String( val ) === String( rule.value );
			case 'is_not':
				return String( val ) !== String( rule.value );
			case 'contains':
				return val.indexOf( rule.value ) !== -1;
			case 'gt':
				return parseFloat( val ) > parseFloat( rule.value );
			case 'lt':
				return parseFloat( val ) < parseFloat( rule.value );
			case 'empty':
				return val === '' || val == null;
			case 'not_empty':
				return val !== '' && val != null;
			default:
				return false;
		}
	}

	function applyConditional( form ) {
		form.querySelectorAll( '[data-formskit-conditional]' ).forEach( function ( wrap ) {
			var cfg;
			try {
				cfg = JSON.parse( wrap.getAttribute( 'data-formskit-conditional' ) );
			} catch ( e ) {
				return;
			}
			if ( ! cfg || ! cfg.rules || ! cfg.rules.length ) {
				return;
			}

			var results = cfg.rules.map( function ( r ) {
				return testRule( form, r );
			} );
			var matched =
				cfg.logic === 'any'
					? results.some( Boolean )
					: results.every( Boolean );

			var show = cfg.action === 'hide' ? ! matched : matched;
			wrap.style.display = show ? '' : 'none';

			// Never submit values from a hidden field.
			wrap.querySelectorAll( 'input, textarea, select' ).forEach( function ( input ) {
				input.disabled = ! show;
			} );
		} );
	}

	function initConditional( form ) {
		if ( ! form.querySelector( '[data-formskit-conditional]' ) ) {
			return;
		}
		form.addEventListener( 'change', function () {
			applyConditional( form );
		} );
		form.addEventListener( 'input', function () {
			applyConditional( form );
		} );
		applyConditional( form );
	}

	/* ------------------------------------------------------------------ *
	 * Multi-step
	 * ------------------------------------------------------------------ */

	function initMultistep( form ) {
		var flag = form.querySelector( '.formskit-multistep-flag' );
		if ( ! flag ) {
			return;
		}

		var fieldsWrap = form.querySelector( '.formskit-fields' );
		var children = Array.prototype.slice.call( fieldsWrap.children );

		// Split into steps at page-break fields.
		var steps = [ [] ];
		children.forEach( function ( child ) {
			if ( child.querySelector && child.querySelector( '.formskit-pagebreak' ) ) {
				steps.push( [] );
			} else {
				steps[ steps.length - 1 ].push( child );
			}
		} );

		if ( steps.length < 2 ) {
			return; // No page breaks → nothing to paginate.
		}

		// Wrap each step.
		fieldsWrap.classList.add( 'formskit-steps' );
		fieldsWrap.innerHTML = '';
		var stepEls = steps.map( function ( group, i ) {
			var stepEl = document.createElement( 'div' );
			stepEl.className = 'formskit-step' + ( i === 0 ? ' is-active' : '' );
			group.forEach( function ( el ) {
				stepEl.appendChild( el );
			} );
			fieldsWrap.appendChild( stepEl );
			return stepEl;
		} );

		var current = 0;
		var total = stepEls.length;

		// Progress bar.
		var progressWrap = null;
		if ( flag.getAttribute( 'data-progress' ) === '1' ) {
			progressWrap = document.createElement( 'div' );
			progressWrap.className = 'formskit-progress';
			for ( var p = 0; p < total; p++ ) {
				progressWrap.innerHTML +=
					'<div class="formskit-progress-bar"><span class="formskit-progress-fill"></span></div>';
			}
			fieldsWrap.parentNode.insertBefore( progressWrap, fieldsWrap );
		}

		// Navigation.
		var nav = document.createElement( 'div' );
		nav.className = 'formskit-step-nav';
		var prevBtn = document.createElement( 'button' );
		prevBtn.type = 'button';
		prevBtn.className = 'formskit-step-prev button';
		prevBtn.textContent = 'Previous';
		var nextBtn = document.createElement( 'button' );
		nextBtn.type = 'button';
		nextBtn.className = 'formskit-step-next button';
		nextBtn.textContent = 'Next';
		nav.appendChild( prevBtn );
		nav.appendChild( nextBtn );
		fieldsWrap.parentNode.insertBefore( nav, fieldsWrap.nextSibling );

		var submitWrap = form.querySelector( '.formskit-actions' );

		function stepValid( index ) {
			var ok = true;
			stepEls[ index ]
				.querySelectorAll( '.formskit-required' )
				.forEach( function ( field ) {
					if ( field.style.display === 'none' ) {
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
					}
				} );
			return ok;
		}

		function render() {
			stepEls.forEach( function ( el, i ) {
				el.classList.toggle( 'is-active', i === current );
			} );
			prevBtn.style.visibility = current === 0 ? 'hidden' : 'visible';
			var onLast = current === total - 1;
			nextBtn.style.display = onLast ? 'none' : '';
			if ( submitWrap ) {
				submitWrap.style.display = onLast ? '' : 'none';
			}
			if ( progressWrap ) {
				progressWrap
					.querySelectorAll( '.formskit-progress-fill' )
					.forEach( function ( fill, i ) {
						fill.style.width = i <= current ? '100%' : '0';
					} );
			}
		}

		nextBtn.addEventListener( 'click', function () {
			if ( ! stepValid( current ) ) {
				return;
			}
			if ( current < total - 1 ) {
				current++;
				render();
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) {
				current--;
				render();
			}
		} );

		render();
	}

	/* ------------------------------------------------------------------ *
	 * reCAPTCHA v3
	 * ------------------------------------------------------------------ */

	function initRecaptcha( form ) {
		if ( typeof FormsKitRecaptcha === 'undefined' || ! FormsKitRecaptcha.siteKey ) {
			return;
		}
		var tokenField = form.querySelector( '.formskit-recaptcha-token' );
		if ( ! tokenField ) {
			return;
		}

		form.addEventListener(
			'formskit:validate',
			function ( e ) {
				if ( tokenField.value ) {
					return; // Token ready — let submission proceed.
				}
				e.preventDefault(); // Hold submission while we fetch a token.

				if ( typeof grecaptcha === 'undefined' ) {
					return;
				}
				grecaptcha.ready( function () {
					grecaptcha
						.execute( FormsKitRecaptcha.siteKey, { action: 'submit' } )
						.then( function ( token ) {
							tokenField.value = token;
							if ( form.requestSubmit ) {
								form.requestSubmit();
							} else {
								form.dispatchEvent(
									new Event( 'submit', { cancelable: true } )
								);
							}
						} );
				} );
			},
			false
		);
	}

	/* ------------------------------------------------------------------ */

	function init() {
		document.querySelectorAll( '.formskit-form' ).forEach( function ( form ) {
			if ( form.dataset.formskitProBound ) {
				return;
			}
			form.dataset.formskitProBound = '1';
			initConditional( form );
			initMultistep( form );
			initRecaptcha( form );
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();

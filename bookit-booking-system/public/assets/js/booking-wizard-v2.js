/**
 * Booking wizard V2 — step navigation and interactions (vanilla JS).
 *
 * @package Bookit_Booking_System
 */
( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		if ( ! document.querySelector( '.bookit-v2-wizard-container' ) ) {
			return;
		}

		var wizard = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var currentStep = parseInt( wizard.currentStep, 10 ) || 1;

		initStep( currentStep );
	} );

	function initStep( step ) {
		if ( step === 1 ) {
			initStep1();
		}
		if ( step === 2 ) {
			initStep2();
		}
		if ( step === 3 ) {
			initStep3();
		}
		if ( step === 4 ) {
			initStep4();
		}
		if ( step === 5 ) {
			initStep5();
		}
		initNavigation( step );
	}

	function postToSession( data ) {
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		return fetch( w.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': w.nonce
			},
			body: JSON.stringify( data )
		} ).then( function( r ) {
			return r.json();
		} );
	}

	function advanceStep( step ) {
		postToSession( { current_step: step + 1 } ).then( function() {
			window.location.reload();
		} );
	}

	function initNavigation( step ) {
		var continueBtn = document.getElementById( 'bookit-v2-continue' );
		if ( continueBtn ) {
			continueBtn.addEventListener( 'click', function() {
				advanceStep( step );
			} );
		}

		document.querySelectorAll( '.bookit-v2-confirm-banner-change' ).forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				var n = parseInt( btn.getAttribute( 'data-goto-step' ), 10 );
				if ( ! n || n < 1 ) {
					return;
				}
				postToSession( { current_step: n } ).then( function() {
					window.location.reload();
				} );
			} );
		} );
	}

	function initStep1() {
		document.querySelectorAll( '.bookit-v2-service-card' ).forEach( function( card ) {
			card.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-service-card' ).forEach( function( c ) {
					c.classList.remove( 'bookit-v2-service-card--selected' );
				} );
				card.classList.add( 'bookit-v2-service-card--selected' );
				postToSession( {
					current_step: 2,
					service_id: parseInt( card.dataset.serviceId, 10 ),
					service_name: card.dataset.serviceName || '',
					service_duration: parseInt( card.dataset.serviceDuration, 10 ) || 0
				} ).then( function() {
					window.location.reload();
				} );
			} );
		} );
	}

	function initStep2() {
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var rows = document.querySelectorAll( '.bookit-v2-staff-row, .bookit-v2-staff-card' );
		rows.forEach( function( el ) {
			if ( el.classList.contains( 'bookit-v2-staff-row--unavailable' ) || el.classList.contains( 'bookit-v2-staff-card--unavailable' ) ) {
				return;
			}
			el.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-staff-row--selected, .bookit-v2-staff-card--selected' ).forEach( function( s ) {
					s.classList.remove( 'bookit-v2-staff-row--selected' );
					s.classList.remove( 'bookit-v2-staff-card--selected' );
				} );
				if ( el.classList.contains( 'bookit-v2-staff-row' ) ) {
					el.classList.add( 'bookit-v2-staff-row--selected' );
				}
				if ( el.classList.contains( 'bookit-v2-staff-card' ) ) {
					el.classList.add( 'bookit-v2-staff-card--selected' );
				}
				var sid = el.dataset.staffId;
				var staffId = sid === undefined || sid === '' ? 0 : parseInt( sid, 10 );
				fetch( w.restUrl + 'bookit/v1/staff/select', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': w.nonce
					},
					body: JSON.stringify( { staff_id: staffId } )
				} ).then( function( r ) {
					return r.json();
				} ).then( function( res ) {
					if ( res && res.success ) {
						window.location.reload();
					}
				} );
			} );
		} );
	}

	function formatSlotButtonLabel( slot ) {
		var p = String( slot ).split( ':' );
		var h = p[0 ] !== undefined ? p[0 ] : '00';
		var m = p[1 ] !== undefined ? p[1 ] : '00';
		if ( h.length < 2 ) {
			h = ( '0' + h ).slice( -2 );
		}
		if ( m.length < 2 ) {
			m = ( '0' + m ).slice( -2 );
		}
		return h + ':' + m;
	}

	function renderTimeSections( slots ) {
		var container = document.getElementById( 'bookit-v2-time-sections' );
		if ( ! container || ! slots ) {
			return;
		}
		var labels = {
			morning: 'Morning',
			afternoon: 'Afternoon',
			evening: 'Evening'
		};
		var order = [ 'morning', 'afternoon', 'evening' ];
		var html = '';
		order.forEach( function( key ) {
			var list = slots[ key ] || [];
			if ( ! list.length ) {
				return;
			}
			html += '<div class="bookit-v2-time-section">';
			html += '<p class="bookit-v2-time-section-label">' + labels[ key ] + '</p>';
			html += '<div class="bookit-v2-slots-grid">';
			list.forEach( function( slot ) {
				var raw = String( slot );
				var esc = raw.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' );
				html += '<button type="button" class="bookit-v2-slot bookit-v2-slot--available" data-time="' + esc + '">' + formatSlotButtonLabel( raw ) + '</button>';
			} );
			html += '</div></div>';
		} );
		container.innerHTML = html;
		container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function initStep3() {
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var selInitial = document.querySelector( '.bookit-v2-day--selected.bookit-v2-day--available' );
		var currentSelectedDate = selInitial && selInitial.dataset.date ? selInitial.dataset.date : '';

		document.querySelectorAll( '.bookit-v2-day--available' ).forEach( function( dayBtn ) {
			dayBtn.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-day--selected' ).forEach( function( d ) {
					d.classList.remove( 'bookit-v2-day--selected' );
				} );
				dayBtn.classList.add( 'bookit-v2-day--selected' );
				currentSelectedDate = dayBtn.dataset.date || '';
				postToSession( {
					current_step: 3,
					date: currentSelectedDate
				} ).then( function() {
					var url = w.restUrl + 'bookit/v1/wizard/timeslots?date=' + encodeURIComponent( currentSelectedDate );
					return fetch( url, {
						credentials: 'same-origin',
						headers: {
							'X-WP-Nonce': w.nonce
						}
					} ).then( function( r ) {
						return r.json();
					} );
				} ).then( function( data ) {
					if ( data && data.success && data.slots ) {
						renderTimeSections( data.slots );
					}
				} );
			} );
		} );

		var timeSections = document.getElementById( 'bookit-v2-time-sections' );
		if ( timeSections ) {
			timeSections.addEventListener( 'click', function( e ) {
				var slot = e.target.closest( '.bookit-v2-slot--available' );
				if ( ! slot ) {
					return;
				}
				var dateVal = currentSelectedDate;
				if ( ! dateVal ) {
					var sel = document.querySelector( '.bookit-v2-day--selected' );
					if ( sel ) {
						dateVal = sel.dataset.date || '';
					}
				}
				document.querySelectorAll( '.bookit-v2-slot--selected' ).forEach( function( s ) {
					s.classList.remove( 'bookit-v2-slot--selected' );
				} );
				slot.classList.add( 'bookit-v2-slot--selected' );
				postToSession( {
					current_step: 3,
					date: dateVal,
					time: slot.dataset.time
				} ).then( function() {
					var cont = document.getElementById( 'bookit-v2-continue' );
					if ( cont ) {
						cont.removeAttribute( 'disabled' );
					}
				} );
			} );
		}
	}

	function initStep4() {
		var toggle = document.getElementById( 'bookit-v2-special-requests-toggle' );
		var textarea = document.getElementById( 'special-requests' );
		if ( toggle && textarea ) {
			toggle.addEventListener( 'click', function() {
				toggle.style.display = 'none';
				textarea.style.display = '';
				textarea.focus();
			} );
		}
	}

	function updateCtaLabel( value ) {
		var btn = document.getElementById( 'bookit-v2-cta-btn' );
		if ( ! btn ) {
			return;
		}
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var deposit = parseFloat( w.depositAmount ) || 0;
		var total = parseFloat( w.totalAmount ) || 0;
		var amount = deposit > 0 ? deposit : total;
		var formatted = amount > 0 ? '\u00a3' + amount.toFixed( 2 ) : '';

		if ( value === 'card' ) {
			btn.textContent = formatted ? 'Pay ' + formatted + ' now' : 'Pay now';
		} else if ( value === 'paypal' ) {
			btn.textContent = 'Continue to PayPal';
		} else if ( value === 'person' ) {
			btn.textContent = 'Confirm booking';
		} else if ( value === 'use_package' || ( typeof value === 'string' && value.indexOf( 'use_package_' ) === 0 ) ) {
			btn.textContent = 'Use my package';
		} else if ( typeof value === 'string' && value.indexOf( 'buy_' ) === 0 ) {
			btn.textContent = 'Buy package & confirm';
		} else {
			btn.textContent = 'Continue';
		}
	}

	function getPaymentChoiceValue() {
		var checked = document.querySelector( 'input[name="bookit_v2_payment_choice"]:checked' );
		return checked ? checked.value : 'card';
	}

	function initStep5() {
		updateCtaLabel( 'card' );

		document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( row ) {
			row.addEventListener( 'click', function() {
				document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( r ) {
					r.classList.remove( 'bookit-v2-payment-row--selected' );
					r.classList.remove( 'bookit-v2-payment-row--disabled' );
				} );
				row.classList.add( 'bookit-v2-payment-row--selected' );
				var radio = row.querySelector( 'input[type="radio"]' );
				if ( radio ) {
					radio.checked = true;
				}
				document.querySelectorAll( '.bookit-v2-package-row input[type="radio"]' ).forEach( function( pr ) {
					pr.checked = false;
				} );
				document.querySelectorAll( '.bookit-v2-package-row' ).forEach( function( pr ) {
					pr.classList.remove( 'bookit-v2-package-row--selected' );
				} );
				updateCtaLabel( row.dataset.value || ( radio ? radio.value : 'card' ) );
			} );
		} );

		document.querySelectorAll( '.bookit-v2-package-row' ).forEach( function( row ) {
			row.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-package-row' ).forEach( function( r ) {
					r.classList.remove( 'bookit-v2-package-row--selected' );
				} );
				row.classList.add( 'bookit-v2-package-row--selected' );
				var radio = row.querySelector( 'input[type="radio"]' );
				if ( radio ) {
					radio.checked = true;
				}
				document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( pr ) {
					pr.classList.add( 'bookit-v2-payment-row--disabled' );
					pr.classList.remove( 'bookit-v2-payment-row--selected' );
				} );
				document.querySelectorAll( '#bookit-v2-zone-c input[type="radio"]' ).forEach( function( pr ) {
					pr.checked = false;
				} );
				var val = row.dataset.value || ( radio ? radio.value : '' );
				updateCtaLabel( val );
			} );
		} );

		var cta = document.getElementById( 'bookit-v2-cta-btn' );
		if ( cta ) {
			cta.addEventListener( 'click', function() {
				var choice = getPaymentChoiceValue();
				postToSession( {
					current_step: 5,
					payment_method: choice
				} ).then( function() {
					window.location.reload();
				} );
			} );
		}
	}
} )();

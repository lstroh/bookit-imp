/* Bookit Wizard V2 */
( function() {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function() {
		if ( ! document.querySelector( '.bookit-v2-wizard-container' ) ) {
			return;
		}

		var toggle = document.getElementById( 'bookit-v2-special-requests-toggle' );
		var textarea = document.getElementById( 'special-requests' );
		if ( toggle && textarea ) {
			toggle.addEventListener( 'click', function() {
				textarea.style.display = '';
				toggle.style.display = 'none';
				textarea.focus();
			} );
		}
	} );
} )();

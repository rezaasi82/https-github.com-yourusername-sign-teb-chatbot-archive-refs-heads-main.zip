/**
 * SignTeb Blocks — Before/After Slider: Interaction
 * Drag + Touch — Vanilla JS
 */
( function () {
	'use strict';

	document.querySelectorAll( '.stmb-ba' ).forEach( function ( el ) {
		const before = el.querySelector( '.stmb-ba__before' );
		const handle = el.querySelector( '.stmb-ba__handle' );
		const isRtl  = document.dir === 'rtl' || document.documentElement.dir === 'rtl';

		if ( ! before || ! handle ) return;

		let dragging = false;

		function setPosition( clientX ) {
			const rect = el.getBoundingClientRect();
			let pct;

			if ( isRtl ) {
				pct = ( ( rect.right - clientX ) / rect.width ) * 100;
			} else {
				pct = ( ( clientX - rect.left ) / rect.width ) * 100;
			}

			pct = Math.max( 2, Math.min( 98, pct ) );

			before.style.width     = pct + '%';
			handle.style.right     = isRtl ? pct + '%' : 'auto';
			handle.style.left      = isRtl ? 'auto'    : pct + '%';
		}

		// Mouse
		el.addEventListener( 'mousedown',  function ( e ) { dragging = true; setPosition( e.clientX ); e.preventDefault(); } );
		window.addEventListener( 'mousemove', function ( e ) { if ( dragging ) setPosition( e.clientX ); } );
		window.addEventListener( 'mouseup',   function ()   { dragging = false; } );

		// Touch
		el.addEventListener( 'touchstart', function ( e ) { dragging = true; setPosition( e.touches[0].clientX ); }, { passive: true } );
		el.addEventListener( 'touchmove',  function ( e ) { if ( dragging ) setPosition( e.touches[0].clientX ); }, { passive: true } );
		el.addEventListener( 'touchend',   function ()   { dragging = false; } );
	} );
} )();

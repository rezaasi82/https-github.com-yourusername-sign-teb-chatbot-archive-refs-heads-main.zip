/**
 * SignTeb MedCore — Before/After Slider
 * Standalone theme version (blocks version also has its own)
 */

( function () {
	'use strict';

	// نکته: قبلاً listenerهای window (mousemove/mouseup) داخل forEach هر
	// اسلایدر ثبت می‌شدند — یعنی اگر چند «.stmc-before-after» در یک صفحه
	// باشند، چند listener تکراری روی window جمع می‌شد. اکنون فقط یک
	// listener مشترک روی window ثبت می‌شود و اسلایدر «فعال» را دنبال می‌کند.
	let activeSlider = null;

	function setPos( slider, clientX ) {
		const rect = slider.el.getBoundingClientRect();
		let pct = slider.isRtl
			? ( ( rect.right - clientX ) / rect.width ) * 100
			: ( ( clientX - rect.left  ) / rect.width ) * 100;

		pct = Math.max( 2, Math.min( 98, pct ) );
		slider.before.style.width = pct + '%';
		slider.handle.style[ slider.isRtl ? 'right' : 'left' ] = pct + '%';
	}

	document.querySelectorAll( '.stmc-before-after' ).forEach( function ( el ) {
		const before = el.querySelector( '.stmc-ba-before' );
		const handle = el.querySelector( '.stmc-ba-handle' );
		const isRtl  = document.documentElement.dir === 'rtl';

		if ( ! before || ! handle ) return;

		const slider = { el, before, handle, isRtl };

		el.addEventListener( 'mousedown', function ( e ) {
			activeSlider = slider;
			setPos( slider, e.clientX );
			e.preventDefault();
		} );
		el.addEventListener( 'touchstart', function ( e ) {
			activeSlider = slider;
			setPos( slider, e.touches[0].clientX );
		}, { passive: true } );
		el.addEventListener( 'touchmove', function ( e ) {
			if ( activeSlider === slider ) setPos( slider, e.touches[0].clientX );
		}, { passive: true } );
		el.addEventListener( 'touchend', function () {
			if ( activeSlider === slider ) activeSlider = null;
		} );
	} );

	window.addEventListener( 'mousemove', function ( e ) {
		if ( activeSlider ) setPos( activeSlider, e.clientX );
	} );
	window.addEventListener( 'mouseup', function () {
		activeSlider = null;
	} );
} )();

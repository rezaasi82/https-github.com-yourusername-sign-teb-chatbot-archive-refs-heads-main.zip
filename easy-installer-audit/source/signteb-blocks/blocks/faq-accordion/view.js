/**
 * SignTeb Blocks — FAQ Accordion: Interaction
 */
( function () {
	'use strict';

	document.querySelectorAll( '.stmb-faq-list' ).forEach( function ( list ) {
		const allowMultiple = list.dataset.multiple === 'true';
		const items = list.querySelectorAll( '.stmb-faq-item' );

		items.forEach( function ( item ) {
			const btn   = item.querySelector( '.stmb-faq-btn' );
			const panel = item.querySelector( '.stmb-faq-panel' );
			if ( ! btn || ! panel ) return;

			btn.addEventListener( 'click', function () {
				const isOpen = item.classList.contains( 'is-open' );

				// Close all if single-open mode
				if ( ! allowMultiple ) {
					items.forEach( function ( other ) {
						if ( other !== item ) closeItem( other );
					} );
				}

				// Toggle current
				isOpen ? closeItem( item ) : openItem( item );
			} );
		} );

		function openItem( item ) {
			const btn   = item.querySelector( '.stmb-faq-btn' );
			const panel = item.querySelector( '.stmb-faq-panel' );
			if ( ! btn || ! panel ) return;

			item.classList.add( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'true' );
			panel.hidden = false;

			// Animate height
			panel.style.maxHeight = '0';
			panel.style.overflow  = 'hidden';
			panel.style.transition = 'max-height 0.35s cubic-bezier(0.25,1,0.5,1)';

			requestAnimationFrame( function () {
				panel.style.maxHeight = panel.scrollHeight + 'px';
			} );
		}

		function closeItem( item ) {
			const btn   = item.querySelector( '.stmb-faq-btn' );
			const panel = item.querySelector( '.stmb-faq-panel' );
			if ( ! btn || ! panel ) return;

			item.classList.remove( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'false' );

			panel.style.maxHeight = '0';
			panel.addEventListener( 'transitionend', function onEnd() {
				panel.hidden = true;
				panel.removeEventListener( 'transitionend', onEnd );
			}, { once: true } );
		}
	} );
} )();

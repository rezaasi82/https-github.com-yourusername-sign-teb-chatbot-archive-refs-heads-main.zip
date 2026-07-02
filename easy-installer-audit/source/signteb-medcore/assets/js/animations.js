/**
 * SignTeb MedCore — Animations
 * IntersectionObserver scroll reveals + hover micro-interactions
 * Respects prefers-reduced-motion
 */

( function () {
	'use strict';

	// Bail if reduced motion is preferred
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		// Just make everything visible
		document.querySelectorAll( '.will-animate' ).forEach( el => el.classList.add( 'is-visible' ) );
		return;
	}

	// ── Scroll reveal ─────────────────────────────────────────────────────────

	// نکته: `.stmc-doctor-card`, `.stmc-card`, `.stmc-glass-card` و `.will-animate`
	// عمداً از این لیست حذف شده‌اند — main.js همین المان‌ها را از قبل با یک
	// IntersectionObserver مجزا observe می‌کند؛ نگه داشتن آن‌ها اینجا باعث
	// می‌شد هر المان با دو observer مستقل هم‌زمان رصد شود (کار تکراری بی‌فایده).
	const ANIMATE_TARGETS = [
		'.stmb-doctor-card',
		'.stmb-service-card',
		'.stmb-review-card',
		'.stmb-faq-item',
		'.stmb-stat-card',
		'.stmb-next-card',
	].join( ',' );

	if ( 'IntersectionObserver' in window ) {
		const revealObserver = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) return;

					const el    = entry.target;
					const delay = parseFloat( el.dataset.delay || '0' );

					setTimeout( function () {
						el.classList.add( 'is-visible' );
						revealObserver.unobserve( el );
					}, delay * 1000 );
				} );
			},
			{ threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
		);

		// Add stagger delays to grids
		[
			'.stmb-doctors-grid',
			'.stmb-service-grid',
			'.stmb-testimonials__grid',
			'.stmb-stats__inner',
		].forEach( function ( gridSel ) {
			document.querySelectorAll( gridSel ).forEach( function ( grid ) {
				Array.from( grid.children ).forEach( function ( card, i ) {
					card.classList.add( 'will-animate' );
					card.dataset.delay = String( i * 0.08 );
					revealObserver.observe( card );
				} );
			} );
		} );

		// نکته: پویش سراسری «.will-animate» عمداً حذف شد — main.js همین کلاس را
		// روی المان‌های خودش اضافه می‌کند و با observer مجزای خودش رصد می‌کند؛
		// نگه داشتن این پویش اینجا باعث رصد دوباره همان المان‌ها می‌شد.
		document.querySelectorAll( ANIMATE_TARGETS ).forEach( function ( el ) {
			if ( ! el.classList.contains( 'will-animate' ) ) {
				el.classList.add( 'will-animate' );
				revealObserver.observe( el );
			}
		} );
	}

	// ── Hero parallax (subtle) ─────────────────────────────────────────────────

	const heroMedia = document.querySelector( '.stmb-doctor-hero__media' );
	if ( heroMedia ) {
		window.addEventListener( 'scroll', function () {
			const scrollY = window.scrollY;
			if ( scrollY < window.innerHeight ) {
				heroMedia.style.transform = `translateY(${scrollY * 0.08}px)`;
			}
		}, { passive: true } );
	}

	// ── Doctor card hover lift ─────────────────────────────────────────────────

	document.querySelectorAll( '.stmb-doctor-card, .stmb-service-card' ).forEach( function ( card ) {
		card.addEventListener( 'mouseenter', function () {
			card.style.willChange = 'transform';
		} );
		card.addEventListener( 'mouseleave', function () {
			card.style.willChange = 'auto';
		} );
	} );

	// ── Smooth reveal for hero content ────────────────────────────────────────

	const heroContent = document.querySelector( '.stmb-doctor-hero__content' );
	if ( heroContent ) {
		heroContent.style.opacity  = '0';
		heroContent.style.transform = 'translateY(20px)';
		requestAnimationFrame( function () {
			heroContent.style.transition = 'opacity 0.7s ease, transform 0.7s cubic-bezier(0.25,1,0.5,1)';
			heroContent.style.opacity    = '1';
			heroContent.style.transform  = 'translateY(0)';
		} );
	}

	// ── Animate badge on doctor hero ──────────────────────────────────────────

	const imgBadge = document.querySelector( '.stmb-doctor-hero__img-badge' );
	if ( imgBadge ) {
		setTimeout( function () {
			imgBadge.style.animation = 'none';
			imgBadge.style.transition = 'transform 0.4s cubic-bezier(0.25,1,0.5,1), opacity 0.4s ease';
			imgBadge.style.opacity   = '0';
			imgBadge.style.transform = 'translateY(10px)';
			setTimeout( function () {
				imgBadge.style.opacity   = '1';
				imgBadge.style.transform = 'translateY(0)';
			}, 600 );
		}, 0 );
	}

} )();

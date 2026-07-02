/**
 * SignTeb MedCore — Main JavaScript
 *
 * - IntersectionObserver animations
 * - Counter animation
 * - Lazy load fallback
 * - RTL detection
 * Vanilla JS — defer loaded
 *
 * @package SignTeb_MedCore
 */

( function () {
	'use strict';

	// ── Intersection Observer: Fade-in on scroll ───────────────────────────────

	const animateEls = document.querySelectorAll(
		'.stmc-animate, .stmc-doctor-card, .stmc-card, .stmc-glass-card, .stmc-service-card'
	);

	if ( animateEls.length && 'IntersectionObserver' in window ) {
		const observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-visible' );
						observer.unobserve( entry.target );
					}
				} );
			},
			{
				threshold:  0.12,
				rootMargin: '0px 0px -40px 0px',
			}
		);

		animateEls.forEach( function ( el, index ) {
			// Stagger delay
			el.style.transitionDelay = ( index % 4 ) * 0.08 + 's';
			el.classList.add( 'will-animate' );
			observer.observe( el );
		} );
	} else {
		// Fallback: show everything
		animateEls.forEach( function ( el ) {
			el.classList.add( 'is-visible' );
		} );
	}

	// ── Counter Animation ──────────────────────────────────────────────────────

	function animateCounter( el ) {
		const target   = parseInt( el.getAttribute( 'data-target' ) || el.textContent, 10 );
		const duration = parseInt( el.getAttribute( 'data-duration' ) || '2000', 10 );
		const start    = performance.now();
		const isRtl    = stmcData?.isRtl;

		function easeOut( t ) {
			return 1 - Math.pow( 1 - t, 3 );
		}

		function toFarsi( num ) {
			if ( ! isRtl ) return num.toLocaleString();
			const fa = [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ];
			return num.toLocaleString().replace( /[0-9]/g, d => fa[ d ] );
		}

		function update( now ) {
			const elapsed  = now - start;
			const progress = Math.min( elapsed / duration, 1 );
			const current  = Math.floor( easeOut( progress ) * target );

			el.textContent = toFarsi( current );

			if ( progress < 1 ) {
				requestAnimationFrame( update );
			} else {
				el.textContent = toFarsi( target );
			}
		}

		requestAnimationFrame( update );
	}

	// Observe counter elements
	const counters = document.querySelectorAll( '[data-counter]' );
	if ( counters.length && 'IntersectionObserver' in window ) {
		const counterObserver = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						animateCounter( entry.target );
						counterObserver.unobserve( entry.target );
					}
				} );
			},
			{ threshold: 0.5 }
		);

		counters.forEach( function ( el ) {
			counterObserver.observe( el );
		} );
	}

	// ── Lazy Images Fallback ───────────────────────────────────────────────────

	if ( ! ( 'loading' in HTMLImageElement.prototype ) ) {
		const lazyImages = document.querySelectorAll( 'img[loading="lazy"]' );
		if ( lazyImages.length && 'IntersectionObserver' in window ) {
			const imgObserver = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						const img = entry.target;
						if ( img.dataset.src ) {
							img.src = img.dataset.src;
						}
						imgObserver.unobserve( img );
					}
				} );
			} );
			lazyImages.forEach( img => imgObserver.observe( img ) );
		}
	}

	// ── WhatsApp Float Pulse ───────────────────────────────────────────────────

	const waFloat = document.querySelector( '.stmc-whatsapp-float' );
	if ( waFloat ) {
		// Show after 3 seconds
		setTimeout( function () {
			waFloat.classList.add( 'is-visible' );
		}, 3000 );
	}

	// ── FAQ Accordion (native) ────────────────────────────────────────────────

	const accordions = document.querySelectorAll( '.stmc-accordion__item' );
	accordions.forEach( function ( item ) {
		const btn    = item.querySelector( '.stmc-accordion__btn' );
		const panel  = item.querySelector( '.stmc-accordion__panel' );

		if ( ! btn || ! panel ) return;

		btn.addEventListener( 'click', function () {
			const isOpen = btn.getAttribute( 'aria-expanded' ) === 'true';

			// Close all others (single-open behavior)
			accordions.forEach( function ( other ) {
				const otherBtn   = other.querySelector( '.stmc-accordion__btn' );
				const otherPanel = other.querySelector( '.stmc-accordion__panel' );
				if ( otherBtn && otherPanel && other !== item ) {
					otherBtn.setAttribute( 'aria-expanded', 'false' );
					otherPanel.style.maxHeight = '0';
					other.classList.remove( 'is-open' );
				}
			} );

			// Toggle current
			if ( isOpen ) {
				btn.setAttribute( 'aria-expanded', 'false' );
				panel.style.maxHeight = '0';
				item.classList.remove( 'is-open' );
			} else {
				btn.setAttribute( 'aria-expanded', 'true' );
				panel.style.maxHeight = panel.scrollHeight + 'px';
				item.classList.add( 'is-open' );
			}
		} );

		// Initialize closed
		btn.setAttribute( 'aria-expanded', 'false' );
		panel.style.maxHeight = '0';
		panel.style.overflow  = 'hidden';
		panel.style.transition = 'max-height 0.35s cubic-bezier(0.25, 1, 0.5, 1)';
	} );

	// ── Page loader ───────────────────────────────────────────────────────────

	document.documentElement.classList.add( 'js-ready' );

	window.addEventListener( 'load', function () {
		document.documentElement.classList.add( 'page-loaded' );
	} );

	// ── Review Form: Star Rating + AJAX Submit ─────────────────────────────────

	document.querySelectorAll( '.stmc-review-form' ).forEach( function ( formWrap ) {
		const ratingWrap  = formWrap.querySelector( '.stmc-rating-input' );
		const ratingInput = formWrap.querySelector( 'input[name="stmc_rating"]' );
		const stars       = formWrap.querySelectorAll( '.stmc-rating-star' );
		const submitBtn   = formWrap.querySelector( '.stmc-review-submit' );
		const successBox  = formWrap.querySelector( '.stmc-review-form__success' );
		const errorBox    = formWrap.querySelector( '.stmc-review-form__error' );
		const fieldsWrap  = formWrap.querySelector( '.stmc-review-form__fields' );

		function paintStars( value ) {
			stars.forEach( function ( star ) {
				star.classList.toggle( 'is-active', parseInt( star.dataset.value, 10 ) <= value );
			} );
		}

		if ( ratingWrap && ratingInput ) {
			paintStars( parseInt( ratingInput.value, 10 ) || 5 );

			stars.forEach( function ( star ) {
				star.addEventListener( 'click', function () {
					const value = parseInt( star.dataset.value, 10 );
					ratingInput.value = String( value );
					paintStars( value );
				} );
			} );
		}

		if ( ! submitBtn ) return;

		submitBtn.addEventListener( 'click', function () {
			if ( errorBox ) { errorBox.hidden = true; errorBox.textContent = ''; }

			const nameInput    = formWrap.querySelector( '[name="stmc_reviewer_name"]' );
			const contentInput = formWrap.querySelector( '[name="stmc_content"]' );

			if ( ! nameInput || nameInput.value.trim().length < 2 ) {
				showReviewError( errorBox, 'لطفاً نام خود را وارد کنید.' );
				nameInput?.focus();
				return;
			}

			if ( ! contentInput || contentInput.value.trim().length < 10 ) {
				showReviewError( errorBox, 'لطفاً نظر خود را با جزئیات بیشتری بنویسید.' );
				contentInput?.focus();
				return;
			}

			const data = new FormData();
			fieldsWrap.querySelectorAll( 'input, textarea' ).forEach( function ( el ) {
				if ( el.name ) data.append( el.name, el.value );
			} );

			const textEl    = submitBtn.querySelector( '.stmc-btn-text' );
			const loadingEl = submitBtn.querySelector( '.stmc-btn-loading' );
			submitBtn.disabled = true;
			if ( textEl )    textEl.hidden    = true;
			if ( loadingEl ) loadingEl.hidden = false;

			fetch( ( stmcData && stmcData.ajaxUrl ) || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body:   data,
			} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( json ) {
					if ( json.success ) {
						fieldsWrap.hidden = true;
						if ( successBox ) successBox.hidden = false;
					} else {
						showReviewError( errorBox, json.data?.message || 'خطا در ارسال. لطفاً مجدداً تلاش کنید.' );
					}
				} )
				.catch( function () {
					showReviewError( errorBox, 'خطای شبکه. لطفاً اتصال اینترنت خود را بررسی کنید.' );
				} )
				.finally( function () {
					submitBtn.disabled = false;
					if ( textEl )    textEl.hidden    = false;
					if ( loadingEl ) loadingEl.hidden = true;
				} );
		} );
	} );

	function showReviewError( el, msg ) {
		if ( ! el ) return;
		el.textContent = msg;
		el.hidden = false;
		el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

} )();

/**
 * SignTeb MedCore — Animations (فاز Motion / TASK 5)
 *
 * Scroll-reveal با IntersectionObserver + میکرو-اینترکشن‌ها + fade-inِ تصاویر.
 * سه اصلِ ایمنی رعایت شده تا موشن هرگز محتوا را «گروگان» نگیرد یا «فلش» ندهد:
 *   ۱. المان‌های «داخلِ کادرِ دید» در لحظه‌ی لود بدون انیمیشن و بلافاصله دیده
 *      می‌شوند (بدون فلشِ ناپدید-شدن/پیداشدن روی محتوای بالای صفحه).
 *   ۲. المان‌های «پایینِ صفحه» با اسکرول ظاهر می‌شوند.
 *   ۳. failsafe: اگر به هر دلیلی observer شلیک نکرد، بعد از ۲٫۵ ثانیه همه‌چیز
 *      نمایان می‌شود. نبودِ IntersectionObserver هم = نمایشِ فوریِ همه.
 * prefers-reduced-motion کاملاً محترم شمرده می‌شود (همه‌چیز آنی).
 */

( function () {
	'use strict';

	var doc    = document;
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	var REVEAL_TARGETS = [
		'.stmb-doctor-card',
		'.stmb-service-card',
		'.stmb-review-card',
		'.stmb-faq-item',
		'.stmb-stat-card',
		'.stmb-before-after, .stmb-ba, figure.stmb-before-after',
		'.stmb-appt-card',
		'.stmb-contact-cta__inner',
		'.wp-block-post-content > h2.has-text-align-center'
	].join( ',' );

	function revealAll() {
		doc.querySelectorAll( '.will-animate' ).forEach( function ( el ) {
			el.classList.add( 'is-visible' );
		} );
	}

	// ── حالت کاهش حرکت: همه‌چیز آنی و نمایان ──────────────────────────────────
	if ( reduce ) {
		revealAll();
		fadeImagesInstant();
		return;
	}

	// ── جمع‌آوریِ اهداف: کارت‌های گریدها (با stagger) + سکشن‌ها ─────────────────
	var targets = [];

	[ '.stmb-doctors-grid', '.stmb-service-grid', '.stmb-testimonials__grid', '.stmb-stats__inner' ]
		.forEach( function ( gridSel ) {
			doc.querySelectorAll( gridSel ).forEach( function ( grid ) {
				Array.prototype.forEach.call( grid.children, function ( card, i ) {
					card.dataset.delay = String( Math.min( i * 0.07, 0.42 ) );
					targets.push( card );
				} );
			} );
		} );

	doc.querySelectorAll( REVEAL_TARGETS ).forEach( function ( el ) {
		if ( targets.indexOf( el ) === -1 ) {
			targets.push( el );
		}
	} );

	var supportsIO = 'IntersectionObserver' in window;

	// نقطه‌ی «داخل دید در لحظه‌ی لود» — کمی سخاوتمندانه تا هیرو/ردیف اولِ محتوا
	// بدون انیمیشن و بدون فلش نمایش داده شوند.
	var foldLine = window.innerHeight * 0.92;

	targets.forEach( function ( el ) {
		el.classList.add( 'will-animate' );
		var rect = el.getBoundingClientRect();
		if ( ! supportsIO || rect.top < foldLine ) {
			// بالای fold یا بدون IO → بلافاصله نمایان (بدون فلش، بدون انیمیشنِ گم‌شده)
			el.classList.add( 'is-visible' );
		}
	} );

	if ( supportsIO ) {
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				var el    = entry.target;
				var delay = parseFloat( el.dataset.delay || '0' );
				window.setTimeout( function () {
					el.classList.add( 'is-visible' );
				}, delay * 1000 );
				observer.unobserve( el );
			} );
		}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' } );

		targets.forEach( function ( el ) {
			if ( ! el.classList.contains( 'is-visible' ) ) {
				observer.observe( el );
			}
		} );
	}

	// ── failsafe: هرگز محتوا نباید مخفی بماند ─────────────────────────────────
	window.setTimeout( revealAll, 2500 );

	// ── fade-inِ نرمِ تصاویر هنگام لود (اسکلتونِ سبک زیرِ تصویر) ────────────────
	fadeImages();

	// ── میکرو-اینترکشن: willChange روی هاورِ کارت‌ها (پرفورمنس) ────────────────
	doc.querySelectorAll( '.stmb-doctor-card, .stmb-service-card, .stmb-review-card' ).forEach( function ( card ) {
		card.addEventListener( 'mouseenter', function () { card.style.willChange = 'transform'; } );
		card.addEventListener( 'mouseleave', function () { card.style.willChange = 'auto'; } );
	} );

	// ── پارالاکسِ ملایمِ مدیای هیرو (اگر وجود داشت) ────────────────────────────
	var heroMedia = doc.querySelector( '.stmb-doctor-hero__media' );
	if ( heroMedia ) {
		window.addEventListener( 'scroll', function () {
			var y = window.scrollY;
			if ( y < window.innerHeight ) {
				heroMedia.style.transform = 'translateY(' + ( y * 0.08 ) + 'px)';
			}
		}, { passive: true } );
	}

	// ── توابع کمکی ────────────────────────────────────────────────────────────

	function imageList() {
		return doc.querySelectorAll(
			'.stmb-doctor-card img, .stmb-service-card img, .stmb-ba img, .stmb-before-after img, .wp-block-post-featured-image img'
		);
	}

	function fadeImages() {
		imageList().forEach( function ( img ) {
			img.classList.add( 'stmc-imgfade' );
			if ( img.complete && img.naturalWidth > 0 ) {
				img.classList.add( 'is-loaded' ); // از کش آمده — بدون فلش
			} else {
				img.addEventListener( 'load',  function () { img.classList.add( 'is-loaded' ); }, { once: true } );
				img.addEventListener( 'error', function () { img.classList.add( 'is-loaded' ); }, { once: true } );
			}
		} );
		// failsafe: تصویری نباید نامرئی بماند
		window.setTimeout( function () {
			doc.querySelectorAll( '.stmc-imgfade:not(.is-loaded)' ).forEach( function ( img ) {
				img.classList.add( 'is-loaded' );
			} );
		}, 2500 );
	}

	function fadeImagesInstant() {
		imageList().forEach( function ( img ) { img.classList.add( 'stmc-imgfade', 'is-loaded' ); } );
	}

} )();

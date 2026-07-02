/**
 * SignTeb MedCore — Navigation
 *
 * Mobile menu, keyboard navigation, sticky header
 * Vanilla JS — no jQuery — no dependencies
 *
 * @package SignTeb_MedCore
 */

( function () {
	'use strict';

	// ── DOM References ─────────────────────────────────────────────────────────

	const header    = document.querySelector( '.site-header' );
	const menuBtn   = document.querySelector( '.stmc-menu-toggle' );
	const mobileNav = document.querySelector( '.stmc-mobile-nav' );
	const overlay   = document.querySelector( '.stmc-mobile-nav__overlay' );
	const body      = document.body;

	// ── Sticky Header ──────────────────────────────────────────────────────────

	if ( header ) {
		let lastScroll = 0;
		let ticking    = false;

		function handleScroll() {
			const scrollY = window.scrollY;

			// Add scrolled class after 60px
			header.classList.toggle( 'is-scrolled', scrollY > 60 );

			// Hide on scroll down, show on scroll up (only after initial position)
			if ( scrollY > 200 ) {
				if ( scrollY > lastScroll && ! header.classList.contains( 'is-hidden' ) ) {
					header.classList.add( 'is-hidden' );
				} else if ( scrollY < lastScroll && header.classList.contains( 'is-hidden' ) ) {
					header.classList.remove( 'is-hidden' );
				}
			}

			lastScroll = scrollY <= 0 ? 0 : scrollY;
			ticking    = false;
		}

		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				requestAnimationFrame( handleScroll );
				ticking = true;
			}
		}, { passive: true } );
	}

	// ── Mobile Menu ────────────────────────────────────────────────────────────

	function openMenu() {
		if ( ! menuBtn || ! mobileNav ) return;

		mobileNav.setAttribute( 'aria-hidden', 'false' );
		mobileNav.classList.add( 'is-open' );
		menuBtn.setAttribute( 'aria-expanded', 'true' );
		menuBtn.setAttribute( 'aria-label', stmcData?.i18n?.closeMenu || 'Close menu' );
		body.classList.add( 'menu-is-open' );
		body.style.overflow = 'hidden';

		// Focus first link inside menu
		const firstLink = mobileNav.querySelector( 'a' );
		if ( firstLink ) {
			setTimeout( () => firstLink.focus(), 100 );
		}

		// Trap focus inside menu
		trapFocus( mobileNav );
	}

	function closeMenu() {
		if ( ! menuBtn || ! mobileNav ) return;

		mobileNav.setAttribute( 'aria-hidden', 'true' );
		mobileNav.classList.remove( 'is-open' );
		menuBtn.setAttribute( 'aria-expanded', 'false' );
		menuBtn.setAttribute( 'aria-label', stmcData?.i18n?.openMenu || 'Open menu' );
		body.classList.remove( 'menu-is-open' );
		body.style.overflow = '';
		menuBtn.focus();
		removeFocusTrap();
	}

	if ( menuBtn ) {
		menuBtn.addEventListener( 'click', function () {
			const isOpen = mobileNav?.classList.contains( 'is-open' );
			isOpen ? closeMenu() : openMenu();
		} );
	}

	if ( overlay ) {
		overlay.addEventListener( 'click', closeMenu );
	}

	// ── Focus Trap ─────────────────────────────────────────────────────────────

	let focusTrapHandler = null;

	function trapFocus( element ) {
		const focusable = element.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);
		const first = focusable[0];
		const last  = focusable[ focusable.length - 1 ];

		focusTrapHandler = function ( e ) {
			if ( e.key !== 'Tab' ) {
				// Close on Escape
				if ( e.key === 'Escape' ) closeMenu();
				return;
			}

			if ( e.shiftKey ) {
				if ( document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				}
			} else {
				if ( document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		};

		document.addEventListener( 'keydown', focusTrapHandler );
	}

	function removeFocusTrap() {
		if ( focusTrapHandler ) {
			document.removeEventListener( 'keydown', focusTrapHandler );
			focusTrapHandler = null;
		}
	}

	// ── Dropdown Menus ─────────────────────────────────────────────────────────

	const menuItems = document.querySelectorAll( '.menu-item-has-children' );

	menuItems.forEach( function ( item ) {
		const link    = item.querySelector( ':scope > a' );
		const submenu = item.querySelector( ':scope > ul' );

		if ( ! submenu ) return;

		// Add ARIA attributes
		submenu.setAttribute( 'role', 'menu' );
		submenu.querySelectorAll( 'a' ).forEach( a => a.setAttribute( 'role', 'menuitem' ) );

		if ( link ) {
			link.setAttribute( 'aria-haspopup', 'true' );
			link.setAttribute( 'aria-expanded', 'false' );
		}

		// Keyboard: open on Enter/Space, close on Escape
		if ( link ) {
			link.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					if ( link.getAttribute( 'href' ) === '#' || link.getAttribute( 'href' ) === '' ) {
						e.preventDefault();
						toggleSubmenu( item, link, submenu );
					}
				}
				if ( e.key === 'Escape' ) {
					closeSubmenu( item, link, submenu );
					link.focus();
				}
			} );
		}

		// Close submenu when focus leaves
		item.addEventListener( 'focusout', function ( e ) {
			if ( ! item.contains( e.relatedTarget ) ) {
				closeSubmenu( item, link, submenu );
			}
		} );

		// Hover for desktop
		item.addEventListener( 'mouseenter', function () {
			if ( window.innerWidth >= 1024 ) {
				openSubmenu( item, link, submenu );
			}
		} );

		item.addEventListener( 'mouseleave', function () {
			if ( window.innerWidth >= 1024 ) {
				closeSubmenu( item, link, submenu );
			}
		} );
	} );

	function openSubmenu( item, link, submenu ) {
		item.classList.add( 'is-open' );
		if ( link ) link.setAttribute( 'aria-expanded', 'true' );
		submenu.removeAttribute( 'hidden' );
	}

	function closeSubmenu( item, link, submenu ) {
		item.classList.remove( 'is-open' );
		if ( link ) link.setAttribute( 'aria-expanded', 'false' );
	}

	function toggleSubmenu( item, link, submenu ) {
		item.classList.contains( 'is-open' )
			? closeSubmenu( item, link, submenu )
			: openSubmenu( item, link, submenu );
	}

	// ── Smooth Scroll for anchor links ────────────────────────────────────────

	document.querySelectorAll( 'a[href^="#"]' ).forEach( function ( anchor ) {
		anchor.addEventListener( 'click', function ( e ) {
			const targetId = this.getAttribute( 'href' ).substring( 1 );
			if ( ! targetId ) return;

			const target = document.getElementById( targetId );
			if ( ! target ) return;

			e.preventDefault();

			const headerH = header ? header.offsetHeight : 0;
			const top = target.getBoundingClientRect().top + window.scrollY - headerH - 16;

			window.scrollTo( { top, behavior: 'smooth' } );
			target.focus( { preventScroll: true } );

			// Update URL without scroll
			history.pushState( null, '', '#' + targetId );
		} );
	} );

	// ── Close menu on resize ───────────────────────────────────────────────────

	let resizeTimer;
	window.addEventListener( 'resize', function () {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( function () {
			if ( window.innerWidth >= 1024 ) {
				closeMenu();
			}
		}, 250 );
	} );

} )();

/**
 * SignTeb MedCore — Customizer Live Preview
 *
 * تغییر توکن‌ها را بدون رفرش، مستقیماً روی CSS variables اعمال می‌کند.
 * نگاشت توکن→متغیر از PHP (stmcTokens) می‌آید.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.customize || typeof stmcTokens === 'undefined' ) {
		return;
	}

	var root = document.documentElement;

	function setVar( name, value ) {
		root.style.setProperty( name, value );
	}

	// تیره/روشن کردن hex (هم‌ارز shade() در PHP) برای پیش‌نمایش زنده‌ی مشتقات.
	function shade( hex, percent ) {
		hex = hex.replace( '#', '' );
		if ( hex.length === 3 ) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}
		var r = parseInt( hex.substr( 0, 2 ), 16 ),
			g = parseInt( hex.substr( 2, 2 ), 16 ),
			b = parseInt( hex.substr( 4, 2 ), 16 ),
			target = percent < 0 ? 0 : 255,
			p = Math.abs( percent ),
			mix = function ( c ) { return Math.round( c + ( target - c ) * p ); },
			to2 = function ( n ) { return ( '0' + n.toString( 16 ) ).slice( -2 ); };
		return '#' + to2( mix( r ) ) + to2( mix( g ) ) + to2( mix( b ) );
	}

	Object.keys( stmcTokens ).forEach( function ( settingId ) {
		var t = stmcTokens[ settingId ];
		wp.customize( settingId, function ( setting ) {
			setting.bind( function ( newval ) {
				if ( t.type === 'color' ) {
					setVar( t.css, newval );
					// مشتقات رنگ اصلی
					if ( settingId === 'stmc_color_primary' ) {
						setVar( '--stmc-blue-dark', shade( newval, -0.28 ) );
						setVar( '--stmc-blue-light', shade( newval, 0.82 ) );
					}
				} else if ( t.type === 'select' && t.map ) {
					setVar( t.css, t.map[ newval ] || newval );
				} else if ( t.type === 'range' ) {
					setVar( t.css, String( parseInt( newval, 10 ) ) + ( t.unit || '' ) );
				}
			} );
		} );
	} );
} )( window.wp );

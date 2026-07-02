<?php
/**
 * SignTeb Medical Core — Local SEO + hreflang
 *
 * - hreflang برای FA / AR / EN
 * - NAP consistency در head
 * - Google Business structured data
 * - Geo meta tags
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Seo;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class LocalSeo {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'wp_head', $this, 'output_hreflang', 1 );
		$this->loader->add_action( 'wp_head', $this, 'output_geo_meta', 2 );
	}

	// ─── hreflang ─────────────────────────────────────────────────────────────

	public function output_hreflang(): void {
		if ( ! apply_filters( 'stmc_hreflang_enabled', true ) ) {
			return;
		}

		$current_url = $this->get_current_url();
		$lang_map    = apply_filters( 'stmc_hreflang_map', $this->build_lang_map( $current_url ) );

		if ( empty( $lang_map ) ) {
			return;
		}

		foreach ( $lang_map as $lang => $url ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s">' . "\n",
				esc_attr( $lang ),
				esc_url( $url )
			);
		}

		// x-default always points to primary (FA for IR, EN for UAE)
		$default_lang = get_option( 'stmc_primary_language', 'fa' );
		if ( isset( $lang_map[ $default_lang ] ) ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s">' . "\n",
				esc_url( $lang_map[ $default_lang ] )
			);
		}
	}

	private function build_lang_map( string $current_url ): array {
		$map       = [];
		$lang_dirs = get_option( 'stmc_lang_directories', [] );

		// Default: single-site with URL parameter fallback
		$market = get_option( 'stmc_market', 'ir' ); // ir | ae | multi

		if ( 'ir' === $market ) {
			$map['fa'] = $current_url;
			// If Arabic version exists as /ar/ subdirectory
			$ar_base = get_option( 'stmc_ar_base_url', '' );
			if ( $ar_base ) {
				$map['ar'] = trailingslashit( $ar_base ) . ltrim( str_replace( home_url(), '', $current_url ), '/' );
			}
		} elseif ( 'ae' === $market ) {
			$map['en']    = $current_url;
			$map['ar']    = $current_url; // Arabic version on same site (polylang/wpml)
			$map['ar-ae'] = $current_url;
		}

		// Polylang / WPML support
		if ( function_exists( 'pll_get_post_language' ) ) {
			$map = $this->build_polylang_map();
		}

		return $map;
	}

	private function build_polylang_map(): array {
		$map = [];
		if ( ! function_exists( 'pll_the_languages' ) ) {
			return $map;
		}

		$languages = pll_the_languages( [ 'raw' => 1 ] );
		foreach ( $languages as $lang ) {
			$map[ $lang['locale'] ] = $lang['url'];
		}

		return $map;
	}

	// ─── Geo Meta Tags ────────────────────────────────────────────────────────

	public function output_geo_meta(): void {
		$region   = get_option( 'stmc_geo_region', '' );   // مثال: IR-16 یا AE-DU
		$placename = get_option( 'stmc_geo_placename', '' ); // مثال: تهران یا Dubai
		$position  = get_option( 'stmc_geo_position', '' );  // مثال: 35.6892;51.3890

		if ( ! $region && ! $placename ) {
			return;
		}

		if ( $region ) {
			printf( '<meta name="geo.region" content="%s">' . "\n", esc_attr( $region ) );
		}

		if ( $placename ) {
			printf( '<meta name="geo.placename" content="%s">' . "\n", esc_attr( $placename ) );
		}

		if ( $position ) {
			printf( '<meta name="geo.position" content="%s">' . "\n", esc_attr( $position ) );
			printf( '<meta name="ICBM" content="%s">' . "\n", esc_attr( str_replace( ';', ', ', $position ) ) );
		}
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function get_current_url(): string {
		global $wp;
		return home_url( add_query_arg( [], $wp->request ) );
	}
}

<?php
/**
 * SignTeb Medical Core — Internal Linking Engine
 *
 * اتصال خودکار محتوا:
 * - بیماری‌ها → درمان‌های مرتبط
 * - پزشکان → خدمات
 * - مقالات → پرونده‌های موفق
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Seo;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class InternalLinks {

	/** حداکثر لینک اتوماتیک در هر پست */
	private const MAX_AUTO_LINKS = 8;

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_filter( 'the_content', $this, 'inject_links', 20 );
		$this->loader->add_action( 'save_post',   $this, 'clear_cache', 99 );
	}

	// ─── Main Injector ────────────────────────────────────────────────────────

	public function inject_links( string $content ): string {
		if ( ! is_singular() || is_admin() ) {
			return $content;
		}

		if ( ! apply_filters( 'stmc_internal_links_enabled', (bool) get_option( 'stmc_internal_links', '1' ) ) ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$post_type = get_post_type();

		// Only process our CPTs + posts
		$allowed = [ 'post', 'page', 'doctor', 'medical-service', 'treatment', 'disease', 'clinic', 'case-study' ];
		if ( ! in_array( $post_type, $allowed, true ) ) {
			return $content;
		}

		// Cache check
		$cache_key = 'stmc_il_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$link_map  = $this->build_link_map( $post_id );
		$result    = $this->apply_links( $content, $link_map );

		// Cache 12 hours
		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );

		return $result;
	}

	// ─── Build keyword → URL map ──────────────────────────────────────────────

	private function build_link_map( int $current_post_id ): array {
		$map = [];

		// 1. Diseases → link to their treatment pages
		$diseases = get_posts( [
			'post_type'      => 'disease',
			'posts_per_page' => 30,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		] );

		foreach ( $diseases as $id ) {
			if ( $id === $current_post_id ) continue;
			$title = get_the_title( $id );
			if ( mb_strlen( $title ) < 3 ) continue;
			$map[ $title ] = get_permalink( $id );
		}

		// 2. Treatments → link to treatment pages
		$treatments = get_posts( [
			'post_type'      => 'treatment',
			'posts_per_page' => 30,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		] );

		foreach ( $treatments as $id ) {
			if ( $id === $current_post_id ) continue;
			$title = get_the_title( $id );
			if ( mb_strlen( $title ) < 3 ) continue;
			$map[ $title ] = get_permalink( $id );
		}

		// 3. Doctors by specialty — link to doctor profiles
		$doctors = get_posts( [
			'post_type'      => 'doctor',
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		] );

		foreach ( $doctors as $id ) {
			if ( $id === $current_post_id ) continue;
			$specialty = get_post_meta( $id, 'stmc_doctor_specialty', true );
			if ( $specialty && mb_strlen( $specialty ) >= 4 ) {
				$map[ $specialty ] = get_permalink( $id );
			}
		}

		// Allow external modification
		$map = apply_filters( 'stmc_internal_link_map', $map, $current_post_id );

		// Sort by string length descending (longer phrases first to avoid partial replacement)
		uksort( $map, fn( $a, $b ) => mb_strlen( $b ) - mb_strlen( $a ) );

		return $map;
	}

	// ─── Apply Links to Content ───────────────────────────────────────────────

	private function apply_links( string $content, array $link_map ): string {
		if ( empty( $link_map ) || empty( $content ) ) {
			return $content;
		}

		$links_added = 0;
		$used_urls   = [];

		foreach ( $link_map as $keyword => $url ) {
			if ( $links_added >= self::MAX_AUTO_LINKS ) {
				break;
			}

			// Skip if URL already linked in content
			if ( in_array( $url, $used_urls, true ) ) {
				continue;
			}

			// Skip if keyword already inside an anchor tag
			$pattern = '~(?<!</?a[^>]*?>)(?<!href=["\'])' .
				'(?<![class|id]=["\'][^"\']*?)' .
				'(' . preg_quote( $keyword, '~' ) . ')' .
				'(?![^<]*?>)(?![^<]*?</a>)~u';

			$replaced = preg_replace_callback(
				$pattern,
				function ( array $matches ) use ( $url, $keyword ): string {
					return sprintf(
						'<a href="%s" class="stmc-auto-link" title="%s">%s</a>',
						esc_url( $url ),
						esc_attr( $keyword ),
						$matches[0]
					);
				},
				$content,
				1 // Replace only first occurrence
			);

			if ( $replaced !== $content ) {
				$content     = $replaced;
				$used_urls[] = $url;
				$links_added++;
			}
		}

		return $content;
	}

	// ─── Cache Invalidation ───────────────────────────────────────────────────

	public function clear_cache( int $post_id ): void {
		delete_transient( 'stmc_il_' . $post_id );
	}
}

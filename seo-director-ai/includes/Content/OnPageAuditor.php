<?php
/**
 * On-page SEO auditor. Because the content lives in the WordPress database,
 * no crawler is needed: published posts/pages are scanned directly for the
 * classic on-page problems (title length, missing meta description, H1 in
 * content, thin content, images without alt, no headings, no internal
 * links). Results are cached for an hour — the scan is read-only and cheap
 * but not free on large sites.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

defined( 'ABSPATH' ) || exit;

final class OnPageAuditor {

	private const CACHE_KEY = 'sda_onpage_audit';
	private const CACHE_TTL = HOUR_IN_SECONDS;
	private const MAX_POSTS = 500;

	/** Meta-description keys of the common SEO plugins, checked in order. */
	private const META_DESC_KEYS = [
		'_yoast_wpseo_metadesc',
		'rank_math_description',
		'_aioseo_description',
	];

	/**
	 * @return array{scanned: int, issues_total: int, generated_at: string, pages: array<int, array<string, mixed>>}
	 */
	public function audit( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_POSTS,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		$pages = [];
		$total = 0;

		foreach ( $posts as $post ) {
			$issues = $this->check( $post );
			if ( [] === $issues ) {
				continue;
			}
			$total  += count( $issues );
			$pages[] = [
				'id'     => $post->ID,
				'title'  => (string) get_the_title( $post ),
				'url'    => (string) get_permalink( $post ),
				'issues' => $issues,
			];
		}

		// Worst pages first.
		usort( $pages, static fn( array $a, array $b ) => count( $b['issues'] ) <=> count( $a['issues'] ) );

		$result = [
			'scanned'      => count( $posts ),
			'issues_total' => $total,
			'generated_at' => gmdate( 'c' ),
			'pages'        => $pages,
		];

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * @return array<int, array{code: string, severity: 'high'|'medium'|'low', message: string}>
	 */
	private function check( \WP_Post $post ): array {
		$issues  = [];
		$title   = (string) get_the_title( $post );
		$content = (string) $post->post_content;
		$text    = wp_strip_all_tags( $content );
		$words   = $this->word_count( $text );

		$title_len = mb_strlen( $title );
		if ( $title_len < 25 ) {
			$issues[] = $this->issue( 'title_short', 'medium', __( 'Title is shorter than 25 characters.', 'seo-director-ai' ) );
		} elseif ( $title_len > 65 ) {
			$issues[] = $this->issue( 'title_long', 'medium', __( 'Title is longer than 65 characters and will be truncated in results.', 'seo-director-ai' ) );
		}

		if ( ! $this->has_meta_description( $post->ID ) ) {
			$issues[] = $this->issue( 'meta_missing', 'high', __( 'No meta description found (checked Yoast, Rank Math, AIOSEO, and the excerpt).', 'seo-director-ai' ) );
		}

		if ( preg_match( '/<h1[\s>]/i', $content ) ) {
			$issues[] = $this->issue( 'h1_in_content', 'medium', __( 'An H1 tag inside the content competes with the page title — use H2 and below.', 'seo-director-ai' ) );
		}

		if ( $words < 300 ) {
			$issues[] = $this->issue( 'thin_content', 'high', __( 'Thin content: fewer than 300 words.', 'seo-director-ai' ) );
		}

		if ( $words >= 300 && ! preg_match( '/<h[2-4][\s>]/i', $content ) ) {
			$issues[] = $this->issue( 'no_headings', 'medium', __( 'No H2–H4 headings — long content needs structure.', 'seo-director-ai' ) );
		}

		$images_no_alt = $this->images_without_alt( $content );
		if ( $images_no_alt > 0 ) {
			$issues[] = $this->issue(
				'img_no_alt',
				'low',
				sprintf(
					/* translators: %d: number of images */
					__( '%d image(s) without alt text.', 'seo-director-ai' ),
					$images_no_alt
				)
			);
		}

		if ( $words >= 300 && ! $this->has_internal_link( $content ) ) {
			$issues[] = $this->issue( 'no_internal_links', 'medium', __( 'No internal links to other pages on this site.', 'seo-director-ai' ) );
		}

		return $issues;
	}

	/**
	 * @param 'high'|'medium'|'low' $severity
	 * @return array{code: string, severity: 'high'|'medium'|'low', message: string}
	 */
	private function issue( string $code, string $severity, string $message ): array {
		return [ 'code' => $code, 'severity' => $severity, 'message' => $message ];
	}

	private function has_meta_description( int $post_id ): bool {
		foreach ( self::META_DESC_KEYS as $key ) {
			if ( '' !== trim( (string) get_post_meta( $post_id, $key, true ) ) ) {
				return true;
			}
		}

		return '' !== trim( (string) get_post_field( 'post_excerpt', $post_id ) );
	}

	private function images_without_alt( string $content ): int {
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $content, $matches ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $matches[0] as $img ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img ) ) {
				$count++;
			}
		}

		return $count;
	}

	private function has_internal_link( string $content ): bool {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return false;
		}

		foreach ( $matches[1] as $url ) {
			if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
				return true;
			}
			if ( '' !== $host && str_contains( $url, $host ) ) {
				return true;
			}
		}

		return false;
	}

	/** Word count that also works for Persian (whitespace-token based). */
	private function word_count( string $text ): int {
		$tokens = preg_split( '/\s+/u', trim( $text ) );

		return is_array( $tokens ) ? count( array_filter( $tokens, static fn( $t ) => '' !== $t ) ) : 0;
	}
}

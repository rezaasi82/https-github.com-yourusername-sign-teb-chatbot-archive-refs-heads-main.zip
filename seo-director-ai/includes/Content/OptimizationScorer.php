<?php
/**
 * Content optimization score (0–100). Deterministic checks against a target
 * keyword — placement, structure, depth, linking — following the product's
 * determinism-first rule: the number is reproducible; the optional AI pass
 * only adds an entity-coverage list on top and never changes the score.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

use SEODirector\Ai\InsightService;

defined( 'ABSPATH' ) || exit;

final class OptimizationScorer {

	public function __construct( private InsightService $insights ) {}

	/**
	 * @return array{score: int, checks: array<int, array{code: string, label: string, points: int, max: int, detail: string}>, entities: array{covered: string[], missing: string[]}|null}|\WP_Error
	 */
	public function score( int $post_id, string $keyword, bool $with_entities = false ): array|\WP_Error {
		$post = get_post( $post_id );
		if ( null === $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'sda_not_found', __( 'Post not found or not published.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return new \WP_Error( 'sda_input', __( 'A target keyword is required.', 'seo-director-ai' ), [ 'status' => 400 ] );
		}

		$kw       = mb_strtolower( $keyword );
		$title    = mb_strtolower( (string) get_the_title( $post ) );
		$content  = (string) $post->post_content;
		$text     = wp_strip_all_tags( $content );
		$text_lc  = mb_strtolower( $text );
		$words    = $this->tokens( $text_lc );
		$word_cnt = count( $words );

		$checks = [];

		// Keyword in title — the strongest single on-page signal.
		$checks[] = $this->check(
			'kw_title',
			__( 'Keyword in title', 'seo-director-ai' ),
			str_contains( $title, $kw ) ? 15 : 0,
			15,
			str_contains( $title, $kw ) ? __( 'Found.', 'seo-director-ai' ) : __( 'Add the keyword to the title.', 'seo-director-ai' )
		);

		// Keyword early in the content (first ~100 words).
		$intro    = implode( ' ', array_slice( $words, 0, 100 ) );
		$in_intro = str_contains( $intro, $kw );
		$checks[] = $this->check(
			'kw_intro',
			__( 'Keyword in the introduction', 'seo-director-ai' ),
			$in_intro ? 10 : 0,
			10,
			$in_intro ? __( 'Found in the first 100 words.', 'seo-director-ai' ) : __( 'Mention the keyword in the first 100 words.', 'seo-director-ai' )
		);

		// Keyword in at least one H2–H4.
		$in_heading = false;
		if ( preg_match_all( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/isu', $content, $matches ) ) {
			foreach ( $matches[1] as $heading ) {
				if ( str_contains( mb_strtolower( wp_strip_all_tags( $heading ) ), $kw ) ) {
					$in_heading = true;
					break;
				}
			}
		}
		$checks[] = $this->check(
			'kw_heading',
			__( 'Keyword in a subheading', 'seo-director-ai' ),
			$in_heading ? 10 : 0,
			10,
			$in_heading ? __( 'Found.', 'seo-director-ai' ) : __( 'Use the keyword in an H2/H3.', 'seo-director-ai' )
		);

		// Density between 0.4% and 2.5% — under is invisible, over is stuffing.
		$occurrences = 0 === $word_cnt ? 0 : substr_count( $text_lc, $kw );
		$density     = 0 === $word_cnt ? 0.0 : ( $occurrences * 100.0 / $word_cnt );
		$density_ok  = $density >= 0.4 && $density <= 2.5;
		$checks[]    = $this->check(
			'kw_density',
			__( 'Keyword density', 'seo-director-ai' ),
			$density_ok ? 10 : ( $occurrences > 0 ? 5 : 0 ),
			10,
			sprintf( '%.1f%% (%d×)', $density, $occurrences )
		);

		// Depth: full points at 800+ words, partial from 300.
		$depth_points = $word_cnt >= 800 ? 15 : ( $word_cnt >= 300 ? (int) round( 15 * ( $word_cnt - 300 ) / 500 ) : 0 );
		$checks[]     = $this->check(
			'depth',
			__( 'Content depth', 'seo-director-ai' ),
			$depth_points,
			15,
			sprintf(
				/* translators: %d: word count */
				__( '%d words.', 'seo-director-ai' ),
				$word_cnt
			)
		);

		// Structure: 3+ subheadings.
		$heading_count = preg_match_all( '/<h[2-4][\s>]/i', $content );
		$checks[]      = $this->check(
			'structure',
			__( 'Heading structure', 'seo-director-ai' ),
			$heading_count >= 3 ? 10 : ( $heading_count > 0 ? 5 : 0 ),
			10,
			sprintf(
				/* translators: %d: heading count */
				__( '%d subheadings.', 'seo-director-ai' ),
				(int) $heading_count
			)
		);

		// FAQ block: at least two question-style headings.
		$faq_count = preg_match_all( '/<h[2-4][^>]*>[^<]*[?؟]\s*<\/h[2-4]>/iu', $content );
		$checks[]  = $this->check(
			'faq',
			__( 'FAQ coverage', 'seo-director-ai' ),
			$faq_count >= 2 ? 10 : ( $faq_count > 0 ? 5 : 0 ),
			10,
			sprintf(
				/* translators: %d: FAQ question count */
				__( '%d question headings found.', 'seo-director-ai' ),
				(int) $faq_count
			)
		);

		// Internal links: 2+.
		$host           = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$internal_links = 0;
		if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $link_matches ) ) {
			foreach ( $link_matches[1] as $url ) {
				$is_relative = str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' );
				if ( $is_relative || ( '' !== $host && str_contains( $url, $host ) ) ) {
					$internal_links++;
				}
			}
		}
		$checks[] = $this->check(
			'internal_links',
			__( 'Internal links', 'seo-director-ai' ),
			$internal_links >= 2 ? 10 : ( $internal_links > 0 ? 5 : 0 ),
			10,
			sprintf(
				/* translators: %d: internal link count */
				__( '%d internal link(s).', 'seo-director-ai' ),
				$internal_links
			)
		);

		// Images with alt text.
		$imgs    = (int) preg_match_all( '/<img\b[^>]*>/i', $content, $img_matches );
		$no_alt  = 0;
		foreach ( $img_matches[0] ?? [] as $img ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img ) ) {
				$no_alt++;
			}
		}
		$img_points = 0 === $imgs ? 5 : ( 0 === $no_alt ? 10 : 5 );
		$checks[]   = $this->check(
			'images',
			__( 'Images & alt text', 'seo-director-ai' ),
			$img_points,
			10,
			0 === $imgs
				? __( 'No images — consider adding one.', 'seo-director-ai' )
				: sprintf(
					/* translators: 1: total images, 2: images missing alt */
					__( '%1$d image(s), %2$d missing alt.', 'seo-director-ai' ),
					$imgs,
					$no_alt
				)
		);

		$score = array_sum( array_column( $checks, 'points' ) );

		$entities = null;
		if ( $with_entities && $this->insights->is_available() ) {
			$excerpt = mb_substr( $text, 0, 4000 );
			$result  = $this->insights->generate(
				'entity_coverage',
				[ 'target_keyword' => $keyword, 'article_excerpt' => $excerpt ],
				[ 'entity_type' => 'page', 'entity_label' => (string) get_the_title( $post ) ]
			);
			if ( ! is_wp_error( $result ) ) {
				$entities = [
					'covered' => array_map( 'strval', (array) ( $result['payload']['covered'] ?? [] ) ),
					'missing' => array_map( 'strval', (array) ( $result['payload']['missing'] ?? [] ) ),
				];
			}
		}

		return [ 'score' => $score, 'checks' => $checks, 'entities' => $entities ];
	}

	/**
	 * @return array{code: string, label: string, points: int, max: int, detail: string}
	 */
	private function check( string $code, string $label, int $points, int $max, string $detail ): array {
		return [ 'code' => $code, 'label' => $label, 'points' => $points, 'max' => $max, 'detail' => $detail ];
	}

	/** @return string[] */
	private function tokens( string $text ): array {
		$tokens = preg_split( '/\s+/u', trim( $text ) );

		return is_array( $tokens ) ? array_values( array_filter( $tokens, static fn( $t ) => '' !== $t ) ) : [];
	}
}

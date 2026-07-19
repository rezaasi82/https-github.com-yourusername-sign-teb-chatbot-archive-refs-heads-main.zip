<?php
/**
 * Internal linking engine. Deterministic, AI-free: for a source post it finds
 * other published posts/pages whose title terms appear in the source content
 * but are not linked yet, and proposes anchor → target pairs. Persian-aware
 * (mb_* everywhere, fa+en stopword list).
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

defined( 'ABSPATH' ) || exit;

final class InternalLinkSuggester {

	/** Common tokens that must never drive a match. */
	private const STOPWORDS = [
		// fa
		'در', 'به', 'از', 'با', 'که', 'این', 'آن', 'برای', 'یک', 'را', 'تا', 'هم', 'و', 'یا', 'است', 'های', 'ها', 'می', 'شود', 'کرد', 'چه', 'چیست',
		// en
		'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'is', 'are', 'what', 'how', 'why',
	];

	/**
	 * Suggestions for one source post.
	 *
	 * @return array{source: array{id: int, title: string, url: string}, suggestions: array<int, array{target_id: int, target_title: string, target_url: string, anchor: string, score: int}>}|\WP_Error
	 */
	public function for_post( int $post_id, int $limit = 10 ): array|\WP_Error {
		$post = get_post( $post_id );
		if ( null === $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'sda_not_found', __( 'Post not found or not published.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$content_html = (string) $post->post_content;
		$content_text = mb_strtolower( wp_strip_all_tags( $content_html ) );
		$linked_urls  = $this->linked_urls( $content_html );
		$own_url      = (string) get_permalink( $post );

		$suggestions = [];

		foreach ( $this->candidate_targets( $post_id ) as $target ) {
			$url = $target['url'];
			if ( $url === $own_url || isset( $linked_urls[ untrailingslashit( $url ) ] ) ) {
				continue;
			}

			$match = $this->best_anchor( $content_text, $target['title'] );
			if ( null === $match ) {
				continue;
			}

			$suggestions[] = [
				'target_id'    => $target['id'],
				'target_title' => $target['title'],
				'target_url'   => $url,
				'anchor'       => $match['anchor'],
				'score'        => $match['score'],
			];
		}

		usort( $suggestions, static fn( array $a, array $b ) => $b['score'] <=> $a['score'] );

		return [
			'source'      => [ 'id' => $post->ID, 'title' => (string) get_the_title( $post ), 'url' => $own_url ],
			'suggestions' => array_slice( $suggestions, 0, max( 1, $limit ) ),
		];
	}

	/**
	 * Site-wide pass over the most recent posts — the "what should I link
	 * today" view.
	 *
	 * @return array<int, array{source: array<string, mixed>, suggestions: array<int, array<string, mixed>>}>
	 */
	public function sitewide( int $posts = 10, int $per_post = 3 ): array {
		$ids = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, min( 25, $posts ) ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
			]
		);

		$out = [];
		foreach ( $ids as $id ) {
			$result = $this->for_post( (int) $id, $per_post );
			if ( ! is_wp_error( $result ) && [] !== $result['suggestions'] ) {
				$out[] = $result;
			}
		}

		return $out;
	}

	/**
	 * All other published posts/pages as link targets (capped for memory).
	 *
	 * @return array<int, array{id: int, title: string, url: string}>
	 */
	private function candidate_targets( int $exclude_id ): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'post__not_in'   => [ $exclude_id ],
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		return array_map(
			static fn( \WP_Post $p ) => [
				'id'    => $p->ID,
				'title' => (string) $p->post_title,
				'url'   => (string) get_permalink( $p ),
			],
			$posts
		);
	}

	/**
	 * Pick the best anchor for a target inside the source text: the full
	 * title if present verbatim, else the longest significant title token
	 * that appears — scored so verbatim titles rank first.
	 *
	 * @return array{anchor: string, score: int}|null
	 */
	private function best_anchor( string $content_text, string $target_title ): ?array {
		$title = mb_strtolower( trim( $target_title ) );
		if ( '' === $title ) {
			return null;
		}

		if ( str_contains( $content_text, $title ) ) {
			return [ 'anchor' => $target_title, 'score' => 100 ];
		}

		$tokens = preg_split( '/\s+/u', $title ) ?: [];
		$best   = null;

		foreach ( $tokens as $token ) {
			if ( mb_strlen( $token ) < 4 || in_array( $token, self::STOPWORDS, true ) ) {
				continue;
			}
			if ( ! str_contains( $content_text, $token ) ) {
				continue;
			}
			$score = min( 90, 40 + mb_strlen( $token ) * 5 );
			if ( null === $best || $score > $best['score'] ) {
				$best = [ 'anchor' => $token, 'score' => $score ];
			}
		}

		return $best;
	}

	/**
	 * URLs already linked from the content, normalized without a trailing
	 * slash so both forms match.
	 *
	 * @return array<string, true>
	 */
	private function linked_urls( string $html ): array {
		$urls = [];
		if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$urls[ untrailingslashit( (string) $url ) ] = true;
			}
		}

		return $urls;
	}
}

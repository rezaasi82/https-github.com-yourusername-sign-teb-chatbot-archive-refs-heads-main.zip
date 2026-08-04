<?php
/**
 * Zombie-page detector. "Zombie" pages earn no organic value — zero clicks
 * over the window — yet still cost crawl budget and drag down site quality.
 * This crosses Search Console page metrics with the WordPress content itself
 * (word count, age, internal inbound links) and, for each zombie, recommends
 * the single best fix following the standard content-audit decision tree:
 * wait, improve, rewrite title/meta, add internal links, or prune (merge/301
 * or delete). Deterministic — no AI, so it's reproducible and cheap. Cached
 * for an hour.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

use SEODirector\Core\Schema;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\UrlCanonicalizer;

defined( 'ABSPATH' ) || exit;

final class ZombiePageDetector {

	private const CACHE_KEY = 'sda_zombies';
	private const CACHE_TTL = HOUR_IN_SECONDS;
	private const MAX_POSTS = 500;

	/** Pages younger than this are never pruned — give them time to rank. */
	private const GRACE_DAYS = 90;

	/** Below this word count a page is "thin". */
	private const THIN_WORDS = 300;

	public function __construct(
		private UrlCanonicalizer $canonicalizer,
		private PropertiesRepository $properties,
	) {}

	/**
	 * @return array{window_days: int, scanned: int, zombie_count: int, has_gsc: bool, pages: array<int, array<string, mixed>>}
	 */
	public function detect( int $window_days = self::GRACE_DAYS, bool $force = false ): array {
		$window_days = max( 30, min( 365, $window_days ) );
		$cache_key   = self::CACHE_KEY . '_' . $window_days;

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$metrics = $this->gsc_metrics( $window_days ); // hash_hex => [clicks, impressions, position]
		$has_gsc = null !== $metrics;
		$metrics ??= [];

		$posts   = $this->published_posts();
		$inbound = $this->inbound_link_map( $posts );

		$zombies = [];
		foreach ( $posts as $post ) {
			$canonical = $this->canonicalizer->canonicalize( (string) get_permalink( $post ) );
			$hash      = $this->canonicalizer->hash_hex( $canonical );
			$m         = $metrics[ $hash ] ?? [ 'clicks' => 0, 'impressions' => 0, 'position' => 0.0 ];

			// A page with clicks is doing its job — never a zombie.
			if ( $m['clicks'] > 0 ) {
				continue;
			}

			$words    = $this->word_count( (string) $post->post_content );
			$age_days = (int) ( ( time() - (int) get_post_timestamp( $post ) ) / DAY_IN_SECONDS );
			$in_links = (int) ( $inbound[ $hash ] ?? 0 );

			// When GSC is unconnected we can only flag content-side signals;
			// require a real content problem so we don't list healthy pages.
			if ( ! $has_gsc && $words >= self::THIN_WORDS && $in_links > 0 ) {
				continue;
			}

			$verdict = $this->classify( $m, $words, $age_days, $in_links, $has_gsc );

			$zombies[] = [
				'id'          => $post->ID,
				'title'       => (string) get_the_title( $post ),
				'url'         => (string) get_permalink( $post ),
				'edit_url'    => (string) get_edit_post_link( $post->ID, 'raw' ),
				'clicks'      => $m['clicks'],
				'impressions' => $m['impressions'],
				'position'    => round( (float) $m['position'], 1 ),
				'words'       => $words,
				'age_days'    => $age_days,
				'inbound'     => $in_links,
				'type'        => $verdict['type'],
				'action'      => $verdict['action'],
				'severity'    => $verdict['severity'],
				'reason'      => $verdict['reason'],
			];
		}

		// Worst first: high severity, then fewest impressions (deadest), then oldest.
		$rank = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
		usort(
			$zombies,
			static function ( array $a, array $b ) use ( $rank ): int {
				return [ $rank[ $a['severity'] ], $a['impressions'], -$a['age_days'] ]
					<=> [ $rank[ $b['severity'] ], $b['impressions'], -$b['age_days'] ];
			}
		);

		$result = [
			'window_days'  => $window_days,
			'scanned'      => count( $posts ),
			'zombie_count' => count( $zombies ),
			'has_gsc'      => $has_gsc,
			'pages'        => $zombies,
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * The content-audit decision tree. Returns type + recommended action +
	 * severity + a human reason.
	 *
	 * @param array{clicks: int, impressions: int, position: float} $m
	 * @return array{type: string, action: string, severity: string, reason: string}
	 */
	private function classify( array $m, int $words, int $age_days, int $inbound, bool $has_gsc ): array {
		// Young pages: never prune — they may not have had time to rank.
		if ( $age_days < self::GRACE_DAYS ) {
			return [
				'type'     => 'too_new',
				'action'   => 'wait',
				'severity' => 'low',
				'reason'   => __( 'Published recently — give it time to be indexed and ranked before acting.', 'seo-director-ai' ),
			];
		}

		// Orphan pages (no internal links in) are a strong, easy fix first.
		if ( 0 === $inbound ) {
			return [
				'type'     => 'orphan',
				'action'   => 'internal_link',
				'severity' => $words < self::THIN_WORDS ? 'high' : 'medium',
				'reason'   => __( 'Orphan page: no internal links point to it, so search engines and users can barely reach it. Link to it from related content first.', 'seo-director-ai' ),
			];
		}

		// Impressions but no clicks → it ranks but nobody clicks.
		if ( $m['impressions'] > 0 ) {
			if ( $m['position'] > 0 && $m['position'] <= 20 ) {
				return [
					'type'     => 'low_ctr',
					'action'   => 'improve_meta',
					'severity' => 'medium',
					'reason'   => __( 'Shows in results but gets no clicks — rewrite the title and meta description to match intent and stand out.', 'seo-director-ai' ),
				];
			}

			return [
				'type'     => 'low_ranking',
				'action'   => $words < self::THIN_WORDS ? 'prune' : 'improve',
				'severity' => 'medium',
				'reason'   => $words < self::THIN_WORDS
					? __( 'Ranks poorly (page 3+) with thin content — merge into a stronger page and 301-redirect, or improve substantially.', 'seo-director-ai' )
					: __( 'Ranks poorly (page 3+) despite decent length — refresh content, target a clearer keyword, and add internal links.', 'seo-director-ai' ),
			];
		}

		// No impressions at all → effectively invisible / not indexed.
		if ( $words < self::THIN_WORDS ) {
			return [
				'type'     => 'thin_invisible',
				'action'   => 'prune',
				'severity' => 'high',
				'reason'   => $has_gsc
					? __( 'Thin and invisible in search (no impressions). Best fix: merge into a related page and 301-redirect, or delete and return 410.', 'seo-director-ai' )
					: __( 'Thin content with no internal links. Likely a zombie: merge into a related page and 301-redirect, or delete.', 'seo-director-ai' ),
			];
		}

		return [
			'type'     => 'invisible',
			'action'   => 'improve',
			'severity' => 'medium',
			'reason'   => __( 'Has substance but earns no impressions — it may target no real query or be a near-duplicate. Re-target a keyword with demand, or merge into a stronger page.', 'seo-director-ai' ),
		];
	}

	/**
	 * Summed page metrics over the window, keyed by hex page hash. Null when
	 * no GSC property is selected (so callers know traffic data is absent).
	 *
	 * @return array<string, array{clicks: int, impressions: int, position: float}>|null
	 */
	private function gsc_metrics( int $window_days ): ?array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return null;
		}

		global $wpdb;
		$table = Schema::table( 'gsc_page_daily' );
		$since = gmdate( 'Y-m-d', strtotime( '-' . $window_days . ' days' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT LOWER(HEX(page_hash)) AS h, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position
				FROM {$table} WHERE property_id = %d AND date >= %s GROUP BY page_hash", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property['id'],
				$since
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (string) $row['h'] ] = [
				'clicks'      => (int) $row['clicks'],
				'impressions' => (int) $row['impressions'],
				'position'    => (float) $row['position'],
			];
		}

		return $map;
	}

	/**
	 * @return \WP_Post[]
	 */
	private function published_posts(): array {
		return get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_POSTS,
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);
	}

	/**
	 * One pass over all posts building inbound internal-link counts keyed by
	 * the target page's hex hash.
	 *
	 * @param \WP_Post[] $posts
	 * @return array<string, int>
	 */
	private function inbound_link_map( array $posts ): array {
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$inbound = [];

		foreach ( $posts as $post ) {
			if ( ! preg_match_all( '/href=["\']([^"\']+)["\']/i', (string) $post->post_content, $matches ) ) {
				continue;
			}
			$seen = [];
			foreach ( $matches[1] as $url ) {
				$url        = (string) $url;
				$is_relative = str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' );
				$is_internal = $is_relative || ( '' !== $host && str_contains( $url, $host ) );
				if ( ! $is_internal ) {
					continue;
				}
				$hash = $this->canonicalizer->hash_hex( $this->canonicalizer->canonicalize( $url ) );
				// Count each target at most once per source post.
				if ( isset( $seen[ $hash ] ) ) {
					continue;
				}
				$seen[ $hash ]      = true;
				$inbound[ $hash ]   = ( $inbound[ $hash ] ?? 0 ) + 1;
			}
		}

		return $inbound;
	}

	private function word_count( string $content ): int {
		$tokens = preg_split( '/\s+/u', trim( wp_strip_all_tags( $content ) ) );

		return is_array( $tokens ) ? count( array_filter( $tokens, static fn( $t ) => '' !== $t ) ) : 0;
	}
}

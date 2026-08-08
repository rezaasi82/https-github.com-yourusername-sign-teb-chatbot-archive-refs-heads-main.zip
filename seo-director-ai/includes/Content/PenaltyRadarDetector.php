<?php
/**
 * Penalty Radar. Google never exposes a "this page is penalized" flag through
 * the public API — manual actions live only inside Search Console — so a true
 * penalty can't be read programmatically. What CAN be read is the fingerprint
 * a penalty (or an algorithmic core-update suppression) leaves in the traffic
 * data: a page that had real Search visibility suddenly loses it.
 *
 * This detector crosses each page's DAILY Search Console history against the
 * ChangepointDetector to find the strongest sustained drop, classifies the
 * fingerprint (deindexed / traffic cliff / ranking collapse / CTR collapse /
 * soft decline), decides whether the drop was page-specific or sitewide, and
 * flags whether the drop lines up with a known Google update window. Every
 * flagged page carries a plain-language reason, a recommended next step, and a
 * confidence — it is a lead to investigate, never a verdict. Deterministic, no
 * AI. Cached for an hour.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Core\Schema;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\UrlCanonicalizer;

defined( 'ABSPATH' ) || exit;

final class PenaltyRadarDetector {

	private const CACHE_KEY = 'sda_penalties';
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/** A page needs at least this many impressions over the window to be judged — you can't lose visibility you never had. */
	private const MIN_IMPRESSIONS = 50;

	/** "Recent" tail used to spot a page whose traffic has gone to zero. */
	private const RECENT_DAYS = 21;

	/** A drop this deep (fraction) at the changepoint is a hard cliff. */
	private const CLIFF_DROP = 0.70;

	/** A shallower but still notable sustained drop. */
	private const SOFT_DROP = 0.45;

	/** How close (days) a page drop must be to the sitewide drop to be called sitewide. */
	private const SITEWIDE_TOLERANCE = 7;

	private const MAX_PAGES = 400;

	public function __construct(
		private UrlCanonicalizer $canonicalizer,
		private PropertiesRepository $properties,
		private ChangepointDetector $changepoint,
	) {}

	/**
	 * @return array{window_days: int, has_gsc: bool, scanned: int, flagged_count: int, manual_actions_url: string, sitewide: array<string, mixed>|null, pages: array<int, array<string, mixed>>}
	 */
	public function detect( int $window_days = 180, bool $force = false ): array {
		$window_days = max( 60, min( 365, $window_days ) );
		$cache_key   = self::CACHE_KEY . '_' . $window_days;

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			$empty = [
				'window_days'        => $window_days,
				'has_gsc'            => false,
				'scanned'            => 0,
				'flagged_count'      => 0,
				'manual_actions_url' => '',
				'sitewide'           => null,
				'pages'              => [],
			];
			set_transient( $cache_key, $empty, self::CACHE_TTL );
			return $empty;
		}

		$dates    = $this->date_axis( $window_days );
		$series   = $this->page_series( (int) $property['id'], $dates ); // hash => ['path'=>, 'clicks'=>[date=>n], 'impr'=>[...], 'pos'=>[date=>n]]
		$sitewide = $this->sitewide_drop( (int) $property['id'], $dates );
		$updates  = $this->update_windows();
		$post_map = $this->post_hash_map();

		$flagged = [];
		foreach ( $series as $hash => $page ) {
			$total_impr = array_sum( $page['impr'] );
			if ( $total_impr < self::MIN_IMPRESSIONS ) {
				continue; // Never had enough visibility to lose.
			}

			$verdict = $this->classify( $page, $dates );
			if ( null === $verdict ) {
				continue; // No penalty-like fingerprint.
			}

			$scope = $this->scope( $verdict['date'], $sitewide );
			$update = $this->aligned_update( $verdict['date'], $updates );
			$post   = $post_map[ $hash ] ?? null;
			$url    = home_url( $page['path'] );

			$flagged[] = [
				'url'         => $url,
				'title'       => $post['title'] ?? $page['path'],
				'edit_url'    => $post['edit_url'] ?? '',
				'type'        => $verdict['type'],
				'severity'    => $verdict['severity'],
				'action'      => $verdict['action'],
				'reason'      => $verdict['reason'],
				'drop_date'   => $verdict['date'],
				'drop_pct'    => $verdict['drop_pct'],
				'before'      => $verdict['before'],
				'after'       => $verdict['after'],
				'pos_before'  => $verdict['pos_before'],
				'pos_after'   => $verdict['pos_after'],
				'scope'       => $scope,
				'aligned'     => $update,
				'confidence'  => $this->confidence( $verdict['severity'], $scope, null !== $update ),
			];
		}

		// Worst first: severity, then deepest drop.
		$rank = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
		usort(
			$flagged,
			static fn( array $a, array $b ): int =>
				[ $rank[ $a['severity'] ], $a['drop_pct'] ] <=> [ $rank[ $b['severity'] ], $b['drop_pct'] ]
		);

		$result = [
			'window_days'        => $window_days,
			'has_gsc'            => true,
			'scanned'            => count( $series ),
			'flagged_count'      => count( $flagged ),
			'manual_actions_url' => $this->manual_actions_url( (string) $property['external_id'] ),
			'sitewide'           => $sitewide,
			'pages'              => $flagged,
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * The penalty-fingerprint decision tree. Returns null when the page shows no
	 * sustained drop worth surfacing.
	 *
	 * @param array{path: string, clicks: array<string, float>, impr: array<string, float>, pos: array<string, float>} $page
	 * @param string[] $dates
	 * @return array{type: string, severity: string, action: string, reason: string, date: string, drop_pct: int, before: int, after: int, pos_before: float, pos_after: float}|null
	 */
	private function classify( array $page, array $dates ): ?array {
		$cp = $this->changepoint->detect( $page['clicks'] );

		// Split point for position/impression comparison: the changepoint date,
		// else the midpoint of the window.
		$split_date = $cp['date'] ?? $dates[ (int) floor( count( $dates ) / 2 ) ];

		$before = $this->mean_before( $page['clicks'], $split_date );
		$after  = $this->mean_from( $page['clicks'], $split_date );
		$recent_impr = $this->sum_tail( $page['impr'], self::RECENT_DAYS );
		$early_impr  = $this->mean_before( $page['impr'], $split_date );

		$pos_before = $this->mean_before( $page['pos'], $split_date, true );
		$pos_after  = $this->mean_from( $page['pos'], $split_date, true );

		$drop = $before > 0 ? ( $before - $after ) / $before : 0.0;

		// 1) Deindexed / fully suppressed: it used to show in Search, now it earns
		// no impressions at all in the recent tail. Strongest signal we have.
		if ( $early_impr > 0 && 0.0 === $recent_impr && array_sum( $page['impr'] ) >= self::MIN_IMPRESSIONS ) {
			return [
				'type'       => 'deindexed',
				'severity'   => 'high',
				'action'     => 'check_index',
				'reason'     => __( 'The page used to appear in Search but now earns zero impressions — it may be deindexed, hit by a manual action, blocked by robots/noindex, or fully suppressed. Check Coverage and Manual Actions in Search Console first.', 'seo-director-ai' ),
				'date'       => $split_date,
				'drop_pct'   => -100,
				'before'     => (int) round( $before ),
				'after'      => (int) round( $after ),
				'pos_before' => round( $pos_before, 1 ),
				'pos_after'  => round( $pos_after, 1 ),
			];
		}

		// Everything below needs a real, sustained click drop at a changepoint.
		if ( null === $cp || $drop < self::SOFT_DROP ) {
			return null;
		}

		$pos_collapsed = $pos_before > 0 && $pos_before <= 10 && $pos_after >= 20;
		$impr_held     = $early_impr > 0 && $this->mean_from( $page['impr'], $split_date ) >= 0.6 * $early_impr;

		// 2) Ranking collapse: was on page 1, fell to page 3+ — classic core-update / quality hit.
		if ( $pos_collapsed ) {
			return $this->row( 'ranking_collapse', 'high', 'investigate_quality',
				__( 'Average position collapsed from page 1 to page 3 or worse and clicks fell with it — typical of an algorithmic (core-update) quality reassessment. Audit content quality, E-E-A-T, and intent match against what now outranks you.', 'seo-director-ai' ),
				$split_date, $before, $after, $pos_before, $pos_after );
		}

		// 3) CTR collapse: still shown as often, but clicks evaporated — lost a SERP
		// feature, a title/snippet rewrite by Google, or a targeted suppression.
		if ( $impr_held && $drop >= self::CLIFF_DROP ) {
			return $this->row( 'ctr_collapse', 'medium', 'rewrite_meta',
				__( 'Impressions held but clicks collapsed — the page still ranks yet nobody clicks. It likely lost a rich result / SERP feature or Google is rewriting your title. Rewrite the title & meta to match intent and win the click back.', 'seo-director-ai' ),
				$split_date, $before, $after, $pos_before, $pos_after );
		}

		// 4) Hard traffic cliff (impressions fell too): sharp, sustained loss.
		if ( $drop >= self::CLIFF_DROP ) {
			return $this->row( 'traffic_cliff', 'high', 'investigate_quality',
				__( 'Clicks fell off a cliff and stayed down — a sharp, sustained loss of Search traffic. Check whether the drop lines up with a Google update, a site migration/redirect, or a manual action, then audit the page against the current top results.', 'seo-director-ai' ),
				$split_date, $before, $after, $pos_before, $pos_after );
		}

		// 5) Softer sustained decline — worth watching, not alarming.
		return $this->row( 'soft_decline', 'medium', 'refresh_content',
			__( 'A sustained decline in Search traffic (not a full cliff). Often decay or rising competition rather than a penalty. Refresh the content, update facts, and strengthen internal links before it slides further.', 'seo-director-ai' ),
			$split_date, $before, $after, $pos_before, $pos_after );
	}

	/**
	 * @return array{type: string, severity: string, action: string, reason: string, date: string, drop_pct: int, before: int, after: int, pos_before: float, pos_after: float}
	 */
	private function row( string $type, string $severity, string $action, string $reason, string $date, float $before, float $after, float $pos_before, float $pos_after ): array {
		return [
			'type'       => $type,
			'severity'   => $severity,
			'action'     => $action,
			'reason'     => $reason,
			'date'       => $date,
			'drop_pct'   => (int) round( $before > 0 ? -100 * ( $before - $after ) / $before : 0 ),
			'before'     => (int) round( $before ),
			'after'      => (int) round( $after ),
			'pos_before' => round( $pos_before, 1 ),
			'pos_after'  => round( $pos_after, 1 ),
		];
	}

	/**
	 * Sitewide changepoint on total clicks — context so a page drop that lines up
	 * with a whole-site drop is labelled sitewide (core update / sitewide action)
	 * rather than a page-specific issue.
	 *
	 * @param string[] $dates
	 * @return array{date: string, drop_pct: int}|null
	 */
	private function sitewide_drop( int $property_id, array $dates ): ?array {
		global $wpdb;
		$table = Schema::table( 'gsc_daily_totals' );
		$since = $dates[0];

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT date, clicks FROM {$table} WHERE property_id = %d AND date >= %s ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$since
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || count( $rows ) < 14 ) {
			return null;
		}

		$series = [];
		foreach ( $dates as $d ) {
			$series[ $d ] = 0.0;
		}
		foreach ( $rows as $row ) {
			$series[ (string) $row['date'] ] = (float) $row['clicks'];
		}

		$cp = $this->changepoint->detect( $series );
		if ( null === $cp || null === $cp['change_pct'] || $cp['change_pct'] >= -25 ) {
			return null; // Only a meaningful sitewide drop is worth reporting.
		}

		return [ 'date' => $cp['date'], 'drop_pct' => (int) round( $cp['change_pct'] ) ];
	}

	private function scope( string $date, ?array $sitewide ): string {
		if ( null === $sitewide ) {
			return 'page';
		}
		$delta = abs( ( strtotime( $date ) - strtotime( $sitewide['date'] ) ) / DAY_IN_SECONDS );

		return $delta <= self::SITEWIDE_TOLERANCE ? 'sitewide' : 'page';
	}

	/**
	 * @param array<int, array{label: string, start: string, end: string}> $updates
	 * @return array{label: string}|null
	 */
	private function aligned_update( string $date, array $updates ): ?array {
		$ts = strtotime( $date );
		foreach ( $updates as $u ) {
			// A drop can register a few days after a rollout starts.
			if ( $ts >= strtotime( $u['start'] ) - 3 * DAY_IN_SECONDS && $ts <= strtotime( $u['end'] ) + 10 * DAY_IN_SECONDS ) {
				return [ 'label' => $u['label'] ];
			}
		}

		return null;
	}

	private function confidence( string $severity, string $scope, bool $aligned ): string {
		if ( 'high' === $severity && $aligned ) {
			return 'high';
		}
		if ( 'high' === $severity || $aligned ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Known Google broad-core / spam / helpful-content update windows. Filterable
	 * so it can be extended without a code change as new updates roll out.
	 *
	 * @return array<int, array{label: string, start: string, end: string}>
	 */
	private function update_windows(): array {
		$windows = [
			[ 'label' => 'March 2024 core update',        'start' => '2024-03-05', 'end' => '2024-04-19' ],
			[ 'label' => 'March 2024 spam update',        'start' => '2024-03-05', 'end' => '2024-03-20' ],
			[ 'label' => 'August 2024 core update',       'start' => '2024-08-15', 'end' => '2024-09-03' ],
			[ 'label' => 'November 2024 core update',     'start' => '2024-11-11', 'end' => '2024-12-05' ],
			[ 'label' => 'December 2024 core update',     'start' => '2024-12-12', 'end' => '2024-12-18' ],
			[ 'label' => 'December 2024 spam update',     'start' => '2024-12-19', 'end' => '2024-12-26' ],
			[ 'label' => 'March 2025 core update',        'start' => '2025-03-13', 'end' => '2025-03-27' ],
			[ 'label' => 'June 2025 core update',         'start' => '2025-06-30', 'end' => '2025-07-17' ],
			[ 'label' => 'August 2025 spam update',       'start' => '2025-08-26', 'end' => '2025-09-22' ],
			[ 'label' => 'November 2025 core update',     'start' => '2025-11-12', 'end' => '2025-12-05' ],
		];

		/**
		 * Filter the Google update windows Penalty Radar aligns drops against.
		 *
		 * @param array<int, array{label: string, start: string, end: string}> $windows
		 */
		return (array) apply_filters( 'sda_google_update_windows', $windows );
	}

	private function manual_actions_url( string $external_id ): string {
		return 'https://search.google.com/search-console/manual-actions?resource_id=' . rawurlencode( $external_id );
	}

	/**
	 * Daily per-page series over the window. Missing days are treated as zero
	 * clicks/impressions (GSC omits zero-traffic days); position is kept only for
	 * days that actually have data.
	 *
	 * @param string[] $dates
	 * @return array<string, array{path: string, clicks: array<string, float>, impr: array<string, float>, pos: array<string, float>}>
	 */
	private function page_series( int $property_id, array $dates ): array {
		global $wpdb;
		$table = Schema::table( 'gsc_page_daily' );
		$since = $dates[0];

		// Restrict to pages that carried real visibility, newest activity first.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT LOWER(HEX(page_hash)) AS h, page_path, date, clicks, impressions, position
				FROM {$table}
				WHERE property_id = %d AND date >= %s
				  AND page_hash IN (
					SELECT page_hash FROM {$table}
					WHERE property_id = %d AND date >= %s
					GROUP BY page_hash HAVING SUM(impressions) >= %d
					ORDER BY SUM(impressions) DESC LIMIT %d
				  )
				ORDER BY page_hash, date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$since,
				$property_id,
				$since,
				self::MIN_IMPRESSIONS,
				self::MAX_PAGES
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$zero = array_fill_keys( $dates, 0.0 );
		$out  = [];
		foreach ( $rows as $row ) {
			$h = (string) $row['h'];
			if ( ! isset( $out[ $h ] ) ) {
				$out[ $h ] = [ 'path' => (string) $row['page_path'], 'clicks' => $zero, 'impr' => $zero, 'pos' => [] ];
			}
			$d = (string) $row['date'];
			if ( ! isset( $out[ $h ]['clicks'][ $d ] ) ) {
				continue; // Outside the fixed axis (shouldn't happen).
			}
			$out[ $h ]['clicks'][ $d ] = (float) $row['clicks'];
			$out[ $h ]['impr'][ $d ]   = (float) $row['impressions'];
			$out[ $h ]['pos'][ $d ]    = (float) $row['position'];
		}

		return $out;
	}

	/**
	 * Continuous ascending date axis (Y-m-d) covering the window, so every page
	 * shares the same time base and missing days become explicit zeros.
	 *
	 * @return string[]
	 */
	private function date_axis( int $window_days ): array {
		$dates = [];
		$start = strtotime( '-' . $window_days . ' days' );
		for ( $i = 0; $i <= $window_days; $i++ ) {
			$dates[] = gmdate( 'Y-m-d', $start + $i * DAY_IN_SECONDS );
		}

		return $dates;
	}

	/**
	 * Map each published post's canonical page-hash to its title + edit link, so
	 * flagged rows can show a friendly title and an Edit action when the URL is a
	 * WordPress post (ranked non-post URLs simply show their path).
	 *
	 * @return array<string, array{title: string, edit_url: string}>
	 */
	private function post_hash_map(): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 1000,
				'fields'         => 'ids',
			]
		);

		$map = [];
		foreach ( $posts as $id ) {
			$hash = $this->canonicalizer->hash_hex( $this->canonicalizer->canonicalize( (string) get_permalink( $id ) ) );
			$map[ $hash ] = [
				'title'    => (string) get_the_title( $id ),
				'edit_url' => (string) get_edit_post_link( $id, 'raw' ),
			];
		}

		return $map;
	}

	/** Mean of the series strictly before a split date. When $positive, ignore zero/absent entries. */
	private function mean_before( array $series, string $split, bool $positive = false ): float {
		return $this->mean_slice( $series, $split, true, $positive );
	}

	/** Mean of the series from the split date onward. */
	private function mean_from( array $series, string $split, bool $positive = false ): float {
		return $this->mean_slice( $series, $split, false, $positive );
	}

	private function mean_slice( array $series, string $split, bool $before, bool $positive ): float {
		$vals = [];
		foreach ( $series as $date => $value ) {
			$is_before = strcmp( (string) $date, $split ) < 0;
			if ( $is_before !== $before ) {
				continue;
			}
			$value = (float) $value;
			if ( $positive && $value <= 0.0 ) {
				continue;
			}
			$vals[] = $value;
		}

		return count( $vals ) > 0 ? array_sum( $vals ) / count( $vals ) : 0.0;
	}

	private function sum_tail( array $series, int $days ): float {
		return array_sum( array_slice( $series, -$days, null, true ) );
	}
}

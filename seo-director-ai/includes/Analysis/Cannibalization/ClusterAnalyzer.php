<?php
/**
 * Detects keyword cannibalization: queries for which two or more of the
 * site's own URLs both accumulate meaningful impressions in the same week,
 * splitting authority. Reads the page×query weekly sample and writes
 * 'cannibalization' opportunities keyed by query.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\Cannibalization;

use SEODirector\Core\Schema;
use SEODirector\Data\Repository\OpportunitiesRepository;

defined( 'ABSPATH' ) || exit;

final class ClusterAnalyzer {

	private const MIN_IMPRESSIONS_PER_PAGE = 30;
	private const MAX_CLUSTERS             = 50;

	public function __construct( private OpportunitiesRepository $opportunities ) {}

	/**
	 * Scan the most recent complete week and persist cannibalization findings.
	 */
	public function scan( int $property_id ): void {
		global $wpdb;

		$table      = Schema::table( 'gsc_page_query_weekly' );
		$week_start = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT MAX(week_start) FROM {$table} WHERE property_id = %d", $property_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $week_start ) {
			$this->opportunities->sync_detector( 'cannibalization', [] );
			return;
		}

		// Queries with 2+ pages each above the impression floor.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT HEX(query_hash) AS query_hash, MAX(query) AS query,
					COUNT(DISTINCT page_hash) AS page_count,
					SUM(impressions) AS impressions,
					SUM(clicks) AS clicks
				FROM {$table}
				WHERE property_id = %d AND week_start = %s AND impressions >= %d
				GROUP BY query_hash
				HAVING page_count >= 2
				ORDER BY impressions DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$week_start,
				self::MIN_IMPRESSIONS_PER_PAGE,
				self::MAX_CLUSTERS
			),
			ARRAY_A
		);

		$findings = [];
		foreach ( $rows ?: [] as $row ) {
			$pages = $this->competing_pages( $table, $property_id, (string) $week_start, (string) $row['query_hash'] );
			if ( count( $pages ) < 2 ) {
				continue;
			}

			$findings[] = [
				'entity_type'      => 'query',
				'hash'             => strtolower( (string) $row['query_hash'] ),
				'label'            => (string) $row['query'],
				'secondary_label'  => sprintf( '%d competing URLs', count( $pages ) ),
				'score'            => round( (int) $row['impressions'] / 10, 2 ),
				'est_traffic_gain' => (int) round( (int) $row['clicks'] * 0.3 ),
				'difficulty'       => 6,
				'data'             => [
					'page_count'  => count( $pages ),
					'pages'       => array_slice( $pages, 0, 5 ),
					'impressions' => (int) $row['impressions'],
				],
			];
		}

		$this->opportunities->sync_detector( 'cannibalization', $findings );
	}

	/**
	 * @return array<int, array{page: string, impressions: int, position: float}>
	 */
	private function competing_pages( string $table, int $property_id, string $week_start, string $query_hash_hex ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT page_path, impressions, position FROM {$table}
				WHERE property_id = %d AND week_start = %s AND query_hash = UNHEX(%s) AND impressions >= %d
				ORDER BY impressions DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$week_start,
				$query_hash_hex,
				self::MIN_IMPRESSIONS_PER_PAGE
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $r ) => [
				'page'        => (string) $r['page_path'],
				'impressions' => (int) $r['impressions'],
				'position'    => round( (float) $r['position'], 1 ),
			],
			$rows ?: []
		);
	}
}

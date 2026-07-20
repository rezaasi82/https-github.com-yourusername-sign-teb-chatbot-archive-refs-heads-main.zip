<?php
/**
 * Competitor intelligence (PRO, needs a SerpApi key). Two views:
 *
 *  - overview(): fetches the live SERP for the site's top GSC queries and
 *    aggregates which domains keep out-ranking this site — appearances,
 *    average position, and sample queries per competitor.
 *  - query(): the full organic top-10 for one query, with this site's row
 *    highlighted.
 *
 * Cost control: the overview samples at most 10 queries per run and every
 * SERP fetch is cached 24h in SerpClient, so a full overview costs at most
 * 10 SerpApi searches per day.
 *
 * @package SEODirector
 */

namespace SEODirector\Research;

use SEODirector\Core\Schema;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class CompetitorAnalyzer {

	private const SAMPLE_QUERIES = 10;

	public function __construct(
		private SerpClient $serp,
		private PropertiesRepository $properties,
	) {}

	public function is_configured(): bool {
		return $this->serp->is_configured();
	}

	/**
	 * @return array{competitors: array<int, array{domain: string, appearances: int, avg_position: float, sample_queries: string[]}>, queries_checked: int, our_domain: string}|\WP_Error
	 */
	public function overview(): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'sda_serp_config', __( 'A SerpApi key is required — add it in Settings.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$queries = $this->top_queries();
		if ( [] === $queries ) {
			return new \WP_Error( 'sda_no_data', __( 'No Search Console query data yet — connect Google and sync first.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$our_domain = $this->our_domain();
		$stats      = [];
		$checked    = 0;

		foreach ( $queries as $query ) {
			$serp = $this->serp->search( $query );
			if ( null === $serp ) {
				continue;
			}
			$checked++;

			// Everyone ranking above our first appearance (or the whole top 10
			// when we are absent) is a competitor for this query.
			$our_position = null;
			foreach ( $serp['organic'] as $row ) {
				if ( $row['domain'] === $our_domain ) {
					$our_position = $row['position'];
					break;
				}
			}

			foreach ( $serp['organic'] as $row ) {
				if ( '' === $row['domain'] || $row['domain'] === $our_domain ) {
					continue;
				}
				if ( null !== $our_position && $row['position'] >= $our_position ) {
					continue;
				}

				$domain = $row['domain'];
				$stats[ $domain ] ??= [ 'appearances' => 0, 'position_sum' => 0, 'queries' => [] ];
				$stats[ $domain ]['appearances']++;
				$stats[ $domain ]['position_sum'] += $row['position'];
				if ( count( $stats[ $domain ]['queries'] ) < 3 && ! in_array( $query, $stats[ $domain ]['queries'], true ) ) {
					$stats[ $domain ]['queries'][] = $query;
				}
			}
		}

		$competitors = [];
		foreach ( $stats as $domain => $s ) {
			$competitors[] = [
				'domain'         => $domain,
				'appearances'    => $s['appearances'],
				'avg_position'   => round( $s['position_sum'] / max( 1, $s['appearances'] ), 1 ),
				'sample_queries' => $s['queries'],
			];
		}
		usort( $competitors, static fn( array $a, array $b ) => $b['appearances'] <=> $a['appearances'] );

		return [
			'competitors'     => array_slice( $competitors, 0, 20 ),
			'queries_checked' => $checked,
			'our_domain'      => $our_domain,
		];
	}

	/**
	 * @return array{query: string, our_domain: string, our_position: int|null, results: array<int, array{position: int, title: string, link: string, domain: string, is_us: bool}>}|\WP_Error
	 */
	public function query( string $query ): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'sda_serp_config', __( 'A SerpApi key is required — add it in Settings.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$serp = $this->serp->search( trim( $query ) );
		if ( null === $serp ) {
			return new \WP_Error( 'sda_serp_api', __( 'SERP lookup failed — check the SerpApi key and quota.', 'seo-director-ai' ), [ 'status' => 502 ] );
		}

		$our_domain   = $this->our_domain();
		$our_position = null;
		$results      = [];

		foreach ( $serp['organic'] as $row ) {
			$is_us = $row['domain'] === $our_domain;
			if ( $is_us && null === $our_position ) {
				$our_position = $row['position'];
			}
			$results[] = array_merge( $row, [ 'is_us' => $is_us ] );
		}

		return [
			'query'        => trim( $query ),
			'our_domain'   => $our_domain,
			'our_position' => $our_position,
			'results'      => $results,
		];
	}

	/** Top queries by impressions from the latest GSC week. */
	private function top_queries(): array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return [];
		}

		global $wpdb;
		$table = Schema::table( 'gsc_query_weekly' );
		$week  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT MAX(week_start) FROM {$table} WHERE property_id = %d", $property['id'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( ! $week ) {
			return [];
		}

		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT query FROM {$table}
				WHERE property_id = %d AND week_start = %s
				ORDER BY impressions DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property['id'],
				$week,
				self::SAMPLE_QUERIES
			)
		);

		return array_map( 'strval', $rows ?: [] );
	}

	private function our_domain(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return preg_replace( '/^www\./', '', $host ) ?? $host;
	}
}

<?php
/**
 * The analysis pass, run after every sync and on the hourly alert schedule:
 *   phase 1 "opportunities" — run detectors over 28-day aggregates, persist
 *   phase 2 "health"        — compute + store today's health score
 *   phase 3 "alerts"        — evaluate alert rules
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Alerts\AlertEngine;
use SEODirector\Analysis\HealthScore\HealthScoreCalculator;
use SEODirector\Analysis\OpportunityDetector\OpportunityDetectorInterface;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Core\Schema;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Jobs\AbstractChunkedJob;

defined( 'ABSPATH' ) || exit;

final class RunAnalysisJob extends AbstractChunkedJob {

	public const NAME = 'run_analysis';

	/**
	 * @param OpportunityDetectorInterface[] $detectors
	 */
	public function __construct(
		JobStateRepository $state,
		private array $detectors,
		private MoversRepository $movers,
		private OpportunitiesRepository $opportunities,
		private HealthScoreCalculator $health_calculator,
		private HealthScoreRepository $health_scores,
		private GscRepository $gsc,
		private PropertiesRepository $properties,
		private TrendAnalyzer $trend,
		private AlertEngine $alert_engine,
	) {
		parent::__construct( $state );
	}

	public function name(): string {
		return self::NAME;
	}

	protected function process_chunk( array $cursor ): ?array {
		$phase = (string) ( $cursor['phase'] ?? 'opportunities' );

		match ( $phase ) {
			'opportunities' => $this->run_opportunities(),
			'health'        => $this->run_health(),
			default         => $this->alert_engine->evaluate(),
		};

		return match ( $phase ) {
			'opportunities' => [ 'phase' => 'health' ],
			'health'        => [ 'phase' => 'alerts' ],
			default         => null,
		};
	}

	private function run_opportunities(): void {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return;
		}

		[ $cur_from, $cur_to, $prev_from, $prev_to ] = $this->windows();

		$query_rows = $this->movers->period_aggregates( 'query', $property['id'], $cur_from, $cur_to, $prev_from, $prev_to, 1000 );
		$page_rows  = $this->movers->period_aggregates( 'page', $property['id'], $cur_from, $cur_to, $prev_from, $prev_to, 1000 );

		/**
		 * Filters the opportunity detectors to run (extension point).
		 *
		 * @param OpportunityDetectorInterface[] $detectors
		 */
		$detectors = apply_filters( 'sda_opportunity_detectors', $this->detectors );

		foreach ( $detectors as $detector ) {
			$input    = 'low_ctr' === $detector->slug() ? $page_rows : $query_rows;
			$findings = $detector->detect( $input );
			$this->opportunities->sync_detector( $detector->slug(), $findings );
		}
	}

	private function run_health(): void {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return;
		}

		$to     = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$from   = gmdate( 'Y-m-d', strtotime( $to . ' -27 days' ) );
		$series = $this->gsc->daily_totals_series( $property['id'], $from, $to );

		if ( [] === $series ) {
			return;
		}

		$clicks      = array_map( static fn( $d ) => (float) $d['clicks'], $series );
		$impressions = array_sum( array_column( $series, 'impressions' ) );
		$total_click = array_sum( array_column( $series, 'clicks' ) );

		$weighted_position = 0.0;
		foreach ( $series as $day ) {
			$weighted_position += $day['position'] * $day['impressions'];
		}
		$avg_position = $impressions > 0 ? $weighted_position / $impressions : null;

		$result = $this->health_calculator->calculate(
			[
				'avg_position'          => $avg_position,
				'ctr'                   => $impressions > 0 ? $total_click / $impressions : null,
				'cwv_status_counts'     => $this->cwv_counts(),
				'indexation_ratio'      => null, // Coverage ingestion arrives in a later phase.
				'freshness_ratio'       => null,
				'internal_link_ratio'   => null,
				'clicks_weekly_slope'   => $this->trend->weekly_relative_slope( $clicks ),
				'sessions_weekly_slope' => $this->sessions_slope(),
			]
		);

		$this->health_scores->put_today( $result['score'], $result['components'] );
	}

	/**
	 * @return array{good: int, needs_improvement: int, poor: int}|null
	 */
	private function cwv_counts(): ?array {
		global $wpdb;

		$table = Schema::table( 'psi_audits' );

		// Latest mobile audit per page within 45 days (mobile is what CWV ranks on).
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT cwv_status FROM {$table} p
				WHERE site_id = %d AND strategy = 'mobile' AND cwv_status != 'unknown'
					AND audited_at >= %s
					AND audited_at = (
						SELECT MAX(audited_at) FROM {$table}
						WHERE site_id = p.site_id AND page_hash = p.page_hash AND strategy = 'mobile'
					)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id(),
				gmdate( 'Y-m-d H:i:s', strtotime( '-45 days' ) )
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return null;
		}

		$counts = [ 'good' => 0, 'needs_improvement' => 0, 'poor' => 0 ];
		foreach ( $rows as $row ) {
			$status = (string) $row['cwv_status'];
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
		}

		return $counts;
	}

	private function sessions_slope(): ?float {
		global $wpdb;

		$property = $this->properties->active( 'ga4' );
		if ( null === $property ) {
			return null;
		}

		$table = Schema::table( 'ga4_daily_totals' );
		$rows  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT sessions FROM {$table} WHERE property_id = %d AND channel = 'Organic Search' AND date >= %s ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property['id'],
				gmdate( 'Y-m-d', strtotime( '-28 days' ) )
			)
		);

		if ( ! $rows || count( $rows ) < 14 ) {
			return null;
		}

		return $this->trend->weekly_relative_slope( array_map( 'floatval', $rows ) );
	}

	/**
	 * @return string[] [cur_from, cur_to, prev_from, prev_to]
	 */
	private function windows(): array {
		$cur_to    = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$cur_from  = gmdate( 'Y-m-d', strtotime( $cur_to . ' -27 days' ) );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $cur_from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -27 days' ) );

		return [ $cur_from, $cur_to, $prev_from, $prev_to ];
	}
}

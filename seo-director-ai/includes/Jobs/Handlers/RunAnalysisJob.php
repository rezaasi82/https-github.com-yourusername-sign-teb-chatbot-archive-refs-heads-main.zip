<?php
/**
 * Post-sync analysis pass: health score + opportunity detectors.
 * Single-chunk job (reads only local rollup tables — cheap).
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Analysis\HealthScore\HealthScoreCalculator;
use SEODirector\Analysis\OpportunityDetector\LowCtrDetector;
use SEODirector\Analysis\OpportunityDetector\StrikingDistanceDetector;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\GscQueryDailyRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Jobs\JobResult;

final class RunAnalysisJob {

	public const NAME = 'run_analysis';

	private const WINDOW_DAYS = 56; // 8 aligned weeks for WoW/MoM.

	public function __construct(
		private readonly GscDailyTotalsRepository $totals,
		private readonly GscQueryDailyRepository $queries,
		private readonly HealthScoreCalculator $health_calculator,
		private readonly HealthScoreRepository $health_scores,
		private readonly StrikingDistanceDetector $striking_distance,
		private readonly LowCtrDetector $low_ctr,
		private readonly OpportunitiesRepository $opportunities,
	) {}

	public function run_chunk(): JobResult {
		$property_id = $this->active_property_id();
		if ( null === $property_id ) {
			return JobResult::done();
		}

		$to     = gmdate( 'Y-m-d' );
		$from   = gmdate( 'Y-m-d', strtotime( '-' . self::WINDOW_DAYS . ' days' ) );
		$series = $this->totals->series( $property_id, $from, $to );
		if ( empty( $series ) ) {
			return JobResult::done();
		}

		$this->compute_health_score( $property_id, $series );
		$this->run_detectors( $property_id );

		return JobResult::done();
	}

	/** @param array<int, array<string, mixed>> $series */
	private function compute_health_score( int $property_id, array $series ): void {
		$trend       = new TrendAnalyzer();
		$clicks      = array_map( static fn( $r ) => (float) $r['clicks'], $series );
		$impressions = array_map( static fn( $r ) => (float) $r['impressions'], $series );
		$last        = end( $series );
		$latest_date = $this->totals->latest_date( $property_id );
		$days_stale  = $latest_date ? (int) floor( ( time() - strtotime( $latest_date ) ) / DAY_IN_SECONDS ) : 99;

		// GSC data always lags ~2 days; don't punish for that.
		$days_stale = max( 0, $days_stale - 2 );

		$recent_ctr      = $trend->moving_average( array_map( static fn( $r ) => (float) $r['ctr'], $series ), 7 );
		$recent_position = $trend->moving_average( array_map( static fn( $r ) => (float) $r['position'], $series ), 7 );

		$result = $this->health_calculator->calculate(
			array(
				'clicks_wow'      => $trend->wow( $clicks ),
				'clicks_mom'      => $trend->mom( $clicks ),
				'impressions_wow' => $trend->wow( $impressions ),
				'ctr'             => $recent_ctr,
				'position'        => $recent_position,
				'days_since_sync' => $days_stale,
			)
		);

		$this->health_scores->save( (string) $last['date'], $result['score'], $result['components'] );
	}

	private function run_detectors( int $property_id ): void {
		$to    = gmdate( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( '-28 days' ) );
		$stats = $this->queries->aggregate_window( $property_id, $from, $to );
		if ( empty( $stats ) ) {
			return;
		}

		$this->opportunities->sync_detector_results( StrikingDistanceDetector::SLUG, $this->striking_distance->detect( $stats ) );
		$this->opportunities->sync_detector_results( LowCtrDetector::SLUG, $this->low_ctr->detect( $stats ) );
	}

	private function active_property_id(): ?int {
		global $wpdb;
		$table = $wpdb->prefix . 'sda_properties';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE site_id = %d AND service = 'gsc' AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			)
		);
		return null === $id ? null : (int) $id;
	}
}

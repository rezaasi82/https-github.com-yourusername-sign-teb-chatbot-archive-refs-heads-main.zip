<?php
/**
 * Assembles the data structure for a report from local tables: health score,
 * traffic totals, top movers, opportunities, and active alerts for a period.
 * Renderer-agnostic — CSV/HTML/future renderers consume this shape.
 *
 * @package SEODirector
 */

namespace SEODirector\Reports;

use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\InsightRepository;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class ReportBuilder {

	public function __construct(
		private PropertiesRepository $properties,
		private GscRepository $gsc,
		private HealthScoreRepository $health,
		private MoversRepository $movers,
		private OpportunitiesRepository $opportunities,
		private AlertsRepository $alerts,
		private InsightRepository $insights,
		private GrowthDetector $growth,
		private DeclineDetector $decline,
	) {}

	/**
	 * @param 'weekly'|'monthly'|'quarterly' $type
	 * @return array<string, mixed>
	 */
	public function build( string $type ): array {
		$days     = match ( $type ) { 'weekly' => 7, 'quarterly' => 90, default => 30 };
		$to       = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$from     = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $days - 1 ) . ' days' ) );
		$property = $this->properties->active( 'gsc' );

		$series = null !== $property ? $this->gsc->daily_totals_series( $property['id'], $from, $to ) : [];
		$totals = [
			'clicks'      => array_sum( array_column( $series, 'clicks' ) ),
			'impressions' => array_sum( array_column( $series, 'impressions' ) ),
		];

		$winners = [];
		$losers  = [];
		if ( null !== $property ) {
			$prev_to   = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
			$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -' . ( $days - 1 ) . ' days' ) );
			$rows      = $this->movers->period_aggregates( 'query', $property['id'], $from, $to, $prev_from, $prev_to, 500 );
			$winners   = array_slice( $this->growth->detect( $rows ), 0, 10 );
			$losers    = array_slice( $this->decline->detect( $rows ), 0, 10 );
		}

		return [
			'site'          => get_bloginfo( 'name' ),
			'type'          => $type,
			'period'        => [ 'from' => $from, 'to' => $to ],
			'generated_at'  => current_time( 'mysql', true ),
			'health'        => $this->health->latest(),
			'totals'        => $totals,
			'summary'       => $this->insights->latest_site( 'summary_weekly' )['summary'] ?? null,
			'winners'       => $winners,
			'losers'        => $losers,
			'opportunities' => array_slice( $this->opportunities->list_open( 15 ), 0, 15 ),
			'alerts'        => $this->alerts->list( 'active', 20 ),
		];
	}
}

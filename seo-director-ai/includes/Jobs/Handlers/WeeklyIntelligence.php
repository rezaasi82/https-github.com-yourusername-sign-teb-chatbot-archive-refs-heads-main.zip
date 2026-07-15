<?php
/**
 * Weekly intelligence pass (fires on the weekly pipeline): regenerate the
 * monthly roadmap from current opportunities and, when AI is available,
 * produce a cached weekly executive summary from deterministic evidence.
 * Runs after the sync/analysis passes so it works on fresh rollups.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Ai\InsightService;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Roadmap\RoadmapGenerator;

defined( 'ABSPATH' ) || exit;

final class WeeklyIntelligence {

	public function __construct(
		private RoadmapGenerator $roadmap,
		private InsightService $insights,
		private GscRepository $gsc,
		private PropertiesRepository $properties,
		private TrendAnalyzer $trend,
	) {}

	public function run(): void {
		$this->roadmap->generate( 'monthly' );

		if ( ! $this->insights->is_available() ) {
			return;
		}

		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return;
		}

		$to     = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$from   = gmdate( 'Y-m-d', strtotime( $to . ' -13 days' ) );
		$series = $this->gsc->daily_totals_series( $property['id'], $from, $to );

		$wow = $this->trend->week_over_week( array_map( static fn( $d ) => (float) $d['clicks'], $series ) );
		if ( null === $wow ) {
			return;
		}

		$this->insights->generate(
			'summary_weekly',
			[
				'clicks_this_week' => (int) $wow['current'],
				'clicks_last_week' => (int) $wow['previous'],
				'change_pct'       => $wow['change_pct'],
				'period'           => [ $from, $to ],
			],
			[
				'entity_type'  => 'site',
				'period_start' => $from,
				'period_end'   => $to,
			]
		);
	}
}

<?php
/**
 * Site-level traffic drop: complete 7-day block vs the block before
 * (weekday mix identical on both sides, so weekly seasonality cancels).
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Rules;

use SEODirector\Alerts\AlertRuleInterface;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class TrafficDropRule implements AlertRuleInterface {

	private const MIN_WEEKLY_CLICKS = 50;

	public function __construct(
		private GscRepository $gsc,
		private PropertiesRepository $properties,
		private TrendAnalyzer $trend,
	) {}

	public function slug(): string {
		return 'traffic_drop';
	}

	public function evaluate(): array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return [];
		}

		$to     = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$from   = gmdate( 'Y-m-d', strtotime( $to . ' -13 days' ) );
		$series = $this->gsc->daily_totals_series( $property['id'], $from, $to );

		$wow = $this->trend->week_over_week( array_map( static fn( $d ) => (float) $d['clicks'], $series ) );

		if ( null === $wow || null === $wow['change_pct'] || $wow['previous'] < self::MIN_WEEKLY_CLICKS ) {
			return [];
		}

		if ( $wow['change_pct'] > -25 ) {
			return [];
		}

		$severity = $wow['change_pct'] <= -40 ? 'critical' : 'high';

		return [
			[
				'severity'        => $severity,
				'message'         => sprintf(
					/* translators: 1: percent drop, 2: current clicks, 3: previous clicks. */
					__( 'Organic clicks dropped %1$s%% week-over-week (%2$s vs %3$s).', 'seo-director-ai' ),
					number_format_i18n( abs( $wow['change_pct'] ), 1 ),
					number_format_i18n( $wow['current'] ),
					number_format_i18n( $wow['previous'] )
				),
				// Week-scoped fingerprint: a drop re-fires as a new alert in a later week.
				'fingerprint_hex' => md5( 'traffic_drop|' . gmdate( 'oW' ) ),
				'entity_label'    => null,
				'data'            => $wow,
			],
		];
	}
}

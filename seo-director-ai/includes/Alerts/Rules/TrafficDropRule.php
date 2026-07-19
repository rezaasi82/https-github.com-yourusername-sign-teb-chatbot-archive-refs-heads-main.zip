<?php
/**
 * Traffic drop rule: aligned 7-day clicks fell sharply vs. the prior 7 days.
 * Severity scales with the drop; fingerprint includes the week so a new week
 * with a persisting drop re-raises after auto-resolution.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Rules;

defined( 'ABSPATH' ) || exit;

use SEODirector\Alerts\AlertRuleInterface;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\PropertiesRepository;

final class TrafficDropRule implements AlertRuleInterface {

	private const CRITICAL_DROP = -50.0;
	private const HIGH_DROP     = -30.0;
	private const MEDIUM_DROP   = -15.0;
	private const MIN_BASE_CLICKS = 50; // Ignore noise on tiny sites.

	public function __construct(
		private readonly PropertiesRepository $properties,
		private readonly GscDailyTotalsRepository $totals,
		private readonly TrendAnalyzer $trend,
	) {}

	public function slug(): string {
		return 'traffic_drop';
	}

	public function evaluate(): array {
		$property = $this->properties->active_property( 'gsc' );
		if ( ! $property ) {
			return array();
		}

		$to     = gmdate( 'Y-m-d' );
		$from   = gmdate( 'Y-m-d', strtotime( '-16 days' ) );
		$series = $this->totals->series( (int) $property->id, $from, $to );
		$clicks = array_map( static fn( $r ) => (float) $r['clicks'], $series );

		if ( count( $clicks ) < 14 ) {
			return array();
		}

		$base = array_sum( array_slice( $clicks, -14, 7 ) );
		if ( $base < self::MIN_BASE_CLICKS ) {
			return array();
		}

		$wow = $this->trend->wow( $clicks );
		if ( null === $wow || $wow > self::MEDIUM_DROP ) {
			return array();
		}

		$severity = match ( true ) {
			$wow <= self::CRITICAL_DROP => 'critical',
			$wow <= self::HIGH_DROP     => 'high',
			default                     => 'medium',
		};

		return array(
			array(
				'severity'     => $severity,
				'message'      => sprintf(
					/* translators: %s: percentage drop */
					__( 'Organic clicks dropped %s%% week-over-week.', 'seo-director-ai' ),
					number_format_i18n( abs( round( $wow, 1 ) ), 1 )
				),
				'fingerprint'  => 'wow_' . gmdate( 'oW' ), // ISO year+week.
				'entity_label' => null,
				'data'         => array(
					'wow_pct'     => round( $wow, 1 ),
					'base_clicks' => (int) $base,
				),
			),
		);
	}
}

<?php
/**
 * Composite 0–100 SEO health score. Pure: receives pre-computed component
 * inputs (null = data unavailable), redistributes weights across available
 * components, and returns the score with a full transparency breakdown.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\HealthScore;

use SEODirector\Analysis\ExpectedCtrCurve;

defined( 'ABSPATH' ) || exit;

final class HealthScoreCalculator {

	/** @var array<string, int> Default component weights (sum 100). */
	private const WEIGHTS = [
		'ranking'          => 20,
		'ctr'              => 15,
		'core_web_vitals'  => 15,
		'indexation'       => 10,
		'content_freshness' => 10,
		'internal_linking' => 10,
		'growth_trend'     => 10,
		'traffic_trend'    => 10,
	];

	public function __construct( private ExpectedCtrCurve $ctr_curve ) {}

	/**
	 * @param array{
	 *   avg_position?: float|null,
	 *   ctr?: float|null,
	 *   cwv_status_counts?: array{good: int, needs_improvement: int, poor: int}|null,
	 *   indexation_ratio?: float|null,
	 *   freshness_ratio?: float|null,
	 *   internal_link_ratio?: float|null,
	 *   clicks_weekly_slope?: float|null,
	 *   sessions_weekly_slope?: float|null
	 * } $inputs
	 * @return array{score: int, band: string, components: array<string, array{score: int|null, weight: int, available: bool}>}
	 */
	public function calculate( array $inputs ): array {
		$sub = [
			'ranking'           => $this->ranking_score( $inputs['avg_position'] ?? null ),
			'ctr'               => $this->ctr_score( $inputs['ctr'] ?? null, $inputs['avg_position'] ?? null ),
			'core_web_vitals'   => $this->cwv_score( $inputs['cwv_status_counts'] ?? null ),
			'indexation'        => $this->ratio_score( $inputs['indexation_ratio'] ?? null ),
			'content_freshness' => $this->ratio_score( $inputs['freshness_ratio'] ?? null ),
			'internal_linking'  => $this->ratio_score( $inputs['internal_link_ratio'] ?? null ),
			'growth_trend'      => $this->trend_score( $inputs['clicks_weekly_slope'] ?? null ),
			'traffic_trend'     => $this->trend_score( $inputs['sessions_weekly_slope'] ?? null ),
		];

		$weights = self::WEIGHTS;
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the health score component weights.
			 *
			 * @param array<string, int> $weights Component => weight.
			 */
			$weights = apply_filters( 'sda_health_score_weights', $weights );
		}

		$weighted_sum     = 0.0;
		$available_weight = 0;
		$components       = [];

		foreach ( $weights as $component => $weight ) {
			$score = $sub[ $component ] ?? null;

			$components[ $component ] = [
				'score'     => $score,
				'weight'    => (int) $weight,
				'available' => null !== $score,
			];

			if ( null !== $score ) {
				$weighted_sum     += $score * $weight;
				$available_weight += (int) $weight;
			}
		}

		$score = $available_weight > 0 ? (int) round( $weighted_sum / $available_weight ) : 0;

		return [
			'score'      => max( 0, min( 100, $score ) ),
			'band'       => $score >= 75 ? 'green' : ( $score >= 50 ? 'yellow' : 'red' ),
			'components' => $components,
		];
	}

	private function ranking_score( ?float $avg_position ): ?int {
		if ( null === $avg_position || $avg_position <= 0 ) {
			return null;
		}

		// Position 1 → 100, position 20+ → 0, linear in between.
		return (int) round( 100 * max( 0.0, 1 - ( $avg_position - 1 ) / 19 ) );
	}

	private function ctr_score( ?float $ctr, ?float $avg_position ): ?int {
		if ( null === $ctr || null === $avg_position || $avg_position <= 0 ) {
			return null;
		}

		$expected = $this->ctr_curve->expected( $avg_position );
		if ( $expected <= 0 ) {
			return null;
		}

		// Meeting the expected curve = 67; 1.5× the curve or better = 100.
		return (int) round( 100 * min( 1.5, $ctr / $expected ) / 1.5 );
	}

	/**
	 * @param array{good: int, needs_improvement: int, poor: int}|null $counts
	 */
	private function cwv_score( ?array $counts ): ?int {
		if ( null === $counts ) {
			return null;
		}

		$total = $counts['good'] + $counts['needs_improvement'] + $counts['poor'];
		if ( 0 === $total ) {
			return null;
		}

		return (int) round( ( 100 * $counts['good'] + 55 * $counts['needs_improvement'] + 15 * $counts['poor'] ) / $total );
	}

	private function ratio_score( ?float $ratio ): ?int {
		return null === $ratio ? null : (int) round( 100 * max( 0.0, min( 1.0, $ratio ) ) );
	}

	/**
	 * Weekly relative slope in [-1, 1]: -20%/week → 0, flat → 50, +20%/week → 100.
	 */
	private function trend_score( ?float $weekly_slope ): ?int {
		if ( null === $weekly_slope ) {
			return null;
		}

		$clamped = max( -0.2, min( 0.2, $weekly_slope ) );

		return (int) round( 50 + 250 * $clamped );
	}
}

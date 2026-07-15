<?php
/**
 * Composite SEO health score (0–100) from weighted sub-scores.
 * Deterministic: same inputs → same score. Weights filterable.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\HealthScore;

defined( 'ABSPATH' ) || exit;

final class HealthScoreCalculator {

	/** Default component weights (sum = 1.0). */
	private const WEIGHTS = array(
		'traffic_trend'  => 0.35, // clicks WoW/MoM direction & magnitude.
		'visibility'     => 0.25, // impressions trend.
		'ctr_quality'    => 0.15, // CTR vs. expected-by-position.
		'position'       => 0.15, // average position trend (lower = better).
		'data_freshness' => 0.10, // sync recency.
	);

	/**
	 * @param array{
	 *   clicks_wow: ?float, clicks_mom: ?float,
	 *   impressions_wow: ?float,
	 *   ctr: float, position: float,
	 *   days_since_sync: int
	 * } $inputs Metrics produced by the Analysis pipeline.
	 * @return array{score: int, components: array<string, array{score: float, weight: float}>}
	 */
	public function calculate( array $inputs ): array {
		$components = array(
			'traffic_trend'  => $this->trend_score( $inputs['clicks_wow'] ?? null, $inputs['clicks_mom'] ?? null ),
			'visibility'     => $this->change_score( $inputs['impressions_wow'] ?? null ),
			'ctr_quality'    => $this->ctr_score( (float) ( $inputs['ctr'] ?? 0 ), (float) ( $inputs['position'] ?? 0 ) ),
			'position'       => $this->position_score( (float) ( $inputs['position'] ?? 0 ) ),
			'data_freshness' => $this->freshness_score( (int) ( $inputs['days_since_sync'] ?? 99 ) ),
		);

		/**
		 * Filter the health-score component weights.
		 *
		 * @param array<string, float> $weights Component => weight (should sum to 1).
		 */
		$weights = (array) apply_filters( 'sda_health_score_weights', self::WEIGHTS );

		$total       = 0.0;
		$weight_sum  = 0.0;
		$breakdown   = array();
		foreach ( $components as $name => $score ) {
			$weight             = (float) ( $weights[ $name ] ?? 0 );
			$total             += $score * $weight;
			$weight_sum        += $weight;
			$breakdown[ $name ] = array(
				'score'  => round( $score, 1 ),
				'weight' => $weight,
			);
		}

		$final = $weight_sum > 0 ? (int) round( $total / $weight_sum ) : 0;

		return array(
			'score'      => max( 0, min( 100, $final ) ),
			'components' => $breakdown,
		);
	}

	/**
	 * Blend WoW (fast signal) and MoM (stable signal); null changes read as neutral.
	 */
	private function trend_score( ?float $wow, ?float $mom ): float {
		$wow_score = $this->change_score( $wow );
		$mom_score = $this->change_score( $mom );
		return 0.6 * $mom_score + 0.4 * $wow_score;
	}

	/**
	 * Map a % change to 0–100: -50% → 0, 0% → 60, +50% → 100. Neutral 60 when unknown.
	 */
	private function change_score( ?float $pct ): float {
		if ( null === $pct ) {
			return 60.0;
		}
		$clamped = max( -50.0, min( 50.0, $pct ) );
		return $clamped >= 0
			? 60.0 + ( $clamped / 50.0 ) * 40.0
			: 60.0 + ( $clamped / 50.0 ) * 60.0;
	}

	/**
	 * CTR judged against a position-expected curve (rough industry medians).
	 */
	private function ctr_score( float $ctr, float $position ): float {
		$expected = $this->expected_ctr( $position );
		if ( $expected <= 0 ) {
			return 60.0;
		}
		$ratio = $ctr / $expected;
		return max( 0.0, min( 100.0, $ratio * 70.0 ) );
	}

	private function expected_ctr( float $position ): float {
		return match ( true ) {
			$position <= 0   => 0.0,
			$position <= 1.5 => 0.28,
			$position <= 3   => 0.11,
			$position <= 5   => 0.06,
			$position <= 10  => 0.025,
			$position <= 20  => 0.01,
			default          => 0.004,
		};
	}

	private function position_score( float $position ): float {
		return match ( true ) {
			$position <= 0  => 50.0, // unknown.
			$position <= 3  => 100.0,
			$position <= 10 => 85.0,
			$position <= 20 => 65.0,
			$position <= 50 => 40.0,
			default         => 20.0,
		};
	}

	private function freshness_score( int $days ): float {
		return match ( true ) {
			$days <= 2  => 100.0,
			$days <= 4  => 80.0,
			$days <= 7  => 50.0,
			$days <= 14 => 25.0,
			default     => 0.0,
		};
	}
}

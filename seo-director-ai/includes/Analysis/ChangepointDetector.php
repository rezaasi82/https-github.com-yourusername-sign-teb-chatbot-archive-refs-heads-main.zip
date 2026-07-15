<?php
/**
 * Detects the strongest sustained level shift in a daily series — "when did
 * the drop/jump happen". Scans candidate split points and scores the
 * difference of window means, normalized by pooled standard deviation
 * (a simplified two-sample test). Pure, no I/O.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class ChangepointDetector {

	private const MIN_WINDOW = 7;
	private const MIN_SCORE  = 2.0; // ~2 pooled stddevs of separation.

	public function __construct( private TrendAnalyzer $trend ) {}

	/**
	 * @param array<string, float> $series date (Y-m-d) => value, time-ascending.
	 * @return array{date: string, before_mean: float, after_mean: float, change_pct: float|null, score: float}|null
	 */
	public function detect( array $series ): ?array {
		$dates  = array_keys( $series );
		$values = array_values( array_map( 'floatval', $series ) );
		$n      = count( $values );

		if ( $n < 2 * self::MIN_WINDOW ) {
			return null;
		}

		$best = null;

		for ( $split = self::MIN_WINDOW; $split <= $n - self::MIN_WINDOW; $split++ ) {
			$before = array_slice( $values, 0, $split );
			$after  = array_slice( $values, $split );

			$mean_before = $this->trend->mean( $before );
			$mean_after  = $this->trend->mean( $after );

			$pooled = ( $this->trend->stddev( $before ) + $this->trend->stddev( $after ) ) / 2;
			if ( $pooled <= 0.0 ) {
				$pooled = max( 1.0, abs( $mean_before ) * 0.05 );
			}

			$score = abs( $mean_after - $mean_before ) / $pooled;

			if ( null === $best || $score > $best['score'] ) {
				$best = [
					'date'        => $dates[ $split ],
					'before_mean' => round( $mean_before, 2 ),
					'after_mean'  => round( $mean_after, 2 ),
					'change_pct'  => $this->trend->percent_change( $mean_before, $mean_after ),
					'score'       => round( $score, 2 ),
				];
			}
		}

		return null !== $best && $best['score'] >= self::MIN_SCORE ? $best : null;
	}
}

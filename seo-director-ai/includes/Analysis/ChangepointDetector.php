<?php
/**
 * Detects the most likely drop/jump date in a daily series.
 * CUSUM-style mean-shift scan — deterministic and cheap (O(n)).
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class ChangepointDetector {

	/**
	 * Find the index where splitting the series best separates two means.
	 * Returns null when no split is significant (shift < $min_shift_ratio of overall mean).
	 *
	 * @param float[] $values          Ordered daily values (≥ 14 points recommended).
	 * @param float   $min_shift_ratio Minimum |mean_after - mean_before| / mean_overall.
	 * @return array{index: int, before_mean: float, after_mean: float, shift_pct: float}|null
	 */
	public function detect( array $values, float $min_shift_ratio = 0.2 ): ?array {
		$values = array_values( $values );
		$n      = count( $values );
		if ( $n < 8 ) {
			return null;
		}

		$total_sum = array_sum( $values );
		$overall   = $total_sum / $n;
		if ( $overall <= 0.0 ) {
			return null;
		}

		$best_index = null;
		$best_gap   = 0.0;
		$best_pair  = array( 0.0, 0.0 );
		$before_sum = $values[0] + $values[1];

		// Keep at least 3 points on each side of the split.
		for ( $i = 3; $i <= $n - 3; $i++ ) {
			$before_sum += $values[ $i - 1 ];
			$before_mean = $before_sum / $i;
			$after_mean  = ( $total_sum - $before_sum ) / ( $n - $i );
			$gap         = abs( $after_mean - $before_mean );

			if ( $gap > $best_gap ) {
				$best_gap   = $gap;
				$best_index = $i;
				$best_pair  = array( $before_mean, $after_mean );
			}
		}

		if ( null === $best_index || ( $best_gap / $overall ) < $min_shift_ratio ) {
			return null;
		}

		return array(
			'index'       => $best_index,
			'before_mean' => round( $best_pair[0], 2 ),
			'after_mean'  => round( $best_pair[1], 2 ),
			'shift_pct'   => round( ( $best_pair[1] - $best_pair[0] ) / max( $best_pair[0], 0.0001 ) * 100, 1 ),
		);
	}
}

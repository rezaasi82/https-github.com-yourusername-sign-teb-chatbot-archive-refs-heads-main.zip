<?php
/**
 * Time-series primitives shared by all analyzers. Pure functions, no I/O.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class TrendAnalyzer {

	/**
	 * Least-squares slope of a numeric series, expressed as change-per-step.
	 *
	 * @param float[] $values Series values in time order.
	 */
	public function slope( array $values ): float {
		$n = count( $values );
		if ( $n < 2 ) {
			return 0.0;
		}

		$values = array_values( $values );
		$sum_x  = 0.0;
		$sum_y  = 0.0;
		$sum_xy = 0.0;
		$sum_xx = 0.0;

		foreach ( $values as $x => $y ) {
			$sum_x  += $x;
			$sum_y  += $y;
			$sum_xy += $x * $y;
			$sum_xx += $x * $x;
		}

		$denominator = $n * $sum_xx - $sum_x * $sum_x;

		return 0.0 === $denominator ? 0.0 : ( $n * $sum_xy - $sum_x * $sum_y ) / $denominator;
	}

	/**
	 * Weekly relative slope: slope × 7 as a fraction of the series mean.
	 * +0.10 means "growing ~10% per week". Bounded to [-1, 1].
	 *
	 * @param float[] $values Daily series.
	 */
	public function weekly_relative_slope( array $values ): float {
		$mean = $this->mean( $values );
		if ( $mean <= 0 ) {
			return 0.0;
		}

		return max( -1.0, min( 1.0, $this->slope( $values ) * 7 / $mean ) );
	}

	/**
	 * Percent change between two aggregates, null when the base is 0.
	 */
	public function percent_change( float $previous, float $current ): ?float {
		if ( 0.0 === $previous ) {
			return null;
		}

		return round( 100 * ( $current - $previous ) / $previous, 1 );
	}

	/**
	 * Seasonality-adjusted week-over-week comparison: compares complete
	 * 7-day blocks so weekday mix is identical on both sides.
	 *
	 * @param float[] $daily At least 14 values, time-ascending.
	 * @return array{previous: float, current: float, change_pct: float|null}|null
	 */
	public function week_over_week( array $daily ): ?array {
		if ( count( $daily ) < 14 ) {
			return null;
		}

		$daily    = array_values( $daily );
		$current  = array_sum( array_slice( $daily, -7 ) );
		$previous = array_sum( array_slice( $daily, -14, 7 ) );

		return [
			'previous'   => $previous,
			'current'    => $current,
			'change_pct' => $this->percent_change( $previous, $current ),
		];
	}

	/**
	 * @param float[] $values
	 */
	public function mean( array $values ): float {
		return [] === $values ? 0.0 : array_sum( $values ) / count( $values );
	}

	/**
	 * @param float[] $values
	 */
	public function stddev( array $values ): float {
		$n = count( $values );
		if ( $n < 2 ) {
			return 0.0;
		}

		$mean = $this->mean( $values );
		$sum  = 0.0;
		foreach ( $values as $v ) {
			$sum += ( $v - $mean ) ** 2;
		}

		return sqrt( $sum / ( $n - 1 ) );
	}
}

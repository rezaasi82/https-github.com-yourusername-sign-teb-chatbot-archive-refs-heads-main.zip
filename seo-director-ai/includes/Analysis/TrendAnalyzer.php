<?php
/**
 * Deterministic trend statistics over daily series. Pure functions, no I/O.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class TrendAnalyzer {

	/**
	 * Least-squares slope of a numeric series (units per day).
	 *
	 * @param float[] $values Ordered daily values.
	 */
	public function slope( array $values ): float {
		$n = count( $values );
		if ( $n < 2 ) {
			return 0.0;
		}
		$sum_x  = 0.0;
		$sum_y  = 0.0;
		$sum_xy = 0.0;
		$sum_x2 = 0.0;
		foreach ( array_values( $values ) as $x => $y ) {
			$sum_x  += $x;
			$sum_y  += $y;
			$sum_xy += $x * $y;
			$sum_x2 += $x * $x;
		}
		$denominator = ( $n * $sum_x2 ) - ( $sum_x * $sum_x );
		return 0.0 === $denominator ? 0.0 : ( ( $n * $sum_xy ) - ( $sum_x * $sum_y ) ) / $denominator;
	}

	/**
	 * Percentage change between the last $window days and the $window days before that.
	 * Returns null when the base period is empty (change undefined, not "0%").
	 *
	 * @param float[] $values Ordered daily values, most recent last.
	 */
	public function period_over_period( array $values, int $window ): ?float {
		if ( count( $values ) < 2 * $window ) {
			return null;
		}
		$recent   = array_slice( $values, -$window );
		$previous = array_slice( $values, -2 * $window, $window );
		$base     = array_sum( $previous );
		if ( $base <= 0.0 ) {
			return null;
		}
		return ( array_sum( $recent ) - $base ) / $base * 100;
	}

	/**
	 * 7-day-aligned week-over-week change — removes day-of-week seasonality
	 * by always comparing whole Mon–Sun-sized blocks.
	 *
	 * @param float[] $values Ordered daily values.
	 */
	public function wow( array $values ): ?float {
		return $this->period_over_period( $values, 7 );
	}

	/** @param float[] $values */
	public function mom( array $values ): ?float {
		return $this->period_over_period( $values, 28 );
	}

	/**
	 * Simple moving average of the final $window points.
	 *
	 * @param float[] $values Ordered values.
	 */
	public function moving_average( array $values, int $window ): float {
		$slice = array_slice( $values, -$window );
		return empty( $slice ) ? 0.0 : array_sum( $slice ) / count( $slice );
	}

	/**
	 * Population standard deviation.
	 *
	 * @param float[] $values Values.
	 */
	public function stddev( array $values ): float {
		$n = count( $values );
		if ( $n < 2 ) {
			return 0.0;
		}
		$mean = array_sum( $values ) / $n;
		$sum  = 0.0;
		foreach ( $values as $v ) {
			$sum += ( $v - $mean ) ** 2;
		}
		return sqrt( $sum / $n );
	}
}

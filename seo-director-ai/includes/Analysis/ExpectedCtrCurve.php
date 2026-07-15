<?php
/**
 * Expected organic CTR by average position — the baseline for "low CTR"
 * detection and the CTR health component. Values approximate published
 * aggregate curves; overridable via the sda_expected_ctr_curve filter.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class ExpectedCtrCurve {

	/** @var array<int, float> position => expected CTR. */
	private const CURVE = [
		1  => 0.28,
		2  => 0.15,
		3  => 0.10,
		4  => 0.07,
		5  => 0.05,
		6  => 0.04,
		7  => 0.03,
		8  => 0.025,
		9  => 0.021,
		10 => 0.018,
		15 => 0.010,
		20 => 0.006,
		30 => 0.003,
		50 => 0.001,
	];

	/**
	 * Expected CTR for a (possibly fractional) average position,
	 * linearly interpolated between curve anchors.
	 */
	public function expected( float $position ): float {
		$curve = self::CURVE;
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the expected-CTR-by-position curve.
			 *
			 * @param array<int, float> $curve Position => CTR anchors.
			 */
			$curve = apply_filters( 'sda_expected_ctr_curve', $curve );
		}

		$position = max( 1.0, $position );
		$anchors  = array_keys( $curve );

		if ( $position <= (float) $anchors[0] ) {
			return $curve[ $anchors[0] ];
		}

		$last = end( $anchors );
		if ( $position >= (float) $last ) {
			return $curve[ $last ];
		}

		for ( $i = 1, $count = count( $anchors ); $i < $count; $i++ ) {
			$hi = (float) $anchors[ $i ];
			if ( $position <= $hi ) {
				$lo       = (float) $anchors[ $i - 1 ];
				$fraction = ( $position - $lo ) / ( $hi - $lo );

				return $curve[ $anchors[ $i - 1 ] ] + $fraction * ( $curve[ $anchors[ $i ] ] - $curve[ $anchors[ $i - 1 ] ] );
			}
		}

		return $curve[ $last ];
	}
}

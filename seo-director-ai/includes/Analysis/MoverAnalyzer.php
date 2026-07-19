<?php
/**
 * Winners/Losers: diff two rollup periods to surface the biggest movers.
 * Pure computation over already-fetched rollup maps — no I/O.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class MoverAnalyzer {

	/** Ignore entities below this recent+previous clicks floor (noise). */
	private const MIN_TOTAL_CLICKS = 10;

	/**
	 * @param array<string, array<string, mixed>> $recent   Entity label => rollup row.
	 * @param array<string, array<string, mixed>> $previous Entity label => rollup row.
	 * @param int                                 $limit    Max movers per direction.
	 * @return array{winners: array<int, array<string, mixed>>, losers: array<int, array<string, mixed>>}
	 */
	public function movers( array $recent, array $previous, int $limit = 25 ): array {
		$labels = array_unique( array_merge( array_keys( $recent ), array_keys( $previous ) ) );
		$movers = array();

		foreach ( $labels as $label ) {
			$now  = $recent[ $label ] ?? null;
			$then = $previous[ $label ] ?? null;

			$clicks_now  = (int) ( $now['clicks'] ?? 0 );
			$clicks_then = (int) ( $then['clicks'] ?? 0 );
			if ( ( $clicks_now + $clicks_then ) < self::MIN_TOTAL_CLICKS ) {
				continue;
			}

			$delta      = $clicks_now - $clicks_then;
			$pct_change = $clicks_then > 0
				? round( $delta / $clicks_then * 100, 1 )
				: null; // New entry — % change undefined.

			$pos_now  = (float) ( $now['position'] ?? 0 );
			$pos_then = (float) ( $then['position'] ?? 0 );

			$movers[] = array(
				'label'         => (string) $label,
				'clicks'        => $clicks_now,
				'clicks_prev'   => $clicks_then,
				'clicks_delta'  => $delta,
				'pct_change'    => $pct_change,
				'position'      => round( $pos_now, 1 ),
				'position_prev' => round( $pos_then, 1 ),
				'position_delta' => ( $pos_now > 0 && $pos_then > 0 ) ? round( $pos_then - $pos_now, 1 ) : null,
				'is_new'        => null === $then,
				'is_lost'       => null === $now || 0 === $clicks_now,
			);
		}

		$winners = array_values( array_filter( $movers, static fn( $m ) => $m['clicks_delta'] > 0 ) );
		$losers  = array_values( array_filter( $movers, static fn( $m ) => $m['clicks_delta'] < 0 ) );

		usort( $winners, static fn( $a, $b ) => $b['clicks_delta'] <=> $a['clicks_delta'] );
		usort( $losers, static fn( $a, $b ) => $a['clicks_delta'] <=> $b['clicks_delta'] );

		return array(
			'winners' => array_slice( $winners, 0, $limit ),
			'losers'  => array_slice( $losers, 0, $limit ),
		);
	}
}

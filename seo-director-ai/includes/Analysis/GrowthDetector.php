<?php
/**
 * Winners: entities with significant period-over-period click growth,
 * each with a deterministic "reason" classification and a suggested
 * next action. Pure, no I/O.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class GrowthDetector {

	private const MIN_TOTAL_CLICKS = 10;

	/**
	 * @param MoverRow[] $rows
	 * @return array<int, array<string, mixed>> Sorted by click delta desc.
	 */
	public function detect( array $rows, int $limit = 25 ): array {
		$winners = [];

		foreach ( $rows as $row ) {
			$delta = $row->cur_clicks - $row->prev_clicks;
			if ( $delta <= 0 || $row->cur_clicks + $row->prev_clicks < self::MIN_TOTAL_CLICKS ) {
				continue;
			}

			$winners[] = [
				'label'          => $row->label,
				'hash'           => $row->hash_hex,
				'clicks'         => $row->cur_clicks,
				'clicks_delta'   => $delta,
				'growth_pct'     => 0 === $row->prev_clicks ? null : round( 100 * $delta / $row->prev_clicks, 1 ),
				'is_new'         => 0 === $row->prev_clicks,
				'position'       => round( $row->cur_position, 1 ),
				'position_delta' => round( $row->cur_position - $row->prev_position, 1 ),
				'reason'         => $this->classify( $row ),
				'next_action'    => $this->next_action( $row ),
			];
		}

		usort( $winners, static fn( $a, $b ) => $b['clicks_delta'] <=> $a['clicks_delta'] );

		return array_slice( $winners, 0, $limit );
	}

	private function classify( MoverRow $row ): string {
		if ( 0 === $row->prev_clicks ) {
			return 'new_entry';
		}
		if ( $row->prev_position - $row->cur_position >= 1.0 ) {
			return 'ranking_gain';
		}
		if ( $row->prev_impressions > 0 && $row->cur_impressions / max( 1, $row->prev_impressions ) >= 1.3 ) {
			return 'demand_increase';
		}
		if ( $row->cur_ctr > $row->prev_ctr * 1.2 ) {
			return 'ctr_improvement';
		}

		return 'gradual_growth';
	}

	private function next_action( MoverRow $row ): string {
		if ( $row->cur_position > 3.5 && $row->cur_position <= 10 ) {
			return 'push_to_top3';
		}
		if ( $row->cur_position > 10 ) {
			return 'push_to_page1';
		}

		return 'protect_position';
	}
}

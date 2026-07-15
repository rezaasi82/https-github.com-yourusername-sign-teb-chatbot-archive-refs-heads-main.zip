<?php
/**
 * Losers: entities with significant period-over-period click loss, with a
 * deterministic estimated cause, priority level, and suggested fix.
 * Pure, no I/O. (The AI layer adds narrative + confidence in Phase 3.)
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis;

defined( 'ABSPATH' ) || exit;

final class DeclineDetector {

	private const MIN_TOTAL_CLICKS = 10;

	/**
	 * @param MoverRow[] $rows
	 * @return array<int, array<string, mixed>> Sorted by click loss desc.
	 */
	public function detect( array $rows, int $limit = 25 ): array {
		$losers = [];

		foreach ( $rows as $row ) {
			$delta = $row->cur_clicks - $row->prev_clicks;
			if ( $delta >= 0 || $row->cur_clicks + $row->prev_clicks < self::MIN_TOTAL_CLICKS ) {
				continue;
			}

			$loss_pct = 0 === $row->prev_clicks ? null : round( 100 * $delta / $row->prev_clicks, 1 );

			$losers[] = [
				'label'          => $row->label,
				'hash'           => $row->hash_hex,
				'clicks'         => $row->cur_clicks,
				'clicks_delta'   => $delta,
				'loss_pct'       => $loss_pct,
				'position'       => round( $row->cur_position, 1 ),
				'position_delta' => round( $row->cur_position - $row->prev_position, 1 ),
				'cause'          => $this->classify( $row ),
				'priority'       => $this->priority( $row, $loss_pct ),
				'suggested_fix'  => $this->suggested_fix( $row ),
			];
		}

		usort( $losers, static fn( $a, $b ) => $a['clicks_delta'] <=> $b['clicks_delta'] );

		return array_slice( $losers, 0, $limit );
	}

	private function classify( MoverRow $row ): string {
		$position_worse = $row->cur_position - $row->prev_position;

		if ( 0 === $row->cur_clicks && 0 === $row->cur_impressions ) {
			return 'disappeared'; // Deindexed or fully lost — highest severity signal.
		}
		if ( $position_worse >= 2.0 ) {
			return 'ranking_loss';
		}
		if ( $row->prev_impressions > 0 && $row->cur_impressions / $row->prev_impressions <= 0.7 ) {
			return 'demand_drop';
		}
		if ( $row->cur_ctr < $row->prev_ctr * 0.8 && $position_worse < 1.0 ) {
			return 'ctr_decline'; // Position held, clicks lost: SERP feature / title issue.
		}

		return 'gradual_decay';
	}

	private function priority( MoverRow $row, ?float $loss_pct ): string {
		$cause = $this->classify( $row );

		if ( 'disappeared' === $cause || ( null !== $loss_pct && $loss_pct <= -60 && $row->prev_clicks >= 50 ) ) {
			return 'critical';
		}
		if ( null !== $loss_pct && $loss_pct <= -30 && $row->prev_clicks >= 20 ) {
			return 'high';
		}
		if ( null !== $loss_pct && $loss_pct <= -15 ) {
			return 'medium';
		}

		return 'low';
	}

	private function suggested_fix( MoverRow $row ): string {
		return match ( $this->classify( $row ) ) {
			'disappeared'  => 'check_indexation',
			'ranking_loss' => 'refresh_content_and_links',
			'demand_drop'  => 'verify_seasonality',
			'ctr_decline'  => 'rewrite_title_meta',
			default        => 'schedule_content_refresh',
		};
	}
}

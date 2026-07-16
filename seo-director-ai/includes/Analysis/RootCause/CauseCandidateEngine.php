<?php
/**
 * Builds the deterministic evidence packet for a declining entity: each
 * candidate cause with the signals that support or rule it out. This is the
 * "determinism first" half of root-cause analysis — the AI layer ranks and
 * narrates these candidates but never invents them. Pure, no I/O.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\RootCause;

use SEODirector\Analysis\MoverRow;

defined( 'ABSPATH' ) || exit;

final class CauseCandidateEngine {

	public function __construct(
		private CoreUpdateCalendar $updates,
		private ?SerpProviderInterface $serp = null,
	) {}

	/**
	 * @param MoverRow            $row       The declining entity's two-window aggregates.
	 * @param string|null         $drop_date Changepoint date (Y-m-d) if detected.
	 * @param array<string, bool> $flags     Extra signals: cannibalization, orphaned, cwv_regressed…
	 * @return array<string, mixed> Evidence packet handed to the AI layer.
	 */
	public function build( MoverRow $row, ?string $drop_date, array $flags = [] ): array {
		$position_change = round( $row->cur_position - $row->prev_position, 1 );
		$ctr_change      = $row->prev_ctr > 0 ? round( 100 * ( $row->cur_ctr - $row->prev_ctr ) / $row->prev_ctr, 1 ) : null;
		$impr_change     = $row->prev_impressions > 0 ? round( 100 * ( $row->cur_impressions - $row->prev_impressions ) / $row->prev_impressions, 1 ) : null;

		$candidates = [];

		// Ranking drop.
		if ( $position_change >= 1.0 ) {
			$candidates[] = [
				'cause'    => 'ranking_drop',
				'evidence' => sprintf( 'Average position worsened by %s places.', $position_change ),
				'strength' => $position_change >= 3 ? 'strong' : 'moderate',
			];
		}

		// CTR drop with stable position → SERP feature / title.
		if ( null !== $ctr_change && $ctr_change <= -20 && abs( $position_change ) < 1.0 ) {
			$candidates[] = [
				'cause'    => 'ctr_drop',
				'evidence' => sprintf( 'CTR fell %s%% while position stayed within 1 place.', abs( $ctr_change ) ),
				'strength' => 'strong',
			];
		}

		// Demand drop (competitor growth / seasonality).
		if ( null !== $impr_change && $impr_change <= -25 ) {
			$candidates[] = [
				'cause'    => 'demand_or_competitor',
				'evidence' => sprintf( 'Impressions fell %s%%, suggesting lower demand or competitor gains.', abs( $impr_change ) ),
				'strength' => 'moderate',
			];
		}

		// Cannibalization.
		if ( ! empty( $flags['cannibalization'] ) ) {
			$candidates[] = [
				'cause'    => 'cannibalization',
				'evidence' => 'Multiple URLs alternate in results for the same query cluster.',
				'strength' => 'strong',
			];
		}

		// Internal linking / orphaned.
		if ( ! empty( $flags['orphaned'] ) ) {
			$candidates[] = [
				'cause'    => 'internal_linking',
				'evidence' => 'The page lost internal links or became orphaned.',
				'strength' => 'moderate',
			];
		}

		// Technical / CWV.
		if ( ! empty( $flags['cwv_regressed'] ) ) {
			$candidates[] = [
				'cause'    => 'technical_cwv',
				'evidence' => 'Core Web Vitals regressed to "poor" on this page.',
				'strength' => 'moderate',
			];
		}

		// Core update proximity.
		$update = null !== $drop_date ? $this->updates->near( $drop_date ) : null;
		if ( null !== $update ) {
			$candidates[] = [
				'cause'    => 'core_update',
				'evidence' => sprintf( 'Drop date is within 5 days of the %s.', $update ),
				'strength' => 'moderate',
			];
		}

		// SERP enrichment (Enterprise): a live lookup can reveal SERP features
		// (AI overview, featured snippet, ads) that siphon clicks even when the
		// organic position is stable. The provider is null unless configured.
		$serp = null;
		if ( null !== $this->serp && ! empty( $flags['serp_query'] ) ) {
			$serp = $this->serp->serp_context( (string) $flags['serp_query'] );
		}

		if ( null !== $serp && [] !== ( $serp['features'] ?? [] ) ) {
			$candidates[] = [
				'cause'    => 'serp_features',
				'evidence' => sprintf(
					'SERP now shows features (%s) that can absorb clicks above the organic result.',
					implode( ', ', array_slice( $serp['features'], 0, 5 ) )
				),
				'strength' => ( null !== $ctr_change && $ctr_change <= -15 ) ? 'strong' : 'moderate',
			];
		}

		if ( [] === $candidates ) {
			$candidates[] = [
				'cause'    => 'content_decay',
				'evidence' => 'No sharp signal; gradual decline consistent with aging content.',
				'strength' => 'weak',
			];
		}

		return [
			'entity'          => $row->label,
			'clicks_current'  => $row->cur_clicks,
			'clicks_previous' => $row->prev_clicks,
			'clicks_change'   => $row->cur_clicks - $row->prev_clicks,
			'position_change' => $position_change,
			'ctr_change_pct'  => $ctr_change,
			'impr_change_pct' => $impr_change,
			'drop_date'       => $drop_date,
			'serp'            => $serp,
			'candidates'      => $candidates,
		];
	}
}

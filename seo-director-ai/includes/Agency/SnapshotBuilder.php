<?php
/**
 * Assembles the compact metric snapshot a client site pushes to its agency
 * hub. Deliberately small: the hub grid only needs headline numbers, not the
 * full dataset, so a snapshot stays a few hundred bytes and carries no PII.
 *
 * @package SEODirector
 */

namespace SEODirector\Agency;

use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class SnapshotBuilder {

	public function __construct(
		private HealthScoreRepository $health,
		private AlertsRepository $alerts,
		private OpportunitiesRepository $opportunities,
		private GscRepository $gsc,
		private PropertiesRepository $properties,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function build(): array {
		$health       = $this->health->latest();
		$alert_counts = $this->alerts->active_counts();

		return [
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'generated_at' => gmdate( 'c' ),
			'plugin_version' => defined( 'SDA_VERSION' ) ? SDA_VERSION : '',
			'health'       => null === $health
				? null
				: [ 'score' => $health['score'], 'band' => $health['band'], 'delta' => $health['delta'] ],
			'alerts'       => [
				'critical' => $alert_counts['critical'] ?? 0,
				'high'     => $alert_counts['high'] ?? 0,
				'total'    => array_sum( $alert_counts ),
			],
			'opportunities' => count( $this->opportunities->list_open( 50 ) ),
			'traffic'       => $this->traffic_headline(),
		];
	}

	/**
	 * 28-day organic clicks total plus period-over-period change, when a GSC
	 * property is connected. Null otherwise so the hub can show "not connected".
	 *
	 * @return array{clicks: int, change_pct: float|null}|null
	 */
	private function traffic_headline(): ?array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return null;
		}

		$cur_to    = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$cur_from  = gmdate( 'Y-m-d', strtotime( $cur_to . ' -27 days' ) );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $cur_from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -27 days' ) );

		$cur  = $this->gsc->daily_totals_series( $property['id'], $cur_from, $cur_to );
		$prev = $this->gsc->daily_totals_series( $property['id'], $prev_from, $prev_to );

		$cur_clicks  = (int) array_sum( array_column( $cur, 'clicks' ) );
		$prev_clicks = (int) array_sum( array_column( $prev, 'clicks' ) );

		$change = $prev_clicks > 0 ? round( ( ( $cur_clicks - $prev_clicks ) / $prev_clicks ) * 100, 1 ) : null;

		return [ 'clicks' => $cur_clicks, 'change_pct' => $change ];
	}
}

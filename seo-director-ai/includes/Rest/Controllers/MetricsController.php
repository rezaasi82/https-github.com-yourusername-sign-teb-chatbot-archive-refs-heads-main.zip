<?php
/**
 * GET /sda/v1/metrics/search — GSC daily totals series with optional
 * previous-period comparison. Serves exclusively from local rollup tables.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class MetricsController extends AbstractController {

	public function __construct(
		private GscRepository $gsc,
		private PropertiesRepository $properties,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/metrics/search',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_series' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				'args'                => [
					'days'    => [ 'type' => 'integer', 'default' => 28, 'minimum' => 7, 'maximum' => 480 ],
					'compare' => [ 'type' => 'boolean', 'default' => true ],
				],
			]
		);
	}

	public function get_series( \WP_REST_Request $request ): \WP_REST_Response {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return rest_ensure_response( [ 'series' => [], 'previous' => [], 'totals' => null ] );
		}

		$days = (int) $request->get_param( 'days' );
		$to   = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$from = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $days - 1 ) . ' days' ) );

		$series = $this->gsc->daily_totals_series( $property['id'], $from, $to );

		$previous = [];
		if ( $request->get_param( 'compare' ) ) {
			$prev_to   = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
			$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -' . ( $days - 1 ) . ' days' ) );
			$previous  = $this->gsc->daily_totals_series( $property['id'], $prev_from, $prev_to );
		}

		return rest_ensure_response(
			[
				'series'   => $series,
				'previous' => $previous,
				'totals'   => $this->sum( $series ),
				'previous_totals' => $this->sum( $previous ),
			]
		);
	}

	/**
	 * @param array<int, array{clicks: int, impressions: int, ctr: float, position: float}> $series
	 * @return array{clicks: int, impressions: int, ctr: float, position: float}|null
	 */
	private function sum( array $series ): ?array {
		if ( [] === $series ) {
			return null;
		}

		$clicks      = array_sum( array_column( $series, 'clicks' ) );
		$impressions = array_sum( array_column( $series, 'impressions' ) );
		$weighted    = 0.0;
		foreach ( $series as $day ) {
			$weighted += $day['position'] * $day['impressions'];
		}

		return [
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? round( $clicks / $impressions, 4 ) : 0.0,
			'position'    => $impressions > 0 ? round( $weighted / $impressions, 2 ) : 0.0,
		];
	}
}

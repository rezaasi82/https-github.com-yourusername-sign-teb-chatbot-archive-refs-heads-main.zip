<?php
/**
 * GET /sda/v1/overview — dashboard bootstrap payload, served entirely from
 * local tables (no live Google calls in a web request, ever).
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Jobs\Handlers\SyncGscJob;

defined( 'ABSPATH' ) || exit;

final class OverviewController extends AbstractController {

	public function __construct(
		private ConnectionsRepository $connections,
		private PropertiesRepository $properties,
		private GscRepository $gsc,
		private JobStateRepository $job_state,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/overview',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_overview' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
			]
		);
	}

	public function get_overview(): \WP_REST_Response {
		$connected    = $this->connections->connected_services();
		$google_ok    = in_array( 'google', $connected, true );
		$gsc_property = $this->properties->active( 'gsc' );
		$ga4_property = $this->properties->active( 'ga4' );

		$series = [];
		if ( null !== $gsc_property ) {
			$to     = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
			$from   = gmdate( 'Y-m-d', strtotime( $to . ' -27 days' ) );
			$series = $this->gsc->daily_totals_series( $gsc_property['id'], $from, $to );
		}

		return rest_ensure_response(
			[
				'connections'   => [
					'gsc' => $google_ok && null !== $gsc_property,
					'ga4' => $google_ok && null !== $ga4_property,
					'psi' => in_array( 'psi', $connected, true ),
					'ai'  => (bool) array_intersect( [ 'openai', 'claude', 'gemini' ], $connected ),
				],
				'health'        => null, // Arrives with the Phase 2 analyzers.
				'traffic'       => [
					'series'  => $series,
					'compare' => null,
				],
				'opportunities' => [],
				'risks'         => [],
				'summaries'     => [
					'weekly'  => null,
					'monthly' => null,
				],
				'meta'          => [
					'plugin_version' => SDA_VERSION,
					'backfill'       => $this->backfill_state(),
				],
			]
		);
	}

	/**
	 * @return array{status: string, date: string|null}|null
	 */
	private function backfill_state(): ?array {
		$state = $this->job_state->get( SyncGscJob::NAME );
		if ( null === $state || in_array( $state['status'], [ 'done' ], true ) ) {
			return null;
		}

		return [
			'status' => $state['status'],
			'date'   => isset( $state['cursor']['date'] ) ? (string) $state['cursor']['date'] : null,
		];
	}
}

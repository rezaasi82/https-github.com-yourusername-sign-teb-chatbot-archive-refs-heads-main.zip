<?php
/**
 * GET /sda/v1/overview — dashboard bootstrap payload, served entirely from
 * local tables (no live Google calls in a web request, ever).
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\InsightRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Jobs\Handlers\SyncGscJob;
use SEODirector\Onboarding\DemoDataProvider;
use SEODirector\Onboarding\SetupStatus;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class OverviewController extends AbstractController {

	public function __construct(
		private ConnectionsRepository $connections,
		private PropertiesRepository $properties,
		private GscRepository $gsc,
		private JobStateRepository $job_state,
		private HealthScoreRepository $health_scores,
		private OpportunitiesRepository $opportunities,
		private AlertsRepository $alerts,
		private InsightRepository $insights,
		private DemoDataProvider $demo,
		private SetupStatus $setup,
		private Settings $settings,
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

		$ai_ready   = (bool) array_intersect( [ 'openai', 'claude', 'gemini', 'gapgpt' ], $connected );
		$has_synced = 'done' === ( $this->job_state->get( SyncGscJob::NAME )['status'] ?? '' ) && [] !== $series;
		$setup      = $this->setup->build( $google_ok, null !== $gsc_property, $ai_ready, $has_synced );

		// Demo mode: until real data has landed, fill the panels with a clearly
		// labelled sample so the dashboard is never empty on a fresh install.
		$demo = 'off' !== (string) $this->settings->get( 'demo_mode', 'auto' ) && ! $has_synced;

		$connections = [
			'gsc' => $google_ok && null !== $gsc_property,
			'ga4' => $google_ok && null !== $ga4_property,
			'psi' => in_array( 'psi', $connected, true ),
			'ai'  => $ai_ready,
		];

		$meta = [
			'plugin_version' => SDA_VERSION,
			'backfill'       => $this->backfill_state(),
			'demo'           => $demo,
			'setup'          => $setup,
		];

		if ( $demo ) {
			$sample = $this->demo->overview();

			return rest_ensure_response(
				array_merge(
					$sample,
					[ 'connections' => $connections, 'meta' => $meta ]
				)
			);
		}

		return rest_ensure_response(
			[
				'connections'   => $connections,
				'health'        => $this->health_scores->latest(),
				'traffic'       => [
					'series'  => $series,
					'compare' => null,
				],
				'opportunities' => array_slice( $this->opportunities->list_open( 5 ), 0, 5 ),
				'risks'         => array_slice( $this->alerts->list( 'active', 5 ), 0, 5 ),
				'summaries'     => [
					'weekly'  => $this->insights->latest_site( 'summary_weekly' )['summary'] ?? null,
					'monthly' => $this->insights->latest_site( 'summary_monthly' )['summary'] ?? null,
				],
				'meta'          => $meta,
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

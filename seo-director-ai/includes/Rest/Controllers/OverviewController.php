<?php
/**
 * GET /sda/v1/overview — dashboard bootstrap payload.
 * Hard rule: serves only from local tables, never live Google calls.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\GscPageDailyRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use WP_REST_Request;
use WP_REST_Response;

final class OverviewController extends BaseController {

	public function __construct(
		private readonly HealthScoreRepository $health,
		private readonly GscDailyTotalsRepository $totals,
		private readonly GscPageDailyRepository $pages,
		private readonly OpportunitiesRepository $opportunities,
		private readonly AlertsRepository $alerts,
		private readonly JobStateRepository $jobs,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'days' => array(
						'type'              => 'integer',
						'default'           => 28,
						'minimum'           => 7,
						'maximum'           => 365,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		$days        = (int) $request->get_param( 'days' );
		$property_id = $this->active_property_id();
		$to          = gmdate( 'Y-m-d' );
		$from        = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$series      = $property_id ? $this->totals->series( $property_id, $from, $to ) : array();

		$trend       = new TrendAnalyzer();
		$clicks      = array_map( static fn( $r ) => (float) $r['clicks'], $series );
		$impressions = array_map( static fn( $r ) => (float) $r['impressions'], $series );

		$health = $this->health->latest();

		return new WP_REST_Response(
			array(
				'connected'     => null !== $property_id,
				'health'        => array(
					'score'      => $health['score'] ?? null,
					'date'       => $health['date'] ?? null,
					'components' => $health['components'] ?? array(),
					'history'    => $this->health->history( 90 ),
				),
				'totals'        => array(
					'clicks'      => (int) array_sum( $clicks ),
					'impressions' => (int) array_sum( $impressions ),
					'ctr'         => array_sum( $impressions ) > 0 ? round( array_sum( $clicks ) / array_sum( $impressions ), 4 ) : 0,
					'position'    => round( $trend->moving_average( array_map( static fn( $r ) => (float) $r['position'], $series ), 7 ), 1 ),
					'clicks_wow'  => $trend->wow( $clicks ),
					'clicks_mom'  => $trend->mom( $clicks ),
				),
				'series'        => $series,
				'top_pages'     => $property_id ? $this->pages->top_pages( $property_id, $from, $to, 10 ) : array(),
				'opportunities' => $this->opportunities->list_open( 10 ),
				'alerts'        => $this->alerts->list_active( 10 ),
				'sync'          => $this->jobs->all(),
			)
		);
	}

	private function active_property_id(): ?int {
		global $wpdb;
		$table = $wpdb->prefix . 'sda_properties';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE site_id = %d AND service = 'gsc' AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			)
		);
		return null === $id ? null : (int) $id;
	}
}

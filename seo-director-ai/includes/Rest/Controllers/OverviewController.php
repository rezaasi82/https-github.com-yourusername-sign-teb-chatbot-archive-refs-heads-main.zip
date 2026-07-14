<?php
/**
 * GET /sda/v1/overview — dashboard bootstrap payload.
 *
 * Phase 0: serves connection state and empty/demo series so the SPA renders
 * end-to-end. Phase 1+ replaces the data sources with rollup repositories;
 * the response contract is stable from day one.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Core\Schema;

defined( 'ABSPATH' ) || exit;

final class OverviewController extends AbstractController {

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
		global $wpdb;

		$connections_table = Schema::table( 'connections' );
		$connected         = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT service FROM {$connections_table} WHERE site_id = %d AND status = 'connected'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			)
		);

		return rest_ensure_response(
			[
				'connections' => [
					'gsc' => in_array( 'gsc', $connected, true ),
					'ga4' => in_array( 'ga4', $connected, true ),
					'psi' => in_array( 'psi', $connected, true ),
					'ai'  => (bool) array_intersect( [ 'openai', 'claude', 'gemini' ], $connected ),
				],
				'health'      => null,   // { score, band, delta, components } once analyzers land.
				'traffic'     => [
					'series'  => [],     // [{date, clicks, impressions, ctr, position}]
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
					'backfill'       => null, // { progress: 0-100 } while initial sync runs.
				],
			]
		);
	}
}

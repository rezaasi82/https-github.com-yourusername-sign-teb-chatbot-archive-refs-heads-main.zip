<?php
/**
 * POST /sda/v1/insights/explain — on-demand AI explanation / root cause for
 * an entity. Builds the evidence packet deterministically, then asks the AI
 * layer to narrate and rank. Rate-limited; returns cached insights for free.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Ai\InsightService;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Analysis\RootCause\CauseCandidateEngine;
use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class InsightsController extends AbstractController {

	public function __construct(
		private InsightService $insights,
		private MoversRepository $movers,
		private PropertiesRepository $properties,
		private GscRepository $gsc,
		private CauseCandidateEngine $causes,
		private ChangepointDetector $changepoints,
		private GrowthDetector $growth,
		private DeclineDetector $decline,
		private RateLimiter $limiter,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/insights/explain',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'explain' ],
				'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
				'args'                => [
					'entity'  => [ 'type' => 'string', 'required' => true, 'enum' => [ 'query', 'page' ] ],
					'hash'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'kind'    => [ 'type' => 'string', 'default' => 'root_cause', 'enum' => [ 'root_cause', 'growth', 'decline' ] ],
				],
			]
		);
	}

	public function explain( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->insights->is_available() ) {
			return new \WP_Error( 'sda_ai_off', __( 'AI features are not available. Configure a provider and check your token budget.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		if ( ! $this->limiter->allow( 'insights_explain', 30, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many explanation requests. Please try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return new \WP_Error( 'sda_no_property', __( 'No Search Console property selected.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$entity = (string) $request->get_param( 'entity' );
		$hash   = strtoupper( (string) $request->get_param( 'hash' ) );

		$row = $this->find_row( $entity, $property['id'], $hash );
		if ( null === $row ) {
			return new \WP_Error( 'sda_not_found', __( 'Entity not found in the current period.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$kind = (string) $request->get_param( 'kind' );

		if ( 'root_cause' === $kind ) {
			$series = $this->daily_series( $entity, $property['id'], $hash );
			$drop   = $this->changepoints->detect( $series );
			// SERP enrichment only applies to query entities.
			$flags    = 'query' === $entity ? [ 'serp_query' => $row->label ] : [];
			$evidence = $this->causes->build( $row, $drop['date'] ?? null, $flags );
		} else {
			$evidence = 'growth' === $kind
				? ( $this->growth->detect( [ $row ], 1 )[0] ?? [] )
				: ( $this->decline->detect( [ $row ], 1 )[0] ?? [] );
		}

		$result = $this->insights->generate(
			$kind,
			$evidence,
			[
				'entity_type'     => $entity,
				'entity_hash_hex' => $hash,
				'entity_label'    => $row->label,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	private function find_row( string $entity, int $property_id, string $hash ) {
		$cur_to    = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$cur_from  = gmdate( 'Y-m-d', strtotime( $cur_to . ' -27 days' ) );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $cur_from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -27 days' ) );

		foreach ( $this->movers->period_aggregates( $entity, $property_id, $cur_from, $cur_to, $prev_from, $prev_to, 1000 ) as $row ) {
			if ( strtoupper( $row->hash_hex ) === $hash ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @return array<string, float>
	 */
	private function daily_series( string $entity, int $property_id, string $hash ): array {
		global $wpdb;

		$table    = \SEODirector\Core\Schema::table( "gsc_{$entity}_daily" );
		$hash_col = "{$entity}_hash";
		$rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT date, clicks FROM {$table} WHERE property_id = %d AND {$hash_col} = UNHEX(%s) AND date >= %s ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$hash,
				gmdate( 'Y-m-d', strtotime( '-56 days' ) )
			),
			ARRAY_A
		);

		$series = [];
		foreach ( $rows ?: [] as $r ) {
			$series[ (string) $r['date'] ] = (float) $r['clicks'];
		}

		return $series;
	}
}

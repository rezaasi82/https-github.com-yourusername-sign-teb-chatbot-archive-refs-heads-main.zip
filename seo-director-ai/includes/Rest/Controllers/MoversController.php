<?php
/**
 * /sda/v1/winners and /sda/v1/losers — biggest movers from weekly rollups.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Analysis\MoverAnalyzer;
use SEODirector\Data\Repository\GscRollupRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use WP_REST_Request;
use WP_REST_Response;

final class MoversController extends BaseController {

	public function __construct(
		private readonly GscRollupRepository $rollups,
		private readonly PropertiesRepository $properties,
		private readonly MoverAnalyzer $analyzer,
	) {}

	public function register(): void {
		foreach ( array( 'winners', 'losers' ) as $route ) {
			register_rest_route(
				$this->ns(),
				'/' . $route,
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_movers' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'dimension' => array(
							'type'    => 'string',
							'default' => 'query',
							'enum'    => array( 'query', 'page' ),
						),
					),
				)
			);
		}
	}

	public function get_movers( WP_REST_Request $request ): WP_REST_Response {
		$route     = trim( (string) wp_parse_url( $request->get_route(), PHP_URL_PATH ), '/' );
		$direction = str_ends_with( $route, 'losers' ) ? 'losers' : 'winners';
		$dimension = (string) $request->get_param( 'dimension' );

		$property = $this->properties->active_property( 'gsc' );
		if ( ! $property ) {
			return new WP_REST_Response( array( $direction => array(), 'period' => null ) );
		}

		$buckets = $this->rollups->latest_buckets( $dimension, 'weekly', (int) $property->id );
		if ( empty( $buckets['recent'] ) || empty( $buckets['previous'] ) ) {
			return new WP_REST_Response( array( $direction => array(), 'period' => null ) );
		}

		$comparison = $this->rollups->comparison(
			$dimension,
			'weekly',
			(int) $property->id,
			(string) $buckets['recent'],
			(string) $buckets['previous']
		);
		$movers = $this->analyzer->movers( $comparison['recent'], $comparison['previous'], 25 );

		return new WP_REST_Response(
			array(
				$direction => $movers[ $direction ],
				'period'   => array(
					'recent'   => $buckets['recent'],
					'previous' => $buckets['previous'],
				),
			)
		);
	}
}

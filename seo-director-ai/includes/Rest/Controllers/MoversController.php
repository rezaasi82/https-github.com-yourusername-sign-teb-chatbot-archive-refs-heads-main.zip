<?php
/**
 * GET /sda/v1/winners and /sda/v1/losers — period-over-period movers with
 * deterministic reasons/causes, computed from local daily fact tables.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class MoversController extends AbstractController {

	public function __construct(
		private MoversRepository $movers,
		private PropertiesRepository $properties,
		private GrowthDetector $growth,
		private DeclineDetector $decline,
	) {}

	public function register_routes(): void {
		foreach ( [ 'winners', 'losers' ] as $kind ) {
			register_rest_route(
				self::REST_NAMESPACE,
				'/' . $kind,
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn( \WP_REST_Request $r ) => $this->get_movers( $kind, $r ),
					'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
					'args'                => [
						'entity' => [ 'type' => 'string', 'default' => 'query', 'enum' => [ 'query', 'page' ] ],
						'days'   => [ 'type' => 'integer', 'default' => 28, 'minimum' => 7, 'maximum' => 90 ],
					],
				]
			);
		}
	}

	public function get_movers( string $kind, \WP_REST_Request $request ): \WP_REST_Response {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return rest_ensure_response( [ 'items' => [], 'period' => null ] );
		}

		$entity = (string) $request->get_param( 'entity' );
		$days   = (int) $request->get_param( 'days' );

		$cur_to    = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$cur_from  = gmdate( 'Y-m-d', strtotime( $cur_to . ' -' . ( $days - 1 ) . ' days' ) );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $cur_from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to . ' -' . ( $days - 1 ) . ' days' ) );

		$rows = $this->movers->period_aggregates( $entity, $property['id'], $cur_from, $cur_to, $prev_from, $prev_to );

		$items = 'winners' === $kind
			? $this->growth->detect( $rows )
			: $this->decline->detect( $rows );

		return rest_ensure_response(
			[
				'items'  => $items,
				'period' => [
					'current'  => [ $cur_from, $cur_to ],
					'previous' => [ $prev_from, $prev_to ],
				],
			]
		);
	}
}

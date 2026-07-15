<?php
/**
 * Registers all REST controllers.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest;

use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Core\Container;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Jobs\Handlers\DailySyncCoordinator;
use SEODirector\Rest\Controllers\AlertsController;
use SEODirector\Rest\Controllers\ConnectionsController;
use SEODirector\Rest\Controllers\MetricsController;
use SEODirector\Rest\Controllers\MoversController;
use SEODirector\Rest\Controllers\OpportunitiesController;
use SEODirector\Rest\Controllers\OverviewController;
use SEODirector\Rest\Controllers\SettingsController;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class RestServiceProvider {

	public function __construct( private Container $container ) {}

	public function register_routes(): void {
		$c = $this->container;

		$controllers = [
			new OverviewController(
				$c->get( ConnectionsRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( JobStateRepository::class ),
				$c->get( HealthScoreRepository::class ),
				$c->get( OpportunitiesRepository::class ),
				$c->get( AlertsRepository::class )
			),
			new MoversController(
				$c->get( MoversRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( GrowthDetector::class ),
				$c->get( DeclineDetector::class )
			),
			new OpportunitiesController( $c->get( OpportunitiesRepository::class ) ),
			new AlertsController( $c->get( AlertsRepository::class ) ),
			new MetricsController(
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class )
			),
			new ConnectionsController(
				$c->get( ConnectionsRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( OAuthClient::class ),
				$c->get( SearchConsoleClient::class ),
				$c->get( Analytics4Client::class ),
				$c->get( JobStateRepository::class ),
				$c->get( DailySyncCoordinator::class )
			),
			new SettingsController( $c->get( Settings::class ) ),
		];

		/**
		 * Filters the REST controllers to register (add-on extension point).
		 *
		 * @param array     $controllers Controller instances exposing register_routes().
		 * @param Container $container   Plugin container.
		 */
		$controllers = apply_filters( 'sda_rest_controllers', $controllers, $this->container );

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}

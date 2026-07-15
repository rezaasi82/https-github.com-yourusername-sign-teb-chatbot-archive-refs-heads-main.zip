<?php
/**
 * Registers all sda/v1 REST routes.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Container;
use SEODirector\Core\Options;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\GscPageDailyRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Jobs\Scheduler;
use SEODirector\Rest\Controllers\ConnectionsController;
use SEODirector\Rest\Controllers\OpportunitiesController;
use SEODirector\Rest\Controllers\OverviewController;
use SEODirector\Rest\Controllers\SettingsController;

final class RestServiceProvider {

	public const NAMESPACE = 'sda/v1';

	public function __construct( private readonly Container $container ) {}

	public function register_routes(): void {
		$c = $this->container;

		( new OverviewController(
			$c->get( HealthScoreRepository::class ),
			$c->get( GscDailyTotalsRepository::class ),
			$c->get( GscPageDailyRepository::class ),
			$c->get( OpportunitiesRepository::class ),
			$c->get( AlertsRepository::class ),
			$c->get( JobStateRepository::class )
		) )->register();

		( new OpportunitiesController(
			$c->get( OpportunitiesRepository::class ),
			$c->get( Scheduler::class )
		) )->register();

		( new ConnectionsController(
			$c->get( ConnectionsRepository::class ),
			$c->get( OAuthClient::class ),
			$c->get( Scheduler::class )
		) )->register();

		( new SettingsController( $c->get( Options::class ) ) )->register();
	}
}

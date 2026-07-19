<?php
/**
 * Registers all sda/v1 REST routes.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest;

defined( 'ABSPATH' ) || exit;

use SEODirector\Ai\InsightGenerator;
use SEODirector\Analysis\MoverAnalyzer;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Core\Container;
use SEODirector\Core\Options;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\GscPageDailyRepository;
use SEODirector\Data\Repository\GscRollupRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\PsiAuditsRepository;
use SEODirector\Data\Repository\RoadmapTaskRepository;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Jobs\Scheduler;
use SEODirector\Roadmap\RoadmapGenerator;
use SEODirector\Rest\Controllers\AlertsController;
use SEODirector\Rest\Controllers\ConnectionsController;
use SEODirector\Rest\Controllers\InsightsController;
use SEODirector\Rest\Controllers\MoversController;
use SEODirector\Rest\Controllers\OpportunitiesController;
use SEODirector\Rest\Controllers\OverviewController;
use SEODirector\Rest\Controllers\PropertiesController;
use SEODirector\Rest\Controllers\RoadmapController;
use SEODirector\Rest\Controllers\SettingsController;
use SEODirector\Rest\Controllers\VitalsController;

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

		( new PropertiesController(
			$c->get( PropertiesRepository::class ),
			$c->get( ConnectionsRepository::class ),
			$c->get( SearchConsoleClient::class ),
			$c->get( Analytics4Client::class )
		) )->register();

		( new VitalsController(
			$c->get( PsiAuditsRepository::class ),
			$c->get( Scheduler::class )
		) )->register();

		( new AlertsController( $c->get( AlertsRepository::class ) ) )->register();

		( new MoversController(
			$c->get( GscRollupRepository::class ),
			$c->get( PropertiesRepository::class ),
			$c->get( MoverAnalyzer::class )
		) )->register();

		( new InsightsController(
			$c->get( InsightGenerator::class ),
			$c->get( PropertiesRepository::class ),
			$c->get( GscDailyTotalsRepository::class ),
			$c->get( TrendAnalyzer::class )
		) )->register();

		( new RoadmapController(
			$c->get( RoadmapTaskRepository::class ),
			$c->get( RoadmapGenerator::class )
		) )->register();

		( new SettingsController( $c->get( Options::class ) ) )->register();
	}
}

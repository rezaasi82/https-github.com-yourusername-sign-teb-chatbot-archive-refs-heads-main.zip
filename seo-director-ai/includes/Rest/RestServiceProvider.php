<?php
/**
 * Registers all REST controllers.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest;

use SEODirector\Agency\SiteConnector;
use SEODirector\Ai\InsightService;
use SEODirector\Ai\TokenBudget;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Analysis\RootCause\CauseCandidateEngine;
use SEODirector\Core\Container;
use SEODirector\Data\Repository\AgencySitesRepository;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\InsightRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\TaskRepository;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Integrations\Google\BusinessProfileClient;
use SEODirector\Integrations\Google\GoogleAdsClient;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Integrations\Google\TrendsClient;
use SEODirector\Jobs\Handlers\DailySyncCoordinator;
use SEODirector\Roadmap\RoadmapGenerator;
use SEODirector\Content\BriefGenerator;
use SEODirector\Content\ContentStrategist;
use SEODirector\Content\InternalLinkSuggester;
use SEODirector\Content\OnPageAuditor;
use SEODirector\Content\OptimizationScorer;
use SEODirector\Content\SchemaGenerator;
use SEODirector\License\FeatureGate;
use SEODirector\License\LicenseManager;
use SEODirector\Reports\ReportGenerator;
use SEODirector\Data\Repository\ReportsRepository;
use SEODirector\Rest\Controllers\AgencyController;
use SEODirector\Rest\Controllers\AlertsController;
use SEODirector\Rest\Controllers\ConnectionsController;
use SEODirector\Rest\Controllers\HubIngestController;
use SEODirector\Rest\Controllers\IntegrationsController;
use SEODirector\Rest\Controllers\ResearchController;
use SEODirector\Research\ClusterBuilder;
use SEODirector\Research\CompetitorAnalyzer;
use SEODirector\Research\KeywordResearcher;
use SEODirector\Rest\Controllers\ContentController;
use SEODirector\Rest\Controllers\InsightsController;
use SEODirector\Rest\Controllers\LicenseController;
use SEODirector\Rest\Controllers\MetricsController;
use SEODirector\Rest\Controllers\MoversController;
use SEODirector\Rest\Controllers\OpportunitiesController;
use SEODirector\Rest\Controllers\OverviewController;
use SEODirector\Rest\Controllers\ReportsController;
use SEODirector\Rest\Controllers\RoadmapController;
use SEODirector\Rest\Controllers\SettingsController;
use SEODirector\Support\RateLimiter;
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
				$c->get( AlertsRepository::class ),
				$c->get( InsightRepository::class ),
				$c->get( \SEODirector\Onboarding\DemoDataProvider::class ),
				$c->get( \SEODirector\Onboarding\SetupStatus::class ),
				$c->get( Settings::class )
			),
			new MoversController(
				$c->get( MoversRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( GrowthDetector::class ),
				$c->get( DeclineDetector::class )
			),
			new OpportunitiesController( $c->get( OpportunitiesRepository::class ) ),
			new AlertsController( $c->get( AlertsRepository::class ) ),
			new RoadmapController(
				$c->get( TaskRepository::class ),
				$c->get( RoadmapGenerator::class ),
				$c->get( \SEODirector\Integrations\TaskSync\TaskSyncDispatcher::class )
			),
			new InsightsController(
				$c->get( InsightService::class ),
				$c->get( MoversRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( CauseCandidateEngine::class ),
				$c->get( ChangepointDetector::class ),
				$c->get( GrowthDetector::class ),
				$c->get( DeclineDetector::class ),
				$c->get( RateLimiter::class )
			),
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
			new SettingsController(
				$c->get( Settings::class ),
				$c->get( TokenBudget::class ),
				$c->get( InsightService::class )
			),
			new LicenseController(
				$c->get( LicenseManager::class ),
				$c->get( FeatureGate::class ),
				$c->get( RateLimiter::class )
			),
			new ContentController(
				$c->get( ContentStrategist::class ),
				$c->get( BriefGenerator::class ),
				$c->get( InternalLinkSuggester::class ),
				$c->get( SchemaGenerator::class ),
				$c->get( OnPageAuditor::class ),
				$c->get( OptimizationScorer::class ),
				$c->get( FeatureGate::class ),
				$c->get( RateLimiter::class )
			),
			new ReportsController(
				$c->get( ReportsRepository::class ),
				$c->get( ReportGenerator::class )
			),
			new AgencyController(
				$c->get( AgencySitesRepository::class ),
				$c->get( FeatureGate::class )
			),
			new HubIngestController(
				$c->get( AgencySitesRepository::class ),
				$c->get( SiteConnector::class ),
				$c->get( FeatureGate::class ),
				$c->get( RateLimiter::class )
			),
			new IntegrationsController(
				$c->get( TrendsClient::class ),
				$c->get( BusinessProfileClient::class ),
				$c->get( GoogleAdsClient::class )
			),
			new ResearchController(
				$c->get( KeywordResearcher::class ),
				$c->get( ClusterBuilder::class ),
				$c->get( CompetitorAnalyzer::class ),
				$c->get( FeatureGate::class ),
				$c->get( RateLimiter::class )
			),
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

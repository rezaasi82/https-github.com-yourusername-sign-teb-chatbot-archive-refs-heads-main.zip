<?php
/**
 * Plugin orchestrator: wires services into the container and boots modules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

use SEODirector\Admin\AdminMenu;
use SEODirector\Admin\Assets;
use SEODirector\Alerts\AlertEngine;
use SEODirector\Alerts\Channels\EmailChannel;
use SEODirector\Alerts\Rules\CwvDegradationRule;
use SEODirector\Alerts\Rules\TrafficDropRule;
use SEODirector\Ai\InsightCache;
use SEODirector\Ai\ProviderRouter;
use SEODirector\Ai\SchemaValidator;
use SEODirector\Ai\TokenBudget;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\HealthScore\HealthScoreCalculator;
use SEODirector\Analysis\OpportunityDetector\LowCtrDetector;
use SEODirector\Analysis\OpportunityDetector\StrikingDistanceDetector;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\GscPageDailyRepository;
use SEODirector\Data\Repository\Ga4DailyRepository;
use SEODirector\Data\Repository\GscQueryDailyRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\PsiAuditsRepository;
use SEODirector\Data\Retention\RetentionPolicy;
use SEODirector\Data\Rollup\RollupBuilder;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Integrations\Google\OAuthClient;
use SEODirector\Integrations\Google\PageSpeedClient;
use SEODirector\Integrations\Google\QuotaManager;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Integrations\Google\TokenVault;
use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Handlers\RunPsiAuditJob;
use SEODirector\Jobs\Handlers\SyncGa4Job;
use SEODirector\Jobs\Handlers\SyncGscJob;
use SEODirector\Jobs\Handlers\WeeklyMaintenanceJob;
use SEODirector\Jobs\Scheduler;
use SEODirector\Rest\RestServiceProvider;

final class Plugin {

	private bool $booted = false;

	public function __construct( private readonly Container $container ) {
		$this->register_services();
	}

	public function container(): Container {
		return $this->container;
	}

	/**
	 * Boot on plugins_loaded: migrations, i18n, module hooks.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'seo-director-ai', false, dirname( plugin_basename( SDA_PLUGIN_FILE ) ) . '/languages' );

		( new Upgrader() )->maybe_upgrade();

		/** @var Scheduler $scheduler */
		$scheduler = $this->container->get( Scheduler::class );
		$scheduler->register_hooks();

		/** @var RestServiceProvider $rest */
		$rest = $this->container->get( RestServiceProvider::class );
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

		/** @var EmailChannel $email_channel */
		$email_channel = $this->container->get( EmailChannel::class );
		$email_channel->register();

		if ( is_admin() ) {
			/** @var AdminMenu $menu */
			$menu = $this->container->get( AdminMenu::class );
			add_action( 'admin_menu', array( $menu, 'register_menu' ) );

			/** @var Assets $assets */
			$assets = $this->container->get( Assets::class );
			add_action( 'admin_enqueue_scripts', array( $assets, 'maybe_enqueue' ) );
		}

		/**
		 * Fires once SEO Director AI has booted. Extensions register providers here.
		 *
		 * @param Container $container The DI container.
		 */
		do_action( 'sda_booted', $this->container );
	}

	/**
	 * All factories in one place — the composition root.
	 */
	private function register_services(): void {
		$c = $this->container;

		// Infrastructure.
		$c->set( Options::class, static fn() => new Options() );
		$c->set( RetryingHttpClient::class, static fn() => new RetryingHttpClient() );
		$c->set( TokenVault::class, static fn() => new TokenVault() );
		$c->set( QuotaManager::class, static fn() => new QuotaManager() );
		$c->set( UrlCanonicalizer::class, static fn() => new UrlCanonicalizer() );

		// Repositories.
		$c->set( ConnectionsRepository::class, static fn( $c ) => new ConnectionsRepository( $c->get( TokenVault::class ) ) );
		$c->set( PropertiesRepository::class, static fn() => new PropertiesRepository() );
		$c->set( Ga4DailyRepository::class, static fn() => new Ga4DailyRepository() );
		$c->set( PsiAuditsRepository::class, static fn() => new PsiAuditsRepository() );
		$c->set( JobStateRepository::class, static fn() => new JobStateRepository() );
		$c->set( GscDailyTotalsRepository::class, static fn() => new GscDailyTotalsRepository() );
		$c->set( GscQueryDailyRepository::class, static fn() => new GscQueryDailyRepository() );
		$c->set( GscPageDailyRepository::class, static fn() => new GscPageDailyRepository() );
		$c->set( HealthScoreRepository::class, static fn() => new HealthScoreRepository() );
		$c->set( OpportunitiesRepository::class, static fn() => new OpportunitiesRepository() );
		$c->set( AlertsRepository::class, static fn() => new AlertsRepository() );

		// Google integrations.
		$c->set(
			OAuthClient::class,
			static fn( $c ) => new OAuthClient(
				$c->get( RetryingHttpClient::class ),
				$c->get( TokenVault::class ),
				$c->get( ConnectionsRepository::class ),
				$c->get( Options::class )
			)
		);
		$c->set(
			SearchConsoleClient::class,
			static fn( $c ) => new SearchConsoleClient(
				$c->get( RetryingHttpClient::class ),
				$c->get( OAuthClient::class ),
				$c->get( QuotaManager::class )
			)
		);
		$c->set(
			Analytics4Client::class,
			static fn( $c ) => new Analytics4Client(
				$c->get( RetryingHttpClient::class ),
				$c->get( OAuthClient::class ),
				$c->get( QuotaManager::class )
			)
		);
		$c->set(
			PageSpeedClient::class,
			static fn( $c ) => new PageSpeedClient(
				$c->get( RetryingHttpClient::class ),
				$c->get( ConnectionsRepository::class ),
				$c->get( QuotaManager::class )
			)
		);

		// Rollups, retention, alerts.
		$c->set( RollupBuilder::class, static fn() => new RollupBuilder() );
		$c->set( RetentionPolicy::class, static fn( $c ) => new RetentionPolicy( $c->get( Options::class ) ) );
		$c->set(
			AlertEngine::class,
			static fn( $c ) => new AlertEngine(
				$c->get( AlertsRepository::class ),
				array(
					new TrafficDropRule(
						$c->get( PropertiesRepository::class ),
						$c->get( GscDailyTotalsRepository::class ),
						$c->get( TrendAnalyzer::class )
					),
					new CwvDegradationRule( $c->get( PsiAuditsRepository::class ) ),
				)
			)
		);

		// Deterministic analysis (pure, no I/O).
		$c->set( TrendAnalyzer::class, static fn() => new TrendAnalyzer() );
		$c->set( ChangepointDetector::class, static fn() => new ChangepointDetector() );
		$c->set( HealthScoreCalculator::class, static fn() => new HealthScoreCalculator() );
		$c->set( StrikingDistanceDetector::class, static fn() => new StrikingDistanceDetector() );
		$c->set( LowCtrDetector::class, static fn() => new LowCtrDetector() );

		// AI layer.
		$c->set( SchemaValidator::class, static fn() => new SchemaValidator() );
		$c->set( TokenBudget::class, static fn( $c ) => new TokenBudget( $c->get( Options::class ) ) );
		$c->set( InsightCache::class, static fn() => new InsightCache() );
		$c->set(
			ProviderRouter::class,
			static fn( $c ) => new ProviderRouter(
				$c->get( Options::class ),
				$c->get( RetryingHttpClient::class ),
				$c->get( ConnectionsRepository::class ),
				$c->get( SchemaValidator::class )
			)
		);

		// Jobs.
		$c->set(
			SyncGscJob::class,
			static fn( $c ) => new SyncGscJob(
				$c->get( SearchConsoleClient::class ),
				$c->get( ConnectionsRepository::class ),
				$c->get( JobStateRepository::class ),
				$c->get( GscDailyTotalsRepository::class ),
				$c->get( GscQueryDailyRepository::class ),
				$c->get( GscPageDailyRepository::class ),
				$c->get( UrlCanonicalizer::class )
			)
		);
		$c->set(
			RunAnalysisJob::class,
			static fn( $c ) => new RunAnalysisJob(
				$c->get( GscDailyTotalsRepository::class ),
				$c->get( GscQueryDailyRepository::class ),
				$c->get( HealthScoreCalculator::class ),
				$c->get( HealthScoreRepository::class ),
				$c->get( StrikingDistanceDetector::class ),
				$c->get( LowCtrDetector::class ),
				$c->get( OpportunitiesRepository::class )
			)
		);
		$c->set(
			SyncGa4Job::class,
			static fn( $c ) => new SyncGa4Job(
				$c->get( Analytics4Client::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( JobStateRepository::class ),
				$c->get( Ga4DailyRepository::class ),
				$c->get( UrlCanonicalizer::class )
			)
		);
		$c->set(
			RunPsiAuditJob::class,
			static fn( $c ) => new RunPsiAuditJob(
				$c->get( PageSpeedClient::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( GscPageDailyRepository::class ),
				$c->get( PsiAuditsRepository::class ),
				$c->get( JobStateRepository::class ),
				$c->get( UrlCanonicalizer::class )
			)
		);
		$c->set(
			WeeklyMaintenanceJob::class,
			static fn( $c ) => new WeeklyMaintenanceJob(
				$c->get( PropertiesRepository::class ),
				$c->get( RollupBuilder::class ),
				$c->get( RetentionPolicy::class )
			)
		);
		$c->set(
			Scheduler::class,
			static fn( $c ) => new Scheduler(
				$c->get( SyncGscJob::class ),
				$c->get( SyncGa4Job::class ),
				$c->get( RunAnalysisJob::class ),
				$c->get( RunPsiAuditJob::class ),
				$c->get( WeeklyMaintenanceJob::class ),
				$c->get( AlertEngine::class )
			)
		);

		// Presentation.
		$c->set( EmailChannel::class, static fn() => new EmailChannel() );
		$c->set( RestServiceProvider::class, static fn( $c ) => new RestServiceProvider( $c ) );
		$c->set( AdminMenu::class, static fn() => new AdminMenu() );
		$c->set( Assets::class, static fn() => new Assets() );
	}
}

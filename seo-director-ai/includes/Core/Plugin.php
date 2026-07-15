<?php
/**
 * Composition root: wires the container and boots modules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

use SEODirector\Admin\AdminMenu;
use SEODirector\Admin\Assets;
use SEODirector\Ai\InsightCache;
use SEODirector\Ai\InsightService;
use SEODirector\Ai\ProviderRouter;
use SEODirector\Ai\PromptLibrary;
use SEODirector\Ai\Providers\ClaudeProvider;
use SEODirector\Ai\Providers\GeminiProvider;
use SEODirector\Ai\Providers\OpenAiProvider;
use SEODirector\Ai\SchemaValidator;
use SEODirector\Ai\TokenBudget;
use SEODirector\Alerts\AlertEngine;
use SEODirector\Alerts\Channels\EmailChannel;
use SEODirector\Alerts\Rules\CwvRegressionRule;
use SEODirector\Alerts\Rules\KeywordLossRule;
use SEODirector\Alerts\Rules\TrafficDropRule;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Analysis\HealthScore\HealthScoreCalculator;
use SEODirector\Analysis\OpportunityDetector\LowCtrDetector;
use SEODirector\Analysis\OpportunityDetector\NearTopDetector;
use SEODirector\Analysis\OpportunityDetector\StrikingDistanceDetector;
use SEODirector\Analysis\RootCause\CauseCandidateEngine;
use SEODirector\Analysis\RootCause\CoreUpdateCalendar;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Data\Repository\AlertsRepository;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\Ga4Repository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\HealthScoreRepository;
use SEODirector\Data\Repository\InsightRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\MoversRepository;
use SEODirector\Data\Repository\OpportunitiesRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\PsiRepository;
use SEODirector\Data\Repository\TaskRepository;
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
use SEODirector\Jobs\Handlers\DailySyncCoordinator;
use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Handlers\RunPsiAuditJob;
use SEODirector\Jobs\Handlers\WeeklyIntelligence;
use SEODirector\Roadmap\RoadmapGenerator;
use SEODirector\Support\RateLimiter;
use SEODirector\Jobs\Handlers\SyncGa4Job;
use SEODirector\Jobs\Handlers\SyncGscJob;
use SEODirector\Jobs\Scheduler;
use SEODirector\Rest\RestServiceProvider;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private Container $container;

	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function container(): Container {
		return $this->container;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->register_services();

		load_plugin_textdomain( 'seo-director-ai', false, dirname( plugin_basename( SDA_PLUGIN_FILE ) ) . '/languages' );

		( new Upgrader() )->maybe_upgrade();

		/** @var Scheduler $scheduler */
		$scheduler = $this->container->get( Scheduler::class );
		$scheduler->register_hooks();

		/** @var RestServiceProvider $rest */
		$rest = $this->container->get( RestServiceProvider::class );
		add_action( 'rest_api_init', [ $rest, 'register_routes' ] );

		if ( is_admin() ) {
			/** @var AdminMenu $menu */
			$menu = $this->container->get( AdminMenu::class );
			add_action( 'admin_menu', [ $menu, 'register' ] );

			/** @var Assets $assets */
			$assets = $this->container->get( Assets::class );
			add_action( 'admin_enqueue_scripts', [ $assets, 'enqueue' ] );
		}

		/**
		 * Fires once SEO Director AI has finished booting.
		 *
		 * @param Plugin $plugin The plugin instance (container access for extensions).
		 */
		do_action( 'sda_booted', $this );
	}

	private function register_services(): void {
		$c = $this->container;

		$c->set( Settings::class, static fn() => new Settings() );
		$c->set( TokenVault::class, static fn() => new TokenVault() );
		$c->set( RetryingHttpClient::class, static fn() => new RetryingHttpClient() );
		$c->set( QuotaManager::class, static fn() => new QuotaManager() );
		$c->set( UrlCanonicalizer::class, static fn() => new UrlCanonicalizer() );

		// Repositories.
		$c->set( JobStateRepository::class, static fn() => new JobStateRepository() );
		$c->set( ConnectionsRepository::class, static fn( Container $c ) => new ConnectionsRepository( $c->get( TokenVault::class ) ) );
		$c->set( PropertiesRepository::class, static fn() => new PropertiesRepository() );
		$c->set( GscRepository::class, static fn() => new GscRepository() );
		$c->set( Ga4Repository::class, static fn() => new Ga4Repository() );
		$c->set( PsiRepository::class, static fn() => new PsiRepository() );
		$c->set( RollupBuilder::class, static fn() => new RollupBuilder() );
		$c->set( RetentionPolicy::class, static fn() => new RetentionPolicy() );

		// Google integration.
		$c->set( OAuthClient::class, static fn( Container $c ) => new OAuthClient( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set( SearchConsoleClient::class, static fn( Container $c ) => new SearchConsoleClient( $c->get( OAuthClient::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ) ) );
		$c->set( Analytics4Client::class, static fn( Container $c ) => new Analytics4Client( $c->get( OAuthClient::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ) ) );
		$c->set( PageSpeedClient::class, static fn( Container $c ) => new PageSpeedClient( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ) ) );

		// Analysis layer (pure).
		$c->set( TrendAnalyzer::class, static fn() => new TrendAnalyzer() );
		$c->set( ChangepointDetector::class, static fn( Container $c ) => new ChangepointDetector( $c->get( TrendAnalyzer::class ) ) );
		$c->set( ExpectedCtrCurve::class, static fn() => new ExpectedCtrCurve() );
		$c->set( GrowthDetector::class, static fn() => new GrowthDetector() );
		$c->set( DeclineDetector::class, static fn() => new DeclineDetector() );
		$c->set( HealthScoreCalculator::class, static fn( Container $c ) => new HealthScoreCalculator( $c->get( ExpectedCtrCurve::class ) ) );

		// Intelligence repositories.
		$c->set( MoversRepository::class, static fn() => new MoversRepository() );
		$c->set( OpportunitiesRepository::class, static fn() => new OpportunitiesRepository() );
		$c->set( AlertsRepository::class, static fn() => new AlertsRepository() );
		$c->set( HealthScoreRepository::class, static fn() => new HealthScoreRepository() );
		$c->set( InsightRepository::class, static fn() => new InsightRepository() );
		$c->set( TaskRepository::class, static fn() => new TaskRepository() );
		$c->set( RateLimiter::class, static fn() => new RateLimiter() );

		// Root cause + roadmap.
		$c->set( CoreUpdateCalendar::class, static fn() => new CoreUpdateCalendar() );
		$c->set( CauseCandidateEngine::class, static fn( Container $c ) => new CauseCandidateEngine( $c->get( CoreUpdateCalendar::class ) ) );
		$c->set( RoadmapGenerator::class, static fn( Container $c ) => new RoadmapGenerator( $c->get( OpportunitiesRepository::class ), $c->get( TaskRepository::class ) ) );

		// AI layer.
		$c->set( SchemaValidator::class, static fn() => new SchemaValidator() );
		$c->set( PromptLibrary::class, static fn() => new PromptLibrary() );
		$c->set( TokenBudget::class, static fn( Container $c ) => new TokenBudget( $c->get( Settings::class ) ) );
		$c->set( InsightCache::class, static fn( Container $c ) => new InsightCache( $c->get( InsightRepository::class ) ) );
		$c->set( ClaudeProvider::class, static fn( Container $c ) => new ClaudeProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set( OpenAiProvider::class, static fn( Container $c ) => new OpenAiProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set( GeminiProvider::class, static fn( Container $c ) => new GeminiProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set(
			ProviderRouter::class,
			static fn( Container $c ) => new ProviderRouter(
				[
					'claude' => $c->get( ClaudeProvider::class ),
					'openai' => $c->get( OpenAiProvider::class ),
					'gemini' => $c->get( GeminiProvider::class ),
				],
				$c->get( SchemaValidator::class ),
				$c->get( TokenBudget::class ),
				$c->get( Settings::class )
			)
		);
		$c->set(
			InsightService::class,
			static fn( Container $c ) => new InsightService(
				$c->get( ProviderRouter::class ),
				$c->get( PromptLibrary::class ),
				$c->get( InsightCache::class ),
				$c->get( InsightRepository::class ),
				$c->get( Settings::class )
			)
		);
		$c->set(
			WeeklyIntelligence::class,
			static fn( Container $c ) => new WeeklyIntelligence(
				$c->get( RoadmapGenerator::class ),
				$c->get( InsightService::class ),
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( TrendAnalyzer::class )
			)
		);

		// Alerts.
		$c->set( EmailChannel::class, static fn( Container $c ) => new EmailChannel( $c->get( Settings::class ) ) );
		$c->set(
			AlertEngine::class,
			static fn( Container $c ) => new AlertEngine(
				[
					new TrafficDropRule( $c->get( GscRepository::class ), $c->get( PropertiesRepository::class ), $c->get( TrendAnalyzer::class ) ),
					new KeywordLossRule( $c->get( MoversRepository::class ), $c->get( PropertiesRepository::class ) ),
					new CwvRegressionRule(),
				],
				$c->get( AlertsRepository::class ),
				$c->get( EmailChannel::class )
			)
		);

		// Jobs.
		$c->set(
			RunAnalysisJob::class,
			static fn( Container $c ) => new RunAnalysisJob(
				$c->get( JobStateRepository::class ),
				[
					new StrikingDistanceDetector( $c->get( ExpectedCtrCurve::class ) ),
					new LowCtrDetector( $c->get( ExpectedCtrCurve::class ) ),
					new NearTopDetector( $c->get( ExpectedCtrCurve::class ) ),
				],
				$c->get( MoversRepository::class ),
				$c->get( OpportunitiesRepository::class ),
				$c->get( HealthScoreCalculator::class ),
				$c->get( HealthScoreRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( TrendAnalyzer::class ),
				$c->get( AlertEngine::class )
			)
		);
		$c->set(
			SyncGscJob::class,
			static fn( Container $c ) => new SyncGscJob(
				$c->get( JobStateRepository::class ),
				$c->get( SearchConsoleClient::class ),
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( RollupBuilder::class ),
				$c->get( UrlCanonicalizer::class ),
				$c->get( Settings::class )
			)
		);
		$c->set(
			SyncGa4Job::class,
			static fn( Container $c ) => new SyncGa4Job(
				$c->get( JobStateRepository::class ),
				$c->get( Analytics4Client::class ),
				$c->get( Ga4Repository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( UrlCanonicalizer::class )
			)
		);
		$c->set(
			RunPsiAuditJob::class,
			static fn( Container $c ) => new RunPsiAuditJob(
				$c->get( JobStateRepository::class ),
				$c->get( PageSpeedClient::class ),
				$c->get( PsiRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( UrlCanonicalizer::class )
			)
		);
		$c->set(
			DailySyncCoordinator::class,
			static fn( Container $c ) => new DailySyncCoordinator(
				$c->get( JobStateRepository::class ),
				$c->get( SyncGscJob::class ),
				$c->get( SyncGa4Job::class ),
				$c->get( RetentionPolicy::class )
			)
		);

		add_filter(
			'sda_register_jobs',
			static fn( array $jobs ) => array_merge(
				$jobs,
				[
					SyncGscJob::NAME     => $c->get( SyncGscJob::class ),
					SyncGa4Job::NAME     => $c->get( SyncGa4Job::class ),
					RunPsiAuditJob::NAME => $c->get( RunPsiAuditJob::class ),
					RunAnalysisJob::NAME => $c->get( RunAnalysisJob::class ),
				]
			)
		);

		add_action( Scheduler::HOOK_PREFIX . 'daily_sync', static fn() => $c->get( DailySyncCoordinator::class )->run_daily() );
		add_action( Scheduler::HOOK_PREFIX . 'weekly_pipeline', static fn() => $c->get( DailySyncCoordinator::class )->run_weekly() );

		// Every completed data sync triggers a fresh analysis pass; the hourly
		// schedule re-evaluates alerts between syncs.
		add_action( 'sda_sync_completed', static fn() => Scheduler::enqueue_next_chunk( RunAnalysisJob::NAME ) );
		add_action( Scheduler::HOOK_PREFIX . 'hourly_alerts', static fn() => $c->get( AlertEngine::class )->evaluate() );

		// Weekly intelligence: roadmap regeneration + AI weekly summary.
		add_action( Scheduler::HOOK_PREFIX . 'weekly_pipeline', static fn() => $c->get( WeeklyIntelligence::class )->run() );

		$c->set( Scheduler::class, static fn( Container $c ) => new Scheduler( $c->get( JobStateRepository::class ) ) );
		$c->set( RestServiceProvider::class, static fn( Container $c ) => new RestServiceProvider( $c ) );
		$c->set( AdminMenu::class, static fn() => new AdminMenu() );
		$c->set( Assets::class, static fn( Container $c ) => new Assets( $c->get( Settings::class ) ) );

		/**
		 * Allows add-ons to register or override container services.
		 *
		 * @param Container $c The plugin container.
		 */
		do_action( 'sda_register_services', $c );
	}
}

<?php
/**
 * Composition root: wires the container and boots modules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

use SEODirector\Admin\AdminMenu;
use SEODirector\Admin\Assets;
use SEODirector\Agency\ClientAccess;
use SEODirector\Agency\SiteConnector;
use SEODirector\Agency\SnapshotBuilder;
use SEODirector\Agency\WhiteLabel;
use SEODirector\Ai\BrandVoice;
use SEODirector\Ai\InsightCache;
use SEODirector\Ai\InsightService;
use SEODirector\Ai\ProviderRouter;
use SEODirector\Ai\PromptLibrary;
use SEODirector\Ai\Providers\ClaudeProvider;
use SEODirector\Ai\Providers\GapGptProvider;
use SEODirector\Ai\Providers\GeminiProvider;
use SEODirector\Ai\Providers\OpenAiProvider;
use SEODirector\Ai\SchemaValidator;
use SEODirector\Ai\TokenBudget;
use SEODirector\Alerts\AlertEngine;
use SEODirector\Alerts\EscalationPolicy;
use SEODirector\Alerts\Channels\EmailChannel;
use SEODirector\Alerts\Channels\BaleChannel;
use SEODirector\Alerts\Channels\SlackChannel;
use SEODirector\Alerts\Channels\TelegramChannel;
use SEODirector\Alerts\Channels\WebhookChannel;
use SEODirector\Alerts\Rules\CwvRegressionRule;
use SEODirector\Alerts\Rules\KeywordLossRule;
use SEODirector\Alerts\Rules\TrafficDropRule;
use SEODirector\Analysis\Cannibalization\ClusterAnalyzer;
use SEODirector\Analysis\ChangepointDetector;
use SEODirector\Analysis\DeclineDetector;
use SEODirector\Analysis\ExpectedCtrCurve;
use SEODirector\Analysis\GrowthDetector;
use SEODirector\Analysis\HealthScore\HealthScoreCalculator;
use SEODirector\Analysis\InternalLinks\LinkGraphBuilder;
use SEODirector\Analysis\OpportunityDetector\FaqDetector;
use SEODirector\Analysis\OpportunityDetector\LowCtrDetector;
use SEODirector\Analysis\OpportunityDetector\NearTopDetector;
use SEODirector\Analysis\OpportunityDetector\SchemaDetector;
use SEODirector\Analysis\OpportunityDetector\SnippetDetector;
use SEODirector\Analysis\OpportunityDetector\StrikingDistanceDetector;
use SEODirector\Analysis\RootCause\CauseCandidateEngine;
use SEODirector\Analysis\RootCause\CoreUpdateCalendar;
use SEODirector\Integrations\Serp\SerpApiProvider;
use SEODirector\Analysis\TrendAnalyzer;
use SEODirector\Content\BriefGenerator;
use SEODirector\Content\ContentStrategist;
use SEODirector\Content\InternalLinkSuggester;
use SEODirector\Content\OnPageAuditor;
use SEODirector\Content\OptimizationScorer;
use SEODirector\Content\SchemaGenerator;
use SEODirector\Content\SchemaInjector;
use SEODirector\Content\ZombiePageDetector;
use SEODirector\Content\PenaltyRadarDetector;
use SEODirector\Medical\EeatAnalyzer;
use SEODirector\Medical\KnowledgeGraph;
use SEODirector\Medical\MedicalEntityEngine;
use SEODirector\Medical\MedicalSchemaBuilder;
use SEODirector\Research\AutocompleteClient;
use SEODirector\Research\ClusterBuilder;
use SEODirector\Research\CompetitorAnalyzer;
use SEODirector\Research\KeywordResearcher;
use SEODirector\Research\SerpClient as ResearchSerpClient;
use SEODirector\License\FeatureGate;
use SEODirector\License\GracePeriodHandler;
use SEODirector\License\LicenseManager;
use SEODirector\License\LicenseRepository;
use SEODirector\Reports\ReportBuilder;
use SEODirector\Reports\ReportGenerator;
use SEODirector\Reports\ReportScheduler;
use SEODirector\Reports\Renderers\CsvRenderer;
use SEODirector\Reports\Renderers\HtmlRenderer;
use SEODirector\Data\Repository\ReportsRepository;
use SEODirector\Data\Repository\AgencySitesRepository;
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
use SEODirector\Integrations\Google\BusinessProfileClient;
use SEODirector\Integrations\Google\GoogleAdsClient;
use SEODirector\Integrations\Google\PageSpeedClient;
use SEODirector\Integrations\Google\TrendsClient;
use SEODirector\Integrations\Google\QuotaManager;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Integrations\Google\TokenVault;
use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Integrations\TaskSync\JiraConnector;
use SEODirector\Integrations\TaskSync\TaskSyncDispatcher;
use SEODirector\Integrations\TaskSync\TrelloConnector;
use SEODirector\Jobs\Handlers\DailySyncCoordinator;
use SEODirector\Jobs\Handlers\HubPushJob;
use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Handlers\RunPsiAuditJob;
use SEODirector\Jobs\Handlers\WeeklyIntelligence;
use SEODirector\Onboarding\DemoDataProvider;
use SEODirector\Onboarding\SetupStatus;
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

		/** @var Updater $updater */
		$updater = $this->container->get( Updater::class );
		$updater->register();

		// Front-end JSON-LD output for posts with saved schema.
		$this->container->get( SchemaInjector::class )->register();

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
		$c->set( TrendsClient::class, static fn( Container $c ) => new TrendsClient( $c->get( RetryingHttpClient::class ) ) );
		$c->set( BusinessProfileClient::class, static fn( Container $c ) => new BusinessProfileClient( $c->get( OAuthClient::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ) ) );
		$c->set( GoogleAdsClient::class, static fn( Container $c ) => new GoogleAdsClient( $c->get( OAuthClient::class ), $c->get( Settings::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ) ) );

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

		// Onboarding / demo mode.
		$c->set( DemoDataProvider::class, static fn() => new DemoDataProvider() );
		$c->set( SetupStatus::class, static fn() => new SetupStatus() );

		// Licensing.
		$c->set( GracePeriodHandler::class, static fn() => new GracePeriodHandler() );
		$c->set( LicenseRepository::class, static fn( Container $c ) => new LicenseRepository( $c->get( TokenVault::class ) ) );
		$c->set(
			LicenseManager::class,
			static fn( Container $c ) => new LicenseManager(
				$c->get( LicenseRepository::class ),
				$c->get( RetryingHttpClient::class ),
				$c->get( GracePeriodHandler::class ),
				$c->get( Settings::class )
			)
		);
		$c->set( FeatureGate::class, static fn( Container $c ) => new FeatureGate( $c->get( LicenseManager::class ) ) );

		// Reports.
		$c->set( ReportsRepository::class, static fn() => new ReportsRepository() );
		$c->set( CsvRenderer::class, static fn() => new CsvRenderer() );
		$c->set( HtmlRenderer::class, static fn() => new HtmlRenderer() );
		$c->set(
			ReportBuilder::class,
			static fn( Container $c ) => new ReportBuilder(
				$c->get( PropertiesRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( HealthScoreRepository::class ),
				$c->get( MoversRepository::class ),
				$c->get( OpportunitiesRepository::class ),
				$c->get( AlertsRepository::class ),
				$c->get( InsightRepository::class ),
				$c->get( GrowthDetector::class ),
				$c->get( DeclineDetector::class )
			)
		);
		$c->set(
			ReportGenerator::class,
			static fn( Container $c ) => new ReportGenerator(
				$c->get( ReportBuilder::class ),
				[ 'html' => $c->get( HtmlRenderer::class ), 'csv' => $c->get( CsvRenderer::class ) ],
				$c->get( ReportsRepository::class ),
				$c->get( FeatureGate::class )
			)
		);
		$c->set(
			ReportScheduler::class,
			static fn( Container $c ) => new ReportScheduler( $c->get( ReportGenerator::class ), $c->get( Settings::class ), $c->get( FeatureGate::class ) )
		);

		// Agency (hub + client + white-label).
		$c->set( SiteConnector::class, static fn() => new SiteConnector() );
		$c->set( AgencySitesRepository::class, static fn( Container $c ) => new AgencySitesRepository( $c->get( TokenVault::class ) ) );
		$c->set( WhiteLabel::class, static fn( Container $c ) => new WhiteLabel( $c->get( Settings::class ), $c->get( FeatureGate::class ) ) );
		$c->set(
			SnapshotBuilder::class,
			static fn( Container $c ) => new SnapshotBuilder(
				$c->get( HealthScoreRepository::class ),
				$c->get( AlertsRepository::class ),
				$c->get( OpportunitiesRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class )
			)
		);
		$c->set(
			HubPushJob::class,
			static fn( Container $c ) => new HubPushJob(
				$c->get( SnapshotBuilder::class ),
				$c->get( SiteConnector::class ),
				$c->get( RetryingHttpClient::class ),
				$c->get( Settings::class )
			)
		);

		// Root cause + roadmap.
		$c->set( CoreUpdateCalendar::class, static fn() => new CoreUpdateCalendar() );
		$c->set( ClusterAnalyzer::class, static fn( Container $c ) => new ClusterAnalyzer( $c->get( OpportunitiesRepository::class ) ) );
		$c->set( LinkGraphBuilder::class, static fn( Container $c ) => new LinkGraphBuilder( $c->get( UrlCanonicalizer::class ) ) );
		$c->set( SerpApiProvider::class, static fn( Container $c ) => new SerpApiProvider( $c->get( Settings::class ), $c->get( FeatureGate::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set( CauseCandidateEngine::class, static fn( Container $c ) => new CauseCandidateEngine( $c->get( CoreUpdateCalendar::class ), $c->get( SerpApiProvider::class ) ) );
		$c->set( RoadmapGenerator::class, static fn( Container $c ) => new RoadmapGenerator( $c->get( OpportunitiesRepository::class ), $c->get( TaskRepository::class ) ) );

		// Enterprise: task sync (Jira / Trello).
		$c->set( JiraConnector::class, static fn( Container $c ) => new JiraConnector( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set( TrelloConnector::class, static fn( Container $c ) => new TrelloConnector( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set(
			TaskSyncDispatcher::class,
			static fn( Container $c ) => new TaskSyncDispatcher(
				[
					'jira'   => $c->get( JiraConnector::class ),
					'trello' => $c->get( TrelloConnector::class ),
				],
				$c->get( TaskRepository::class ),
				$c->get( Settings::class ),
				$c->get( FeatureGate::class )
			)
		);

		// AI layer.
		$c->set( SchemaValidator::class, static fn() => new SchemaValidator() );
		$c->set( PromptLibrary::class, static fn() => new PromptLibrary() );
		$c->set( TokenBudget::class, static fn( Container $c ) => new TokenBudget( $c->get( Settings::class ) ) );
		$c->set( InsightCache::class, static fn( Container $c ) => new InsightCache( $c->get( InsightRepository::class ) ) );
		$c->set( BrandVoice::class, static fn( Container $c ) => new BrandVoice( $c->get( Settings::class ), $c->get( FeatureGate::class ) ) );
		$c->set( ClaudeProvider::class, static fn( Container $c ) => new ClaudeProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set( OpenAiProvider::class, static fn( Container $c ) => new OpenAiProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set( GeminiProvider::class, static fn( Container $c ) => new GeminiProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set( GapGptProvider::class, static fn( Container $c ) => new GapGptProvider( $c->get( ConnectionsRepository::class ), $c->get( RetryingHttpClient::class ), $c->get( QuotaManager::class ), $c->get( Settings::class ) ) );
		$c->set(
			ProviderRouter::class,
			static fn( Container $c ) => new ProviderRouter(
				[
					'claude' => $c->get( ClaudeProvider::class ),
					'openai' => $c->get( OpenAiProvider::class ),
					'gemini' => $c->get( GeminiProvider::class ),
					'gapgpt' => $c->get( GapGptProvider::class ),
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
				$c->get( Settings::class ),
				$c->get( BrandVoice::class )
			)
		);
		$c->set(
			ContentStrategist::class,
			static fn( Container $c ) => new ContentStrategist( $c->get( InsightService::class ), $c->get( PropertiesRepository::class ) )
		);
		$c->set(
			BriefGenerator::class,
			static fn( Container $c ) => new BriefGenerator( $c->get( InsightService::class ), $c->get( PropertiesRepository::class ) )
		);
		$c->set( InternalLinkSuggester::class, static fn() => new InternalLinkSuggester() );
		$c->set( SchemaGenerator::class, static fn() => new SchemaGenerator() );

		// Medical Pack (Wave 3).
		$c->set( MedicalEntityEngine::class, static fn( Container $c ) => new MedicalEntityEngine( $c->get( Settings::class ) ) );
		$c->set( EeatAnalyzer::class, static fn( Container $c ) => new EeatAnalyzer( $c->get( MedicalEntityEngine::class ) ) );
		$c->set( MedicalSchemaBuilder::class, static fn( Container $c ) => new MedicalSchemaBuilder( $c->get( Settings::class ), $c->get( MedicalEntityEngine::class ) ) );
		$c->set( KnowledgeGraph::class, static fn( Container $c ) => new KnowledgeGraph( $c->get( MedicalEntityEngine::class ) ) );

		$c->set( SchemaInjector::class, static fn( Container $c ) => new SchemaInjector( $c->get( Settings::class ), $c->get( MedicalSchemaBuilder::class ) ) );
		$c->set( OnPageAuditor::class, static fn() => new OnPageAuditor() );
		$c->set( OptimizationScorer::class, static fn( Container $c ) => new OptimizationScorer( $c->get( InsightService::class ) ) );
		$c->set( ZombiePageDetector::class, static fn( Container $c ) => new ZombiePageDetector( $c->get( UrlCanonicalizer::class ), $c->get( PropertiesRepository::class ) ) );
		$c->set( PenaltyRadarDetector::class, static fn( Container $c ) => new PenaltyRadarDetector( $c->get( UrlCanonicalizer::class ), $c->get( PropertiesRepository::class ), $c->get( ChangepointDetector::class ) ) );

		// Research module (Wave 2): keyword discovery, clusters, competitors.
		$c->set( AutocompleteClient::class, static fn( Container $c ) => new AutocompleteClient( $c->get( RetryingHttpClient::class ) ) );
		$c->set( ResearchSerpClient::class, static fn( Container $c ) => new ResearchSerpClient( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set(
			KeywordResearcher::class,
			static fn( Container $c ) => new KeywordResearcher(
				$c->get( AutocompleteClient::class ),
				$c->get( ResearchSerpClient::class ),
				$c->get( PropertiesRepository::class )
			)
		);
		$c->set(
			ClusterBuilder::class,
			static fn( Container $c ) => new ClusterBuilder( $c->get( InsightService::class ), $c->get( KeywordResearcher::class ) )
		);
		$c->set(
			CompetitorAnalyzer::class,
			static fn( Container $c ) => new CompetitorAnalyzer( $c->get( ResearchSerpClient::class ), $c->get( PropertiesRepository::class ) )
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
		$c->set( EscalationPolicy::class, static fn() => new EscalationPolicy() );
		$c->set( EmailChannel::class, static fn( Container $c ) => new EmailChannel( $c->get( Settings::class ) ) );
		$c->set( WebhookChannel::class, static fn( Container $c ) => new WebhookChannel( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set( SlackChannel::class, static fn( Container $c ) => new SlackChannel( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set( TelegramChannel::class, static fn( Container $c ) => new TelegramChannel( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set( BaleChannel::class, static fn( Container $c ) => new BaleChannel( $c->get( Settings::class ), $c->get( RetryingHttpClient::class ) ) );
		$c->set(
			AlertEngine::class,
			static fn( Container $c ) => new AlertEngine(
				[
					new TrafficDropRule( $c->get( GscRepository::class ), $c->get( PropertiesRepository::class ), $c->get( TrendAnalyzer::class ) ),
					new KeywordLossRule( $c->get( MoversRepository::class ), $c->get( PropertiesRepository::class ) ),
					new CwvRegressionRule(),
				],
				$c->get( AlertsRepository::class ),
				[
					$c->get( EmailChannel::class ),
					$c->get( WebhookChannel::class ),
					$c->get( SlackChannel::class ),
					$c->get( TelegramChannel::class ),
					$c->get( BaleChannel::class ),
				],
				$c->get( FeatureGate::class ),
				$c->get( EscalationPolicy::class ),
				$c->get( Settings::class )
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
					new FaqDetector( $c->get( ExpectedCtrCurve::class ) ),
					new SnippetDetector(),
					new SchemaDetector(),
				],
				$c->get( MoversRepository::class ),
				$c->get( OpportunitiesRepository::class ),
				$c->get( HealthScoreCalculator::class ),
				$c->get( HealthScoreRepository::class ),
				$c->get( GscRepository::class ),
				$c->get( PropertiesRepository::class ),
				$c->get( TrendAnalyzer::class ),
				$c->get( AlertEngine::class ),
				$c->get( FeatureGate::class ),
				$c->get( ClusterAnalyzer::class ),
				$c->get( LinkGraphBuilder::class )
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

		// Daily license re-verification against the licensing server (fail-soft, cached).
		add_action( Scheduler::HOOK_PREFIX . 'license_check', static fn() => $c->get( LicenseManager::class )->verify() );

		// Scheduled report delivery is evaluated on the daily tick; the scheduler
		// itself decides whether today matches the configured report day/time.
		add_action( Scheduler::HOOK_PREFIX . 'daily_sync', static fn() => $c->get( ReportScheduler::class )->maybe_run() );

		// Client sites push a snapshot to their agency hub once a day (no-op
		// unless a hub URL + pairing key are configured).
		add_action( Scheduler::HOOK_PREFIX . 'daily_sync', static fn() => $c->get( HubPushJob::class )->run() );

		// Every completed data sync triggers a fresh analysis pass; the hourly
		// schedule re-evaluates alerts between syncs.
		add_action( 'sda_sync_completed', static fn() => Scheduler::enqueue_next_chunk( RunAnalysisJob::NAME ) );
		add_action( Scheduler::HOOK_PREFIX . 'hourly_alerts', static fn() => $c->get( AlertEngine::class )->evaluate() );

		// Weekly intelligence: roadmap regeneration + AI weekly summary.
		add_action( Scheduler::HOOK_PREFIX . 'weekly_pipeline', static fn() => $c->get( WeeklyIntelligence::class )->run() );

		$c->set( Scheduler::class, static fn( Container $c ) => new Scheduler( $c->get( JobStateRepository::class ) ) );
		$c->set( RestServiceProvider::class, static fn( Container $c ) => new RestServiceProvider( $c ) );
		$c->set( AdminMenu::class, static fn() => new AdminMenu() );
		$c->set( Updater::class, static fn( Container $c ) => new Updater( $c->get( Settings::class ) ) );
		$c->set( Assets::class, static fn( Container $c ) => new Assets( $c->get( Settings::class ), $c->get( WhiteLabel::class ) ) );

		// White-label overrides (menu label, brand string, terminology) — cheap when inactive.
		$c->get( WhiteLabel::class )->register();

		/**
		 * Allows add-ons to register or override container services.
		 *
		 * @param Container $c The plugin container.
		 */
		do_action( 'sda_register_services', $c );
	}
}

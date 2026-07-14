<?php
/**
 * Composition root: wires the container and boots modules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

use SEODirector\Admin\AdminMenu;
use SEODirector\Admin\Assets;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\Ga4Repository;
use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\PsiRepository;
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
use SEODirector\Jobs\Handlers\RunPsiAuditJob;
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

		// Jobs.
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
				]
			)
		);

		add_action( Scheduler::HOOK_PREFIX . 'daily_sync', static fn() => $c->get( DailySyncCoordinator::class )->run_daily() );
		add_action( Scheduler::HOOK_PREFIX . 'weekly_pipeline', static fn() => $c->get( DailySyncCoordinator::class )->run_weekly() );

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

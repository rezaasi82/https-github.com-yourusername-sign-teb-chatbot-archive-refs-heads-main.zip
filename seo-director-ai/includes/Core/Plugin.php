<?php
/**
 * Composition root: wires the container and boots modules.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

use SEODirector\Admin\AdminMenu;
use SEODirector\Admin\Assets;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Integrations\Google\QuotaManager;
use SEODirector\Integrations\Google\TokenVault;
use SEODirector\Integrations\Http\RetryingHttpClient;
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
		$c->set( JobStateRepository::class, static fn() => new JobStateRepository() );
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

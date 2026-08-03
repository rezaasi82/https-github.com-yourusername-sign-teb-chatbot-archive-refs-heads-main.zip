<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

use Medora\Authority\Admin\AdminModule;
use Medora\Authority\Analytics\AnalyticsModule;
use Medora\Authority\Citation\CitationModule;
use Medora\Authority\Content\ContentModule;
use Medora\Authority\Crawler\CrawlerModule;
use Medora\Authority\Eeat\EeatModule;
use Medora\Authority\Entity\EntityModule;
use Medora\Authority\Graph\GraphModule;
use Medora\Authority\License\LicenseManager;
use Medora\Authority\License\LicenseModule;
use Medora\Authority\Linking\LinkingModule;
use Medora\Authority\Llm\LlmModule;
use Medora\Authority\Llms\LlmsModule;
use Medora\Authority\Medical\MedicalModule;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Performance\PerformanceModule;
use Medora\Authority\Prompt\PromptModule;
use Medora\Authority\Rest\RestModule;
use Medora\Authority\Schema\SchemaModule;
use Medora\Authority\Score\ScoreModule;
use Medora\Authority\Security\SecurityModule;
use Medora\Authority\Semantic\SemanticModule;
use Medora\Authority\Sitemap\SitemapModule;
use Medora\Authority\Vector\VectorModule;
use Medora\Authority\WhiteLabel\WhiteLabelModule;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Composition root.
 *
 * The plugin class owns exactly three responsibilities: build the container,
 * hand every module a chance to register and boot, and wire the handful of
 * platform-wide WordPress hooks that do not belong to any single module.
 */
final class Plugin
{
    private static ?self $instance = null;

    private readonly Container $container;

    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->container = new Container();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function registry(): ModuleRegistry
    {
        return $this->container->get(ModuleRegistry::class);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->registerCoreServices();
        $this->registerModules();

        add_action('init', [$this, 'loadTextdomain'], 1);
        add_filter('cron_schedules', [Cron::class, 'registerSchedules']);
        add_action('init', [$this, 'maybeFlushRewrites'], 999);

        $this->registry()->bootAll();
    }

    /**
     * Services every module may depend on. Registered as singletons so a
     * request resolves one `Options` read, one cache map and one licence state.
     */
    private function registerCoreServices(): void
    {
        $this->container->instance(Container::class, $this->container);

        $this->container->singleton(Options::class, static fn (): Options => new Options());
        $this->container->singleton(
            LicenseManager::class,
            static fn (Container $c): LicenseManager => new LicenseManager($c->get(Options::class))
        );
        $this->container->singleton(
            ModuleRegistry::class,
            static fn (Container $c): ModuleRegistry => new ModuleRegistry(
                $c,
                $c->get(Options::class),
                $c->get(LicenseManager::class)
            )
        );
    }

    private function registerModules(): void
    {
        $registry = $this->registry();

        foreach ($this->moduleClasses() as $class) {
            /** @var \Medora\Authority\Module\ModuleInterface $module */
            $module = new $class();
            $registry->add($module);
        }
    }

    /**
     * Declaration order is documentation only — `ModuleRegistry` sorts by the
     * dependency graph before booting.
     *
     * @return list<class-string<\Medora\Authority\Module\ModuleInterface>>
     */
    private function moduleClasses(): array
    {
        /**
         * Filter the module classes the platform loads. Add-ons append their
         * own class here, or unset a core module to replace it wholesale.
         *
         * @param list<class-string> $classes
         */
        return (array) apply_filters('medora_module_classes', [
            SecurityModule::class,
            PerformanceModule::class,
            LicenseModule::class,
            EntityModule::class,
            GraphModule::class,
            SemanticModule::class,
            VectorModule::class,
            SchemaModule::class,
            CrawlerModule::class,
            LlmsModule::class,
            SitemapModule::class,
            PromptModule::class,
            LlmModule::class,
            ContentModule::class,
            LinkingModule::class,
            CitationModule::class,
            EeatModule::class,
            MedicalModule::class,
            AnalyticsModule::class,
            ScoreModule::class,
            RestModule::class,
            AdminModule::class,
            WhiteLabelModule::class,
        ]);
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'medora-authority',
            false,
            dirname(MEDORA_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Activation and module toggles queue a rewrite flush rather than calling
     * `flush_rewrite_rules()` directly, because the rules being flushed are
     * only registered once `init` has run.
     */
    public function maybeFlushRewrites(): void
    {
        if (get_transient('medora_flush_rewrites') === false) {
            return;
        }

        delete_transient('medora_flush_rewrites');
        flush_rewrite_rules(false);
    }
}

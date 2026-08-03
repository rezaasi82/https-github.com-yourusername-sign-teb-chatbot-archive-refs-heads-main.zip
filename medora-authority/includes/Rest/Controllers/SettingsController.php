<?php

declare(strict_types=1);

namespace Medora\Authority\Rest\Controllers;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Crawler\CrawlerPolicy;
use Medora\Authority\License\LicenseManager;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Rest\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings, module toggles, crawler policy and licence management.
 */
final class SettingsController extends AbstractController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get'],
                'permission_callback' => [$this, 'canView'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/modules/(?P<id>[a-z_]+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'toggleModule'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'      => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key'],
                'enabled' => ['type' => 'boolean', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/crawlers', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'crawlers'],
                'permission_callback' => [$this, 'canView'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateCrawler'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'slug'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
                    'decision' => ['type' => 'string', 'enum' => ['allow', 'block', 'delay', 'reset']],
                    'preset'   => ['type' => 'string', 'enum' => ['allow', 'selective', 'block']],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/license', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'license'],
                'permission_callback' => [$this, 'canView'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'licenseAction'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'action' => ['type' => 'string', 'required' => true, 'enum' => ['activate', 'deactivate', 'transfer', 'refresh']],
                    'key'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);
    }

    public function get(): WP_REST_Response
    {
        $options = $this->container->get(Options::class);

        $settings = $options->all();

        // The stored API key never leaves the server, in either direction.
        unset(
            $settings['embedding_api_key'],
            $settings['llm_api_key'],
            $settings['install_hash'],
            $settings['license']
        );

        return $this->ok([
            'settings'          => $settings,
            'defaults'          => Options::defaults(),
            'has_embedding_key' => $options->getString('embedding_api_key') !== ''
                || getenv('MEDORA_EMBEDDING_API_KEY') !== false
                || defined('MEDORA_EMBEDDING_API_KEY'),
            'has_llm_key'       => $options->getString('llm_api_key') !== ''
                || getenv('MEDORA_LLM_API_KEY') !== false
                || defined('MEDORA_LLM_API_KEY'),
            'tiers'             => array_map(
                static fn (string $tier): array => ['id' => $tier, 'label' => LicenseTier::label($tier)],
                LicenseTier::all()
            ),
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $incoming = $request->get_json_params();

        if (! is_array($incoming) || $incoming === []) {
            return $this->badRequest(__('No settings supplied.', 'medora-authority'));
        }

        $options  = $this->container->get(Options::class);
        $defaults = Options::defaults();
        $clean    = [];

        foreach ($incoming as $key => $value) {
            $key = sanitize_key((string) $key);

            // Only keys the product declares are writable, so a crafted request
            // cannot inject arbitrary options.
            if (! array_key_exists($key, $defaults) && ! in_array($key, ['embedding_api_key', 'llm_api_key'], true)) {
                continue;
            }

            $clean[$key] = $this->sanitizeValue($key, $value);
        }

        if ($clean === []) {
            return $this->badRequest(__('No recognised settings supplied.', 'medora-authority'));
        }

        $options->merge($clean);

        // Changing what is published needs a rewrite flush on the next request.
        if (isset($clean['llms_txt_enabled']) || isset($clean['sitemap_enabled'])) {
            set_transient('medora_flush_rewrites', 1, HOUR_IN_SECONDS);
        }

        return $this->get();
    }

    public function toggleModule(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $registry = $this->container->get(ModuleRegistry::class);
        $id       = (string) $request->get_param('id');
        $module   = $registry->get($id);

        if ($module === null) {
            return $this->notFound(__('Unknown module.', 'medora-authority'));
        }

        $enabled = (bool) $request->get_param('enabled');

        if ($enabled && ! $this->container->get(LicenseManager::class)->allowsTier($module->requiredTier())) {
            return new WP_Error(
                'medora_upgrade_required',
                sprintf(
                    /* translators: %s: licence tier name. */
                    __('This module requires the %s plan.', 'medora-authority'),
                    LicenseTier::label($module->requiredTier())
                ),
                ['status' => 402, 'required_tier' => $module->requiredTier()]
            );
        }

        // Refuse to disable a module other enabled modules depend on, rather
        // than letting the dependency check silently disable them too.
        if (! $enabled) {
            $dependents = [];

            foreach ($registry->all() as $otherId => $other) {
                if ($registry->isEnabled($otherId) && in_array($id, $other->dependencies(), true)) {
                    $dependents[] = $other->title();
                }
            }

            if ($dependents !== []) {
                return $this->badRequest(sprintf(
                    /* translators: %s: comma-separated module names. */
                    __('Disable these modules first, they depend on this one: %s', 'medora-authority'),
                    implode(', ', $dependents)
                ));
            }
        }

        $registry->setEnabled($id, $enabled);

        set_transient('medora_flush_rewrites', 1, HOUR_IN_SECONDS);

        return $this->ok(['id' => $id, 'enabled' => $enabled, 'requires_reload' => true]);
    }

    public function crawlers(): WP_REST_Response
    {
        $policy = $this->container->get(CrawlerPolicy::class);

        return $this->ok([
            'preset'      => $policy->preset(),
            'crawl_delay' => $policy->crawlDelay(),
            'crawlers'    => $policy->resolvedTable(),
            'robots'      => [
                'physical_file_present' => $this->container->get(\Medora\Authority\Crawler\RobotsManager::class)->hasPhysicalRobotsFile(),
                'preview'               => $this->container->get(\Medora\Authority\Crawler\RobotsManager::class)->directives(),
            ],
        ]);
    }

    public function updateCrawler(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $policy = $this->container->get(CrawlerPolicy::class);
        $preset = (string) $request->get_param('preset');

        if ($preset !== '') {
            $policy->setPreset($preset);
        }

        $slug     = (string) $request->get_param('slug');
        $decision = (string) $request->get_param('decision');

        if ($slug !== '' && $decision !== '') {
            if (\Medora\Authority\Crawler\CrawlerRegistry::find($slug) === null) {
                return $this->notFound(__('Unknown crawler.', 'medora-authority'));
            }

            // "reset" clears the override so the site preset applies again.
            $policy->setOverride($slug, $decision === 'reset' ? null : $decision);
        }

        return $this->crawlers();
    }

    public function license(): WP_REST_Response
    {
        return $this->ok($this->container->get(LicenseManager::class)->publicState());
    }

    public function licenseAction(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $license = $this->container->get(LicenseManager::class);
        $action  = (string) $request->get_param('action');

        $result = match ($action) {
            'activate'   => $license->activate((string) $request->get_param('key')),
            'deactivate' => $license->deactivate(),
            'transfer'   => $license->transferToCurrentDomain(),
            default      => $this->refreshLicense($license),
        };

        if (! ($result['ok'] ?? false)) {
            return new WP_Error(
                'medora_license_error',
                (string) ($result['message'] ?? __('Licence operation failed.', 'medora-authority')),
                ['status' => 400]
            );
        }

        return $this->ok([
            'message' => (string) ($result['message'] ?? ''),
            'license' => $license->publicState(),
        ]);
    }

    /** @return array{ok: bool, message: string} */
    private function refreshLicense(LicenseManager $license): array
    {
        $license->refresh(true);

        return ['ok' => true, 'message' => __('Licence status refreshed.', 'medora-authority')];
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        $defaults = Options::defaults();
        $default  = $defaults[$key] ?? null;

        if (is_bool($default)) {
            return (bool) $value;
        }

        if (is_int($default)) {
            return (int) $value;
        }

        if (is_array($default) || is_array($value)) {
            return $this->sanitizeArray((array) $value);
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function sanitizeArray(array $value): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            $key = is_int($key) ? $key : sanitize_key((string) $key);

            $clean[$key] = match (true) {
                is_array($item)  => $this->sanitizeArray($item),
                is_bool($item)   => $item,
                is_int($item)    => $item,
                is_float($item)  => $item,
                default          => sanitize_text_field((string) $item),
            };
        }

        return $clean;
    }
}

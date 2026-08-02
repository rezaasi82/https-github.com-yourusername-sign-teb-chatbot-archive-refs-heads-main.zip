<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Cron;
use Medora\Authority\Core\Options;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Support\Hash;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Crawler Manager.
 *
 * Detects AI crawlers, applies the configured access policy, logs the visit
 * and publishes the policy to robots.txt.
 */
final class CrawlerModule extends AbstractModule
{
    public function id(): string
    {
        return 'crawler';
    }

    public function title(): string
    {
        return __('AI Crawler Manager', 'medora-authority');
    }

    public function description(): string
    {
        return __('Detect, control and measure the AI crawlers that read your site.', 'medora-authority');
    }

    public function register(Container $container): void
    {
        $container->singleton(CrawlerDetector::class, static fn (): CrawlerDetector => new CrawlerDetector());
        $container->singleton(CrawlLogRepository::class, static fn (): CrawlLogRepository => new CrawlLogRepository());
        $container->singleton(
            CrawlerPolicy::class,
            static fn (Container $c): CrawlerPolicy => new CrawlerPolicy($c->get(Options::class))
        );
    }

    public function boot(Container $container): void
    {
        $container->get(RobotsManager::class)->register();

        // Priority 1: the decision must be made before any theme output.
        add_action('template_redirect', function () use ($container): void {
            $this->handleRequest($container);
        }, 1);

        add_action(Cron::PRUNE_LOGS, function () use ($container): void {
            $retention = $container->get(Options::class)->getInt('retention_days', 180);
            $container->get(CrawlLogRepository::class)->prune(max(7, $retention));
        });

        // Re-emitting robots.txt is cheap, but the cached crawl report is not.
        add_action('medora_crawler_policy_changed', static function () use ($container): void {
            $container->get(\Medora\Authority\Support\Cache::class)->flush();
        });
    }

    /**
     * Decide, log and — when blocked — short-circuit the request.
     */
    private function handleRequest(Container $container): void
    {
        if (is_admin() || wp_doing_cron() || wp_doing_ajax()) {
            return;
        }

        $detector = $container->get(CrawlerDetector::class);
        $crawler  = $detector->current();

        if ($crawler === null) {
            return;
        }

        $policy   = $container->get(CrawlerPolicy::class);
        $decision = $policy->decisionFor((string) $crawler['slug']);
        $status   = $decision === CrawlerPolicy::BLOCK ? 403 : 200;

        $container->get(CrawlLogRepository::class)->record([
            'crawler_slug' => (string) $crawler['slug'],
            'vendor'       => (string) $crawler['vendor'],
            'request_uri'  => $detector->requestUri(),
            'object_id'    => (int) get_queried_object_id(),
            'status_code'  => $status,
            'decision'     => $decision,
            'ip_hash'      => $container->get(Hash::class)->visitorIp(),
        ]);

        /**
         * Fires on every identified AI crawler request, before the response is
         * produced. Integrations use this for their own logging or for serving
         * a machine-optimised variant of the page.
         *
         * @param array<string, mixed> $crawler  Registry entry.
         * @param string               $decision allow|block|delay.
         */
        do_action('medora_ai_crawler_detected', $crawler, $decision);

        if ($decision !== CrawlerPolicy::BLOCK) {
            return;
        }

        // 403 rather than 404: it tells the operator the block was deliberate
        // and keeps the URL out of "broken link" reports on the crawler side.
        status_header(403);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-Type: text/plain; charset=utf-8', true);

        echo esc_html__('This site does not grant this AI crawler access to its content.', 'medora-authority');

        exit;
    }
}

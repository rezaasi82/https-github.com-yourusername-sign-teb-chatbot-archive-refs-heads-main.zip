<?php

declare(strict_types=1);

namespace Medora\Authority\Analytics;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Cron;
use Medora\Authority\Core\Options;
use Medora\Authority\Crawler\CrawlerDetector;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Support\Cache;
use Medora\Authority\Support\Hash;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Analytics — measures referral traffic from assistants.
 *
 * Cookie-free by design: the visitor "identity" is a daily-rotating salted hash
 * of IP and user agent, which is enough to count distinct visitors within a day
 * without storing anything that identifies a person or requiring a consent
 * banner.
 */
final class AnalyticsModule extends AbstractModule
{
    public function id(): string
    {
        return 'analytics';
    }

    public function title(): string
    {
        return __('AI Analytics', 'medora-authority');
    }

    public function description(): string
    {
        return __('Track visits arriving from ChatGPT, Claude, Perplexity, Gemini and other assistants.', 'medora-authority');
    }

    public function register(Container $container): void
    {
        $container->singleton(ReferralDetector::class, static fn (): ReferralDetector => new ReferralDetector());
        $container->singleton(ReferralRepository::class, static fn (): ReferralRepository => new ReferralRepository());
        $container->singleton(
            TrendCalculator::class,
            static fn (Container $c): TrendCalculator => new TrendCalculator(
                $c->get(ReferralRepository::class),
                $c->get(Cache::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        if (! $container->get(Options::class)->getBool('analytics_enabled', true)) {
            return;
        }

        add_action('template_redirect', function () use ($container): void {
            $this->recordVisit($container);
        }, 20);

        add_action(Cron::PRUNE_LOGS, static function () use ($container): void {
            $retention = $container->get(Options::class)->getInt('retention_days', 180);
            $container->get(ReferralRepository::class)->prune(max(7, $retention));
        });
    }

    private function recordVisit(Container $container): void
    {
        if (is_admin() || wp_doing_cron() || wp_doing_ajax() || is_404()) {
            return;
        }

        // Crawler visits are logged by the crawler module; counting them here
        // too would double-count and inflate the referral numbers.
        if ($container->get(CrawlerDetector::class)->isAiCrawler()) {
            return;
        }

        if (is_user_logged_in()) {
            return;
        }

        $referrer = isset($_SERVER['HTTP_REFERER'])
            ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER']))
            : '';

        $queryString = isset($_SERVER['QUERY_STRING'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['QUERY_STRING']))
            : '';

        $match = $container->get(ReferralDetector::class)->detect($referrer, $queryString);

        if ($match === null) {
            return;
        }

        $hash    = $container->get(Hash::class);
        $visitor = $hash->pseudonymize(
            $hash->rawIp()
            . '|' . $container->get(CrawlerDetector::class)->userAgent()
            // Rotating the salt daily bounds how long a visitor hash stays
            // comparable, so it cannot be used to build a long-term profile.
            . '|' . gmdate('Y-m-d')
        );

        $container->get(ReferralRepository::class)->record(
            $match['slug'],
            $match['referrer_host'],
            $container->get(CrawlerDetector::class)->requestUri(),
            (int) get_queried_object_id(),
            $visitor
        );

        /**
         * Fires when a visit from an AI assistant is recorded.
         *
         * @param array{slug: string, label: string, referrer_host: string} $match
         */
        do_action('medora_ai_referral_recorded', $match);
    }
}

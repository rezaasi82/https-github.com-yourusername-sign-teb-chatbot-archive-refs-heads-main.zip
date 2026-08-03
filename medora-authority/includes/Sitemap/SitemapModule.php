<?php

declare(strict_types=1);

namespace Medora\Authority\Sitemap;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Score\AnalysisRepository;
use Medora\Authority\Support\Cache;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Sitemap Engine.
 *
 * Serves `/medora-sitemap.xml` (the index) plus per-type sitemaps in XML, JSON
 * and Markdown. Search engines are pinged only when content actually changes,
 * and never more than once an hour — repeatedly pinging an unchanged sitemap
 * is at best ignored and at worst treated as spam.
 */
final class SitemapModule extends AbstractModule
{
    private const QUERY_TYPE   = 'medora_sitemap';
    private const QUERY_FORMAT = 'medora_sitemap_format';
    private const PING_THROTTLE_KEY = 'medora_sitemap_pinged';

    public function id(): string
    {
        return 'sitemap';
    }

    public function title(): string
    {
        return __('AI Sitemap Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Publish entity-annotated sitemaps in XML, JSON and Markdown.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity'];
    }

    public function register(Container $container): void
    {
        $container->singleton(SitemapRenderer::class, static fn (): SitemapRenderer => new SitemapRenderer());

        $container->singleton(
            SitemapBuilder::class,
            static fn (Container $c): SitemapBuilder => new SitemapBuilder(
                $c->get(EntityRepository::class),
                $c->get(AnalysisRepository::class),
                $c->get(Cache::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        if (! $container->get(Options::class)->getBool('sitemap_enabled', true)) {
            return;
        }

        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);

        add_action('template_redirect', function () use ($container): void {
            $this->maybeServe($container);
        });

        foreach (['save_post', 'deleted_post', 'trashed_post'] as $hook) {
            add_action($hook, function () use ($container): void {
                $container->get(SitemapBuilder::class)->flush();
                $this->maybePing();
            });
        }
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^medora-sitemap\.xml$', 'index.php?' . self::QUERY_TYPE . '=index', 'top');
        add_rewrite_rule(
            '^medora-sitemap-([a-z]+)\.(xml|json|md)$',
            'index.php?' . self::QUERY_TYPE . '=$matches[1]&' . self::QUERY_FORMAT . '=$matches[2]',
            'top'
        );
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_TYPE;
        $vars[] = self::QUERY_FORMAT;

        return $vars;
    }

    private function maybeServe(Container $container): void
    {
        $type = (string) get_query_var(self::QUERY_TYPE);

        if ($type === '') {
            return;
        }

        $builder  = $container->get(SitemapBuilder::class);
        $renderer = $container->get(SitemapRenderer::class);

        if ($type === 'index') {
            $this->emit($renderer->index($builder->availableTypes()), SitemapRenderer::FORMAT_XML);
        }

        if (! in_array($type, $builder->availableTypes(), true)) {
            return;
        }

        $format = (string) get_query_var(self::QUERY_FORMAT);
        $format = array_key_exists($format, SitemapRenderer::contentTypes()) ? $format : SitemapRenderer::FORMAT_XML;

        $this->emit($renderer->render($builder->entries($type), $format, $type), $format);
    }

    private function emit(string $body, string $format): void
    {
        status_header(200);
        header('Content-Type: ' . (SitemapRenderer::contentTypes()[$format] ?? 'application/xml; charset=utf-8'), true);
        header('X-Robots-Tag: noindex, follow', true);
        header('Cache-Control: public, max-age=3600', true);

        // Pre-serialised and already escaped per format by the renderer.
        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        exit;
    }

    /**
     * Notify search engines, at most once an hour.
     */
    private function maybePing(): void
    {
        if (get_transient(self::PING_THROTTLE_KEY) !== false) {
            return;
        }

        set_transient(self::PING_THROTTLE_KEY, 1, HOUR_IN_SECONDS);

        $sitemap = home_url('/medora-sitemap.xml');

        /**
         * Filter the endpoints pinged when the sitemap changes.
         *
         * Google retired its ping endpoint in 2023 and Bing prefers IndexNow,
         * so the default list is deliberately short. Add your own endpoint
         * (including an IndexNow key URL) here.
         *
         * @param list<string> $endpoints
         * @param string       $sitemap
         */
        $endpoints = (array) apply_filters('medora_sitemap_ping_endpoints', [], $sitemap);

        foreach ($endpoints as $endpoint) {
            wp_remote_get($endpoint, ['timeout' => 5, 'blocking' => false]);
        }

        do_action('medora_sitemap_updated', $sitemap);
    }
}

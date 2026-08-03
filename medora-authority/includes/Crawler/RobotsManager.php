<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Writes Medora's crawler policy into the virtual robots.txt.
 *
 * WordPress serves robots.txt dynamically unless a physical file exists, so
 * the directives are appended through the `robots_txt` filter. If a real file
 * is present the filter never runs; `hasPhysicalRobotsFile()` detects that and
 * the dashboard surfaces it as a warning rather than silently doing nothing.
 */
final class RobotsManager
{
    public function __construct(
        private readonly CrawlerPolicy $policy,
        private readonly Options $options,
    ) {
    }

    public function register(): void
    {
        add_filter('robots_txt', [$this, 'filterRobotsTxt'], 20, 2);
    }

    public function filterRobotsTxt(string $output, bool $public): string
    {
        // A site marked non-public already emits a blanket Disallow; adding
        // per-crawler rules on top would only confuse.
        if (! $public) {
            return $output;
        }

        return rtrim($output) . "\n\n" . $this->directives() . "\n";
    }

    /** The block Medora appends to robots.txt. */
    public function directives(): string
    {
        $lines = [
            '# --- Medora Authority ---',
            '# AI crawler policy. Managed from the Medora dashboard.',
        ];

        $delay   = $this->policy->crawlDelay();
        $blocked = 0;

        foreach ($this->policy->resolvedTable() as $crawler) {
            $token = (string) $crawler['robots_token'];

            if ($token === '') {
                continue;
            }

            $lines[] = '';
            $lines[] = sprintf('User-agent: %s', $token);

            switch ($crawler['decision']) {
                case CrawlerPolicy::BLOCK:
                    $lines[] = 'Disallow: /';
                    $blocked++;
                    break;

                case CrawlerPolicy::DELAY:
                    $lines[] = 'Allow: /';

                    if ($delay > 0) {
                        // Not part of the original standard and ignored by
                        // Google, but honoured by Bing, Yandex and several AI
                        // crawlers, so it is still worth emitting.
                        $lines[] = sprintf('Crawl-delay: %d', $delay);
                    }
                    break;

                default:
                    $lines[] = 'Allow: /';
                    break;
            }
        }

        if ($this->options->getBool('llms_txt_enabled', true)) {
            $lines[] = '';
            $lines[] = sprintf('# LLM guidance: %s', home_url('/llms.txt'));
        }

        if ($this->options->getBool('sitemap_enabled', true)) {
            $lines[] = sprintf('Sitemap: %s', home_url('/medora-sitemap.xml'));
        }

        $lines[] = '';
        $lines[] = sprintf('# %d crawler(s) disallowed by policy.', $blocked);
        $lines[] = '# --- /Medora Authority ---';

        /**
         * Filter the robots.txt block before it is emitted.
         *
         * @param string $block
         */
        return (string) apply_filters('medora_robots_directives', implode("\n", $lines));
    }

    /**
     * True when a real robots.txt shadows the WordPress virtual one, which
     * silently disables every directive above.
     */
    public function hasPhysicalRobotsFile(): bool
    {
        return file_exists(ABSPATH . 'robots.txt');
    }

    /**
     * `X-Robots-Tag` values for the current request.
     *
     * Blocking in robots.txt stops a well-behaved crawler from fetching, but
     * the header is what expresses the AI-specific opt-out for content that has
     * already been fetched.
     *
     * @return list<string>
     */
    public function headerDirectives(): array
    {
        $directives = [];

        if ($this->policy->decisionFor('google-extended') === CrawlerPolicy::BLOCK) {
            $directives[] = 'Google-Extended: noindex';
        }

        return (array) apply_filters('medora_robots_headers', $directives);
    }
}

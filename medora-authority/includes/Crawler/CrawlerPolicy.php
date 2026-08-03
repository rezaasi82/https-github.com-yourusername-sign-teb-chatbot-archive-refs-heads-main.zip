<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-crawler access decisions.
 *
 * Three site-wide presets cover the common cases, with per-crawler overrides
 * layered on top:
 *
 * - `allow`     — everything is welcome (maximum AI visibility).
 * - `selective` — search and retrieval crawlers allowed, training crawlers
 *                 blocked. This is the setting most publishers actually want:
 *                 stay citable, opt out of corpus collection.
 * - `block`     — deny all AI crawlers, keep classic search.
 */
final class CrawlerPolicy
{
    public const ALLOW = 'allow';
    public const BLOCK = 'block';
    public const DELAY = 'delay';

    private const OPTION_OVERRIDES = 'crawler_overrides';
    private const OPTION_PRESET    = 'crawler_policy';
    private const OPTION_DELAY     = 'crawler_delay_seconds';

    public function __construct(private readonly Options $options)
    {
    }

    public function preset(): string
    {
        return $this->options->getString(self::OPTION_PRESET, 'allow');
    }

    public function setPreset(string $preset): void
    {
        $this->options->set(self::OPTION_PRESET, in_array($preset, ['allow', 'selective', 'block'], true) ? $preset : 'allow');
    }

    /** @return array<string, string> crawler slug => decision */
    public function overrides(): array
    {
        /** @var array<string, string> $overrides */
        $overrides = $this->options->getArray(self::OPTION_OVERRIDES);

        return $overrides;
    }

    public function setOverride(string $slug, ?string $decision): void
    {
        $overrides = $this->overrides();

        if ($decision === null) {
            unset($overrides[$slug]);
        } else {
            $overrides[$slug] = in_array($decision, [self::ALLOW, self::BLOCK, self::DELAY], true) ? $decision : self::ALLOW;
        }

        $this->options->set(self::OPTION_OVERRIDES, $overrides);

        do_action('medora_crawler_policy_changed', $slug, $decision);
    }

    public function crawlDelay(): int
    {
        return max(0, $this->options->getInt(self::OPTION_DELAY, 10));
    }

    /**
     * Effective decision for a crawler: explicit override first, then preset.
     */
    public function decisionFor(string $slug): string
    {
        $overrides = $this->overrides();

        if (isset($overrides[$slug])) {
            return $overrides[$slug];
        }

        $crawler = CrawlerRegistry::find($slug);

        if ($crawler === null) {
            return self::ALLOW;
        }

        $decision = match ($this->preset()) {
            'block'     => $crawler['purpose'] === CrawlerRegistry::PURPOSE_SEARCH && $crawler['vendor'] === 'Google'
                ? self::ALLOW   // never block classic Googlebot; that is an SEO own-goal
                : self::BLOCK,
            'selective' => $crawler['purpose'] === CrawlerRegistry::PURPOSE_TRAINING ? self::BLOCK : self::ALLOW,
            default     => self::ALLOW,
        };

        /**
         * Filter the access decision for a crawler.
         *
         * @param string               $decision One of allow|block|delay.
         * @param string               $slug     Crawler slug.
         * @param array<string, mixed> $crawler  Registry entry.
         */
        return (string) apply_filters('medora_crawler_decision', $decision, $slug, $crawler);
    }

    public function allows(string $slug): bool
    {
        return $this->decisionFor($slug) !== self::BLOCK;
    }

    /**
     * Every crawler with its resolved decision, for the dashboard and the
     * robots.txt writer.
     *
     * @return list<array<string, mixed>>
     */
    public function resolvedTable(): array
    {
        $rows = [];

        foreach (CrawlerRegistry::all() as $crawler) {
            $rows[] = $crawler + [
                'decision'       => $this->decisionFor($crawler['slug']),
                'is_override'    => isset($this->overrides()[$crawler['slug']]),
                'purpose_label'  => CrawlerRegistry::purposeLabel($crawler['purpose']),
            ];
        }

        return $rows;
    }
}

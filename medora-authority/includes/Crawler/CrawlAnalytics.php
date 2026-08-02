<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

use Medora\Authority\Support\Cache;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns the raw crawl log into the numbers the dashboard shows.
 *
 * Reports are cached for fifteen minutes: the underlying aggregate queries are
 * `GROUP BY` scans that an agency dashboard would otherwise re-run on every
 * page load.
 */
final class CrawlAnalytics
{
    private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    public function __construct(
        private readonly CrawlLogRepository $log,
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array{
     *     window_days: int,
     *     total_hits: int,
     *     unique_crawlers: int,
     *     coverage: array{seen: int, known: int, percent: float},
     *     crawlers: list<array<string, mixed>>,
     *     series: list<array<string, mixed>>,
     *     top_paths: list<array<string, mixed>>,
     *     missing: list<array<string, mixed>>
     * }
     */
    public function report(int $days = 30): array
    {
        return $this->cache->remember(
            sprintf('crawl_report_%d', $days),
            self::CACHE_TTL,
            function () use ($days): array {
                $totals   = $this->log->totalsByCrawler($days);
                $registry = CrawlerRegistry::all();
                $seen     = array_column($totals, 'crawler_slug');

                $crawlers = [];

                foreach ($totals as $row) {
                    $meta = $registry[$row['crawler_slug']] ?? null;

                    $crawlers[] = [
                        'slug'      => $row['crawler_slug'],
                        'name'      => $meta['name'] ?? $row['crawler_slug'],
                        'vendor'    => $meta['vendor'] ?? $row['vendor'],
                        'purpose'   => $meta['purpose'] ?? '',
                        'hits'      => $row['hits'],
                        'last_seen' => $row['last_seen'],
                    ];
                }

                // Crawlers we allow but that have not visited. This is the
                // actionable half of the report: an allowed AI crawler that
                // never arrives usually means a robots, DNS or firewall
                // problem, not disinterest.
                $missing = [];

                foreach ($registry as $slug => $meta) {
                    if (in_array($slug, $seen, true) || ! $meta['recommended'] || $meta['ua_token'] === '') {
                        continue;
                    }

                    $missing[] = [
                        'slug'   => $slug,
                        'name'   => $meta['name'],
                        'vendor' => $meta['vendor'],
                    ];
                }

                $known = count(array_filter($registry, static fn (array $c): bool => $c['ua_token'] !== ''));

                return [
                    'window_days'     => $days,
                    'total_hits'      => array_sum(array_column($totals, 'hits')),
                    'unique_crawlers' => count($totals),
                    'coverage'        => [
                        'seen'    => count($totals),
                        'known'   => $known,
                        'percent' => $known > 0 ? round((count($totals) / $known) * 100, 1) : 0.0,
                    ],
                    'crawlers'  => $crawlers,
                    'series'    => $this->log->dailySeries($days),
                    'top_paths' => $this->log->topPaths($days),
                    'missing'   => $missing,
                ];
            }
        );
    }
}

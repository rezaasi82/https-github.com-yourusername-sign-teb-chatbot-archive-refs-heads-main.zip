<?php

namespace SignTeb\VideoHub\Admin;

use SignTeb\VideoHub\Api\SourceManager;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Cron\Scheduler;
use SignTeb\VideoHub\Db\AiQueueRepository;
use SignTeb\VideoHub\Db\AnalyticsRepository;
use SignTeb\VideoHub\Db\SyncLogRepository;
use SignTeb\VideoHub\Db\VideoRepository;
use SignTeb\VideoHub\Schema\SeoCompat;
use SignTeb\VideoHub\Seo\IndexingClient;
use SignTeb\VideoHub\Seo\IndexingQueue;
use SignTeb\VideoHub\Seo\VideoSitemap;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 16 — the operations dashboard: counts, last sync, API status,
 * cache, recent errors and Google indexing state.
 */
class DashboardPage
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی لازم را ندارید.', 'signteb-video-hub'));
        }

        $videos    = new VideoRepository();
        $analytics = new AnalyticsRepository();
        $sitemap   = new VideoSitemap($this->settings);

        $data = [
            'settings'    => $this->settings,
            'counts'      => [
                'published' => $videos->count('publish'),
                'draft'     => $videos->count('draft'),
                'with_ai'   => $videos->count_with_ai(),
                'sitemap'   => $sitemap->eligible_count(),
            ],
            'duration'    => $sitemap->total_duration_human(),
            'last_sync'   => (string) get_option('stvh_last_sync', ''),
            'sync_log'    => (new SyncLogRepository())->recent(5),
            'sources'     => (new SourceManager($this->settings))->status(),
            'ai_queue'    => (new AiQueueRepository())->counts(),
            'totals'      => $analytics->totals(30),
            'next_runs'   => Scheduler::next_runs(),
            'caches'      => CacheManager::detected(),
            'seo_plugins' => SeoCompat::detected(),
            'indexing'    => [
                'configured' => (new IndexingClient($this->settings))->is_configured(),
                'queued'     => (new IndexingQueue($this->settings))->count(),
                'last_ping'  => IndexingClient::last_ping(),
            ],
            'sitemap_url' => VideoSitemap::url(),
            'errors'      => Logger::recent(8),
        ];

        require STVH_DIR . 'includes/Admin/views/dashboard.php';
    }
}

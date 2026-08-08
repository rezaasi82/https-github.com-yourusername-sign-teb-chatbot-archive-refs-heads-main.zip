<?php

namespace SignTeb\VideoHub\Admin;

use SignTeb\VideoHub\Api\SourceManager;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\PostType;
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

        $data['links']  = [
            'videos'   => admin_url('edit.php?post_type=' . PostType::POST_TYPE),
            'drafts'   => admin_url('edit.php?post_status=draft&post_type=' . PostType::POST_TYPE),
            'settings' => admin_url('admin.php?page=stvh-settings'),
            'reports'  => admin_url('admin.php?page=stvh-analytics'),
        ];
        $data['setup']  = $this->setup_steps($data);
        $data['health'] = $this->health($data);

        require STVH_DIR . 'includes/Admin/views/dashboard.php';
    }

    /**
     * The one question a dashboard should answer before any other: is this
     * working right now, and if not, what do I press?
     *
     * Reading four panels and decoding badges is work an operator should not
     * have to do to learn "yes, fine" — so the panels stay, and this says the
     * answer out loud above them.
     *
     * @param array<string,mixed> $data
     * @return array{level:string,title:string,detail:string,action:string,action_label:string,link:string}
     */
    private function health(array $data): array
    {
        $configured = array_filter($data['sources'], static fn(array $s): bool => (bool) $s['configured']);
        $broken     = array_filter($configured, static fn(array $s): bool => ! $s['ok']);
        $errors     = array_filter($data['errors'], static fn(array $e): bool => ($e['level'] ?? '') === 'error');

        if ($configured === []) {
            return [
                'level'        => 'setup',
                'title'        => __('هنوز هیچ منبع ویدئویی وصل نشده است.', 'signteb-video-hub'),
                'detail'       => __('برای شروع، شناسه کانال آپارات یا یوتیوب را در تنظیمات وارد کنید. بقیه‌ی مراحل خودکار است.', 'signteb-video-hub'),
                'action'       => '',
                'action_label' => __('رفتن به تنظیمات', 'signteb-video-hub'),
                'link'         => $data['links']['settings'],
            ];
        }

        if ($broken !== []) {
            $first = reset($broken);

            return [
                'level'        => 'error',
                'title'        => sprintf(
                    /* translators: %s: source label */
                    __('اتصال به %s برقرار نیست.', 'signteb-video-hub'),
                    (string) $first['label']
                ),
                'detail'       => (string) $first['message'],
                'action'       => '',
                'action_label' => __('بررسی تنظیمات', 'signteb-video-hub'),
                'link'         => $data['links']['settings'],
            ];
        }

        if ($data['counts']['published'] === 0 && $data['counts']['draft'] === 0) {
            return [
                'level'        => 'setup',
                'title'        => __('اتصال برقرار است — هنوز ویدئویی وارد نشده.', 'signteb-video-hub'),
                'detail'       => __('یک بار «همگام‌سازی دستی» را بزنید تا کتابخانه پر شود. بعد از آن خودکار ادامه پیدا می‌کند.', 'signteb-video-hub'),
                'action'       => 'sync',
                'action_label' => __('همگام‌سازی دستی', 'signteb-video-hub'),
                'link'         => '',
            ];
        }

        if ($errors !== []) {
            $first = reset($errors);

            return [
                'level'        => 'warn',
                'title'        => sprintf(
                    /* translators: %s: number of recent errors */
                    __('%s خطای اخیر ثبت شده است.', 'signteb-video-hub'),
                    number_format_i18n(count($errors))
                ),
                'detail'       => (string) $first['message'],
                'action'       => '',
                'action_label' => '',
                'link'         => '',
            ];
        }

        return [
            'level'        => 'ok',
            'title'        => __('همه‌چیز درست کار می‌کند.', 'signteb-video-hub'),
            'detail'       => $data['last_sync'] !== ''
                ? sprintf(
                    /* translators: 1: last sync, 2: next sync */
                    __('آخرین همگام‌سازی: %1$s — بعدی: %2$s', 'signteb-video-hub'),
                    wp_date('Y/m/d H:i', (int) strtotime($data['last_sync'])),
                    $data['next_runs']['sync'] > 0
                        ? wp_date('Y/m/d H:i', $data['next_runs']['sync'])
                        : __('زمان‌بندی نشده', 'signteb-video-hub')
                )
                : __('همگام‌سازی هنوز اجرا نشده است.', 'signteb-video-hub'),
            'action'       => 'sync',
            'action_label' => __('همگام‌سازی دستی', 'signteb-video-hub'),
            'link'         => '',
        ];
    }

    /**
     * A first-run checklist, shown only while something is still missing.
     *
     * A blank dashboard full of zeros tells a new operator nothing about what
     * to do next; four numbered steps do.
     *
     * @param array<string,mixed> $data
     * @return array{done:int,total:int,steps:array<int,array{label:string,done:bool,hint:string}>}
     */
    private function setup_steps(array $data): array
    {
        $connected = array_filter($data['sources'], static fn(array $s): bool => (bool) $s['configured']);
        $has_video = $data['counts']['published'] > 0 || $data['counts']['draft'] > 0;

        $steps = [
            [
                'label' => __('اتصال کانال آپارات یا یوتیوب', 'signteb-video-hub'),
                'done'  => $connected !== [],
                'hint'  => __('تنظیمات ← منابع ویدئو', 'signteb-video-hub'),
            ],
            [
                'label' => __('اولین همگام‌سازی', 'signteb-video-hub'),
                'done'  => $has_video,
                'hint'  => __('دکمه‌ی «همگام‌سازی دستی» در همین صفحه', 'signteb-video-hub'),
            ],
            [
                'label' => __('همگام‌سازی خودکار زمان‌بندی شد', 'signteb-video-hub'),
                'done'  => $data['next_runs']['sync'] > 0,
                'hint'  => __('با فعال بودن افزونه خودکار انجام می‌شود', 'signteb-video-hub'),
            ],
            [
                'label' => __('هوش مصنوعی (اختیاری)', 'signteb-video-hub'),
                'done'  => $this->settings->bool('ai_enabled') && $this->settings->secret('ai_api_key') !== '',
                'hint'  => __('برای خلاصه، پرسش‌وپاسخ و لینک‌سازی داخلی', 'signteb-video-hub'),
            ],
        ];

        return [
            'done'  => count(array_filter($steps, static fn(array $s): bool => $s['done'])),
            'total' => count($steps),
            'steps' => $steps,
        ];
    }
}

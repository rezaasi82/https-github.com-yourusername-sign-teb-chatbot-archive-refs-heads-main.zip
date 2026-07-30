<?php

namespace SignTeb\VideoHub\Rest\Controllers;

use SignTeb\VideoHub\Ai\AiManager;
use SignTeb\VideoHub\Ai\ArticleSuggester;
use SignTeb\VideoHub\Api\SourceManager;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Cron\AiWorker;
use SignTeb\VideoHub\Db\AiQueueRepository;
use SignTeb\VideoHub\Rest\RestNamespace;
use SignTeb\VideoHub\Seo\IndexingClient;
use SignTeb\VideoHub\Seo\IndexingQueue;
use SignTeb\VideoHub\Seo\VideoSitemap;
use SignTeb\VideoHub\Sync\Repair;
use SignTeb\VideoHub\Sync\SyncManager;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin-only actions the dashboard triggers without a page reload:
 * manual sync, connection tests, AI generation, cache purge, index ping.
 */
class AdminController
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register_routes(): void
    {
        $permission = [$this, 'can_manage'];

        register_rest_route(RestNamespace::NAME, '/sync', [
            'methods'             => 'POST',
            'callback'            => [$this, 'sync'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(RestNamespace::NAME, '/test-connection', [
            'methods'             => 'POST',
            'callback'            => [$this, 'test_connection'],
            'permission_callback' => $permission,
            'args'                => [
                'target' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(RestNamespace::NAME, '/ai/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => $permission,
            'args'                => [
                'video_id' => ['type' => 'integer', 'required' => true],
                'task'     => ['type' => 'string', 'default' => 'summary'],
            ],
        ]);

        register_rest_route(RestNamespace::NAME, '/ai/queue', [
            'methods'             => 'POST',
            'callback'            => [$this, 'run_queue'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(RestNamespace::NAME, '/ai/article', [
            'methods'             => 'POST',
            'callback'            => [$this, 'publish_article'],
            'permission_callback' => $permission,
            'args'                => [
                'video_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        register_rest_route(RestNamespace::NAME, '/repair', [
            'methods'             => 'POST',
            'callback'            => [$this, 'repair'],
            'permission_callback' => $permission,
            'args'                => [
                'video_id' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route(RestNamespace::NAME, '/cache/purge', [
            'methods'             => 'POST',
            'callback'            => [$this, 'purge_cache'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(RestNamespace::NAME, '/indexing/ping', [
            'methods'             => 'POST',
            'callback'            => [$this, 'ping_index'],
            'permission_callback' => $permission,
            'args'                => [
                'video_id' => ['type' => 'integer', 'default' => 0],
            ],
        ]);
    }

    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public function sync(): WP_REST_Response
    {
        $result = (new SyncManager($this->settings))->sync_all();

        return new WP_REST_Response([
            'ok'      => $result['ok'],
            'message' => $result['ok']
                ? sprintf(
                    /* translators: 1: imported count, 2: updated count, 3: skipped count */
                    __('%1$d ویدئوی جدید، %2$d به‌روزرسانی، %3$d بدون تغییر.', 'signteb-video-hub'),
                    $result['imported'],
                    $result['updated'],
                    $result['skipped']
                )
                : implode(' ', $result['errors']),
            'data'    => $result,
        ], 200);
    }

    public function test_connection(WP_REST_Request $request): WP_REST_Response
    {
        $target = sanitize_key((string) $request->get_param('target'));

        $result = match ($target) {
            'ai'     => (new AiManager($this->settings))->test_connection(),
            'google' => (new IndexingClient($this->settings))->test_connection(),
            default  => $this->test_source($target),
        };

        return new WP_REST_Response($result, 200);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function test_source(string $id): array
    {
        $source = (new SourceManager($this->settings))->get($id);

        return $source === null
            ? ['ok' => false, 'message' => __('منبع ناشناخته است.', 'signteb-video-hub')]
            : $source->test_connection();
    }

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $video_id = (int) $request->get_param('video_id');
        $task     = sanitize_key((string) $request->get_param('task'));

        if (get_post_type($video_id) !== PostType::POST_TYPE) {
            return new WP_REST_Response(['ok' => false, 'message' => __('ویدئو پیدا نشد.', 'signteb-video-hub')], 404);
        }
        if (! in_array($task, AiQueueRepository::TASKS, true)) {
            return new WP_REST_Response(['ok' => false, 'message' => __('نوع کار نامعتبر است.', 'signteb-video-hub')], 400);
        }

        $result = (new AiWorker($this->settings))->run_job($video_id, $task);

        if ($result['ok']) {
            (new CacheManager($this->settings))->purge_post($video_id);
        }

        return new WP_REST_Response([
            'ok'      => $result['ok'],
            'message' => $result['ok']
                ? __('محتوا با موفقیت تولید شد.', 'signteb-video-hub')
                : (string) ($result['error'] ?? __('تولید محتوا ناموفق بود.', 'signteb-video-hub')),
        ], 200);
    }

    public function run_queue(): WP_REST_Response
    {
        $result = (new AiWorker($this->settings))->run();

        return new WP_REST_Response([
            'ok'      => true,
            'message' => sprintf(
                /* translators: 1: processed jobs, 2: failed jobs */
                __('%1$d کار پردازش شد، %2$d ناموفق.', 'signteb-video-hub'),
                $result['processed'],
                $result['failed']
            ),
            'data'    => $result,
        ], 200);
    }

    public function publish_article(WP_REST_Request $request): WP_REST_Response
    {
        $video_id = (int) $request->get_param('video_id');
        $result   = (new ArticleSuggester(null, $this->settings))->publish_as_draft($video_id);

        if (! $result['ok']) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => (string) ($result['error'] ?? ''),
            ], 400);
        }

        return new WP_REST_Response([
            'ok'      => true,
            'message' => __('پیش‌نویس مقاله ساخته شد.', 'signteb-video-hub'),
            'edit'    => get_edit_post_link((int) $result['post_id'], 'raw'),
        ], 200);
    }

    /**
     * Backfill already-imported videos: clean slugs, local featured images and
     * a real post body. Runs in batches so a large library cannot time out.
     */
    public function repair(WP_REST_Request $request): WP_REST_Response
    {
        $repair   = new Repair($this->settings);
        $video_id = (int) $request->get_param('video_id');

        if ($video_id > 0) {
            $one = $repair->run_one($video_id);
            (new CacheManager($this->settings))->purge_post($video_id);

            $changed = $one['slug'] || $one['thumbnail'] || $one['body'];

            return new WP_REST_Response([
                'ok' => true,
                // The editor still holds the pre-repair title, slug and body.
                // Without a reload the admin sees no change, and saving the
                // stale form would write the old values straight back over the
                // repair. Reloading is part of the fix, not a nicety.
                'reload'  => $changed,
                'message' => sprintf(
                    /* translators: 1: slug state, 2: image state, 3: body state */
                    __('نامک: %1$s — تصویر: %2$s — متن: %3$s', 'signteb-video-hub'),
                    $one['slug'] ? __('اصلاح شد', 'signteb-video-hub') : __('بدون تغییر', 'signteb-video-hub'),
                    $one['thumbnail'] ? __('ذخیره شد', 'signteb-video-hub') : __('بدون تغییر', 'signteb-video-hub'),
                    $one['body'] ? __('نوشته شد', 'signteb-video-hub') : __('بدون تغییر', 'signteb-video-hub')
                ),
            ], 200);
        }

        $result = $repair->run();

        if ($result['processed'] > 0) {
            (new CacheManager($this->settings))->purge_all();
        }

        return new WP_REST_Response([
            'ok'      => true,
            'message' => sprintf(
                /* translators: 1: processed, 2: slugs, 3: images, 4: bodies, 5: remaining */
                __('%1$d ویدئو بررسی شد — %2$d نامک، %3$d تصویر، %4$d متن. %5$d باقی مانده.', 'signteb-video-hub'),
                $result['processed'],
                $result['slugs'],
                $result['thumbnails'],
                $result['bodies'],
                $result['remaining']
            ),
            'data'    => $result,
        ], 200);
    }

    public function purge_cache(): WP_REST_Response
    {
        (new CacheManager($this->settings))->purge_all();
        VideoSitemap::flush();

        return new WP_REST_Response([
            'ok'      => true,
            'message' => __('کش پاک شد.', 'signteb-video-hub'),
        ], 200);
    }

    public function ping_index(WP_REST_Request $request): WP_REST_Response
    {
        $video_id = (int) $request->get_param('video_id');
        $queue    = new IndexingQueue($this->settings);

        if ($video_id > 0) {
            $result = (new IndexingClient($this->settings))->publish((string) get_permalink($video_id));
            return new WP_REST_Response($result, 200);
        }

        $sent = $queue->process();

        return new WP_REST_Response([
            'ok'      => true,
            'message' => sprintf(
                /* translators: %d: number of URLs sent to Google */
                __('%d آدرس به گوگل ارسال شد.', 'signteb-video-hub'),
                $sent
            ),
        ], 200);
    }
}

<?php

namespace SignTeb\VideoHub\Core;

use SignTeb\VideoHub\Admin\AdminMenu;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Cron\Scheduler;
use SignTeb\VideoHub\Db\AiQueueRepository;
use SignTeb\VideoHub\Elementor\ElementorBridge;
use SignTeb\VideoHub\Front\Assets;
use SignTeb\VideoHub\Front\Shortcodes;
use SignTeb\VideoHub\Front\TemplateLoader;
use SignTeb\VideoHub\Rest\RestNamespace;
use SignTeb\VideoHub\Schema\SchemaGenerator;
use SignTeb\VideoHub\Seo\IndexingQueue;
use SignTeb\VideoHub\Seo\SocialMeta;
use SignTeb\VideoHub\Seo\VideoSitemap;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Wires WordPress hooks to the plugin subsystems.
 *
 * Everything that must exist regardless of context (post type, cron, REST,
 * sitemap) is registered unconditionally; the front-end and admin halves are
 * mutually exclusive.
 */
class Plugin
{
    private bool $booted = false;

    private Settings $settings;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->settings = new Settings();

        load_plugin_textdomain('signteb-video-hub', false, dirname(STVH_BASENAME) . '/languages');

        // Content model and background work exist in every context.
        (new PostType())->register();
        (new Scheduler($this->settings))->register();
        (new RestNamespace($this->settings))->register();
        (new IndexingQueue($this->settings))->register();
        (new VideoSitemap($this->settings))->register();

        Activator::maybe_upgrade();

        $this->register_cache_invalidation();

        if (ElementorBridge::is_active()) {
            (new ElementorBridge())->register();
        }

        if (is_admin()) {
            (new AdminMenu($this->settings))->register();
            return;
        }

        if (! $this->settings->is_enabled()) {
            return;
        }

        (new Assets($this->settings))->register();
        (new Shortcodes($this->settings))->register();
        (new TemplateLoader())->register();
        (new SchemaGenerator($this->settings))->register();
        (new SocialMeta($this->settings))->register();
    }

    /**
     * Any change to a video invalidates the cached grids, the sitemap and the
     * page caches in front of WordPress.
     */
    private function register_cache_invalidation(): void
    {
        $invalidate = function ($post_id) : void {
            $post_id = (int) $post_id;
            if (get_post_type($post_id) !== PostType::POST_TYPE) {
                return;
            }
            (new CacheManager($this->settings))->purge_post($post_id);
            VideoSitemap::flush();
        };

        add_action('save_post_' . PostType::POST_TYPE, $invalidate, 20);

        // before_delete_post, not deleted_post: the post type is still
        // readable here, so the guard above can still identify a video.
        add_action('before_delete_post', $invalidate, 20);
        add_action('before_delete_post', static function ($post_id): void {
            $post_id = (int) $post_id;
            if (get_post_type($post_id) === PostType::POST_TYPE) {
                (new AiQueueRepository())->delete_for_video($post_id);
            }
        }, 20);
    }
}

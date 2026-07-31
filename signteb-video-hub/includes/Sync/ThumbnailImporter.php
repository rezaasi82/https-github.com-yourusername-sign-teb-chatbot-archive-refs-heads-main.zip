<?php

namespace SignTeb\VideoHub\Sync;

use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Copies the provider poster into the media library and sets it as the
 * featured image.
 *
 * Hotlinking the provider CDN looked cheaper but breaks in practice: Aparat
 * serves posters behind referer checks, so the image renders in the browser
 * tab yet 403s when embedded on another domain — which is why the admin
 * metabox and the cards showed broken thumbnails. Owning a local copy also
 * means the poster survives the video being edited or pulled at the source,
 * and gives themes, RankMath and OpenGraph a real attachment to work with.
 */
class ThumbnailImporter
{
    /**
     * Deliberately short. This runs once per imported video, so a generous
     * figure multiplies: 30 seconds across 30 videos is fifteen minutes of a
     * held-open request. A poster that cannot be fetched in a few seconds is
     * better retried on the next pass than allowed to stall the run.
     */
    private const DOWNLOAD_TIMEOUT = 8;

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function is_enabled(): bool
    {
        return $this->settings->bool('import_thumbnails');
    }

    /**
     * Import the poster for one video. Returns the attachment id, or 0.
     */
    public function import(int $post_id): int
    {
        if (! $this->is_enabled() || $post_id <= 0) {
            return 0;
        }

        // Never overwrite a featured image an editor chose by hand.
        $existing = (int) get_post_thumbnail_id($post_id);
        if ($existing > 0) {
            return $existing;
        }

        $url = (string) get_post_meta($post_id, VideoMeta::THUMBNAIL, true);
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return 0;
        }

        $attachment_id = $this->sideload($url, $post_id);
        if ($attachment_id <= 0) {
            return 0;
        }

        set_post_thumbnail($post_id, $attachment_id);
        update_post_meta($post_id, '_stvh_thumbnail_attachment', $attachment_id);

        return $attachment_id;
    }

    /**
     * Download a remote image into the media library.
     */
    private function sideload(string $url, int $post_id): int
    {
        // These are only loaded in admin contexts by default; sync also runs
        // from cron and REST, so they have to be required explicitly.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // A referer-checking CDN needs the request to look like a plain fetch.
        $temp = $this->download($url);
        if ($temp === '') {
            return 0;
        }

        $file = [
            'name'     => $this->filename($url, $post_id),
            'tmp_name' => $temp,
        ];

        $attachment_id = media_handle_sideload($file, $post_id, null, ['post_title' => get_the_title($post_id)]);

        if (is_wp_error($attachment_id)) {
            // media_handle_sideload cleans up on success only.
            if (file_exists($temp)) {
                wp_delete_file($temp);
            }
            Logger::warning('thumbnail', $attachment_id->get_error_message(), ['post_id' => $post_id]);
            return 0;
        }

        return (int) $attachment_id;
    }

    /**
     * download_url() sends no referer, which is what gets us past Aparat's
     * hotlink protection — but it also follows redirects into HTML error
     * pages, so the payload is verified to be an image before it is accepted.
     */
    private function download(string $url): string
    {
        $temp = download_url($url, self::DOWNLOAD_TIMEOUT);

        if (is_wp_error($temp)) {
            Logger::warning('thumbnail', $temp->get_error_message(), ['url' => $url]);
            return '';
        }

        $type = wp_getimagesize($temp);
        if ($type === false) {
            wp_delete_file($temp);
            Logger::warning('thumbnail', 'پاسخ منبع تصویر معتبر نبود.', ['url' => $url]);
            return '';
        }

        return $temp;
    }

    /**
     * A filename derived from the post, not the CDN path: provider URLs carry
     * query strings and opaque hashes that make for unusable media titles.
     */
    private function filename(string $url, int $post_id): string
    {
        $path      = (string) wp_parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $slug = (string) get_post_field('post_name', $post_id);
        if ($slug === '') {
            $slug = 'video-' . $post_id;
        }

        return $slug . '.' . $extension;
    }
}

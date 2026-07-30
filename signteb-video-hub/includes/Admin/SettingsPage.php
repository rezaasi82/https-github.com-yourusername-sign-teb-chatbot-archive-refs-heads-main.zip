<?php

namespace SignTeb\VideoHub\Admin;

use SignTeb\VideoHub\Ai\InternalLinker;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Cron\Scheduler;
use SignTeb\VideoHub\Seo\VideoSitemap;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The settings screen. Saves through a single nonce-protected POST rather
 * than the Settings API so secrets can be handled separately from the option
 * array (and never round-trip through the page in plaintext).
 */
class SettingsPage
{
    private const NONCE  = 'stvh_save_settings';
    private const ACTION = 'stvh_settings_submit';

    /** Secret fields keep this placeholder when already stored. */
    private const MASK = '••••••••';

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

        // Re-read so the view reflects a save that happened this request.
        $settings   = new Settings();
        $values     = $settings->all();
        $has_secret = [];
        foreach (array_keys(Settings::SECRETS) as $name) {
            $has_secret[$name] = $settings->has_secret($name);
        }

        $data = [
            'values'      => $values,
            'has_secret'  => $has_secret,
            'mask'        => self::MASK,
            'nonce_field' => wp_nonce_field(self::NONCE, '_stvh_nonce', true, false),
            'action'      => self::ACTION,
            'intervals'   => Scheduler::INTERVALS,
            'sitemap_url' => VideoSitemap::url(),
            'saved'       => isset($_GET['stvh_saved']), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ];

        require STVH_DIR . 'includes/Admin/views/settings.php';
    }

    public function handle_save(): void
    {
        if (! isset($_POST['stvh_action']) || sanitize_key(wp_unslash($_POST['stvh_action'])) !== self::ACTION) {
            return;
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی لازم را ندارید.', 'signteb-video-hub'));
        }

        $nonce = isset($_POST['_stvh_nonce']) ? sanitize_text_field(wp_unslash($_POST['_stvh_nonce'])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE)) {
            wp_die(esc_html__('اعتبارسنجی فرم ناموفق بود.', 'signteb-video-hub'));
        }

        $this->save_values($this->collect());
        $this->save_secrets();

        // Anything cached from the old configuration is now wrong.
        (new CacheManager())->purge_all();
        VideoSitemap::flush();
        InternalLinker::flush_candidates();
        flush_rewrite_rules(false);

        /** Fires after the settings are persisted (Scheduler re-anchors cron). */
        do_action('stvh_settings_saved');

        wp_safe_redirect(add_query_arg('stvh_saved', '1', admin_url('admin.php?page=stvh-settings')));
        exit;
    }

    /**
     * Sanitize the submitted, non-secret fields.
     *
     * @return array<string,mixed>
     */
    private function collect(): array
    {
        // Nonce is verified in handle_save() before this runs.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $post = wp_unslash($_POST);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $checkboxes = [
            'enabled', 'auto_publish', 'import_thumbnails', 'ai_enabled', 'ai_auto_summary', 'ai_auto_links',
            'ai_auto_article', 'ai_write_content', 'schema_enabled', 'social_meta', 'sitemap_enabled',
            'analytics_enabled', 'google_indexing', 'cache_purge', 'hub_enabled',
        ];

        $values = [];
        foreach ($checkboxes as $key) {
            $values[$key] = isset($post[$key]) ? 1 : 0;
        }

        $text = [
            'aparat_username', 'youtube_channel', 'ai_model', 'physician_name',
            'physician_specialty', 'clinic_name', 'cloudflare_zone',
        ];
        foreach ($text as $key) {
            $values[$key] = sanitize_text_field((string) ($post[$key] ?? ''));
        }

        $urls = ['ai_base_url', 'physician_url', 'booking_url'];
        foreach ($urls as $key) {
            $values[$key] = esc_url_raw((string) ($post[$key] ?? ''));
        }

        $values['sync_interval'] = isset(Scheduler::INTERVALS[(string) ($post['sync_interval'] ?? '')])
            ? (string) $post['sync_interval']
            : 'stvh_12h';

        $values['ai_provider'] = in_array((string) ($post['ai_provider'] ?? ''), ['anthropic', 'openai', 'gapgpt'], true)
            ? (string) $post['ai_provider']
            : 'anthropic';

        $values['dark_mode'] = in_array((string) ($post['dark_mode'] ?? ''), ['auto', 'light', 'dark'], true)
            ? (string) $post['dark_mode']
            : 'auto';

        $accent = sanitize_hex_color((string) ($post['accent_color'] ?? ''));
        $values['accent_color'] = $accent !== null && $accent !== '' ? $accent : '#0a84ff';

        $values['sync_limit']          = max(1, min(100, (int) ($post['sync_limit'] ?? 30)));
        $values['ai_batch_size']       = max(1, min(10, (int) ($post['ai_batch_size'] ?? 3)));
        $values['cards_per_page']      = max(1, min(48, (int) ($post['cards_per_page'] ?? 12)));
        $values['analytics_retention'] = max(7, min(730, (int) ($post['analytics_retention'] ?? 180)));

        $values['internal_link_map'] = sanitize_textarea_field((string) ($post['internal_link_map'] ?? ''));

        return $values;
    }

    /**
     * @param array<string,mixed> $values
     */
    private function save_values(array $values): void
    {
        // Preserve any key the form does not expose.
        Settings::save(array_merge($this->settings->all(), $values));
    }

    /**
     * A secret field left at the mask means "unchanged"; clearing it deletes
     * the stored value.
     */
    private function save_secrets(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $post = wp_unslash($_POST);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        foreach (array_keys(Settings::SECRETS) as $name) {
            if (! array_key_exists($name, $post)) {
                continue;
            }

            $value = trim((string) $post[$name]);
            if ($value === self::MASK) {
                continue;
            }

            // The Google credential is a JSON blob; the rest are single-line keys.
            $value = $name === 'google_service_json'
                ? sanitize_textarea_field($value)
                : sanitize_text_field($value);

            Settings::save_secret($name, $value);
        }
    }
}

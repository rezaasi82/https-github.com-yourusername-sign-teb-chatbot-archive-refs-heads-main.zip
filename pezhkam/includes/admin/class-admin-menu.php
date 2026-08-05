<?php
/**
 * Registers the "Pezhkam" menu and a single tabbed page.
 *
 * Tabs: AI Provider | Clinic | Appearance | Conversations | Stats | License.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
    private \Pezhkam\Admin\SettingsPage $page;

    public function __construct()
    {
        $this->page = new \Pezhkam\Admin\SettingsPage();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this->page, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        // Unseen-chat badge, same pattern as the core Comments bubble.
        $unseen = (new \Pezhkam\Admin\ChatNotifier())->unseen_count();
        $title  = __('پژکام', 'pezhkam');
        $badge  = sprintf(
            ' <span class="awaiting-mod pzk-menu-count"%s>%s</span>',
            $unseen > 0 ? '' : ' style="display:none"',
            esc_html(number_format_i18n($unseen))
        );

        add_menu_page(
            __('پژکام', 'pezhkam'),
            $title . $badge,
            'manage_options',
            'pzk-chat',
            [$this->page, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue(string $hook): void
    {
        // Every plugin admin page (pzk-chat, pzk-seo, pzk-branches, …) shares
        // these assets; anything more page-specific enqueues its own on top.
        if (strpos($hook, '_page_pzk-') === false && strpos($hook, 'pzk-chat') === false) {
            return;
        }
        wp_enqueue_style('pzk-admin', PZK_URL . 'assets/css/admin.css', [], PZK_VERSION);
        wp_enqueue_script('pzk-admin', PZK_URL . 'assets/js/admin.js', [], PZK_VERSION, true);
        wp_localize_script('pzk-admin', 'PZK_ADMIN', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('pzk_export'),
            'strings' => [
                'working' => __('در حال انجام…', 'pezhkam'),
                'ok'      => __('موفق', 'pezhkam'),
                'failed'  => __('ناموفق', 'pezhkam'),
                'noSel'   => __('ابتدا چند مورد را انتخاب کنید.', 'pezhkam'),
                'queued'  => __('در صف پردازش پس‌زمینه', 'pezhkam'),
            ],
        ]);
    }
}

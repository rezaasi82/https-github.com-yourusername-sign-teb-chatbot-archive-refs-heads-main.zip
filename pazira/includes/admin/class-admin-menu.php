<?php
/**
 * Registers the "Pazira" menu and a single tabbed page.
 *
 * Tabs: AI Provider | Clinic | Appearance | Conversations | Stats | License.
 *
 * @package Pazira
 */

namespace Pazira\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
    private \Pazira\Admin\SettingsPage $page;

    public function __construct()
    {
        $this->page = new \Pazira\Admin\SettingsPage();
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
        $unseen = (new \Pazira\Admin\ChatNotifier())->unseen_count();
        $title  = __('Pazira', 'pazira');
        $badge  = sprintf(
            ' <span class="awaiting-mod pzr-menu-count"%s>%s</span>',
            $unseen > 0 ? '' : ' style="display:none"',
            esc_html(number_format_i18n($unseen))
        );

        add_menu_page(
            __('Pazira', 'pazira'),
            $title . $badge,
            'manage_options',
            'pzr-chat',
            [$this->page, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue(string $hook): void
    {
        // Every plugin admin page (pzr-chat, pzr-seo, pzr-branches, …) shares
        // these assets; anything more page-specific enqueues its own on top.
        if (strpos($hook, '_page_pzr-') === false && strpos($hook, 'pzr-chat') === false) {
            return;
        }
        wp_enqueue_style('pzr-admin', PZR_URL . 'assets/css/admin.css', [], PZR_VERSION);
        wp_enqueue_script('pzr-admin', PZR_URL . 'assets/js/admin.js', [], PZR_VERSION, true);
        wp_localize_script('pzr-admin', 'PZR_ADMIN', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('pzr_export'),
            'strings' => [
                'working' => __('در حال انجام…', 'pazira'),
                'ok'      => __('موفق', 'pazira'),
                'failed'  => __('ناموفق', 'pazira'),
                'noSel'   => __('ابتدا چند مورد را انتخاب کنید.', 'pazira'),
                'queued'  => __('در صف پردازش پس‌زمینه', 'pazira'),
            ],
        ]);
    }
}

<?php
/**
 * Registers the "Clinovix" menu and a single tabbed page.
 *
 * Tabs: AI Provider | Clinic | Appearance | Conversations | Stats | License.
 *
 * @package Clinovix
 */

namespace Clinovix\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
    private \Clinovix\Admin\SettingsPage $page;

    public function __construct()
    {
        $this->page = new \Clinovix\Admin\SettingsPage();
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
        $unseen = (new \Clinovix\Admin\ChatNotifier())->unseen_count();
        $title  = __('Clinovix AI', 'clinovix');
        $badge  = sprintf(
            ' <span class="awaiting-mod clx-menu-count"%s>%s</span>',
            $unseen > 0 ? '' : ' style="display:none"',
            esc_html(number_format_i18n($unseen))
        );

        add_menu_page(
            __('Clinovix AI', 'clinovix'),
            $title . $badge,
            'manage_options',
            'clx-chat',
            [$this->page, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue(string $hook): void
    {
        // Every plugin admin page (clx-chat, clx-seo, clx-branches, …) shares
        // these assets; anything more page-specific enqueues its own on top.
        if (strpos($hook, '_page_clx-') === false && strpos($hook, 'clx-chat') === false) {
            return;
        }
        wp_enqueue_style('clx-admin', CLX_URL . 'assets/css/admin.css', [], CLX_VERSION);
        wp_enqueue_script('clx-admin', CLX_URL . 'assets/js/admin.js', [], CLX_VERSION, true);
        wp_localize_script('clx-admin', 'CLX_ADMIN', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('clx_export'),
            'strings' => [
                'working' => __('در حال انجام…', 'clinovix'),
                'ok'      => __('موفق', 'clinovix'),
                'failed'  => __('ناموفق', 'clinovix'),
                'noSel'   => __('ابتدا چند مورد را انتخاب کنید.', 'clinovix'),
                'queued'  => __('در صف پردازش پس‌زمینه', 'clinovix'),
            ],
        ]);
    }
}

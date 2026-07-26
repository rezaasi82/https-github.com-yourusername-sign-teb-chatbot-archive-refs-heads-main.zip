<?php
/**
 * Registers the "Medora AI" menu and a single tabbed page.
 *
 * Tabs: AI Provider | Clinic | Appearance | Conversations | Stats | License.
 *
 * @package Medora
 */

namespace Medora\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
    private \Medora\Admin\SettingsPage $page;

    public function __construct()
    {
        $this->page = new \Medora\Admin\SettingsPage();
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
        $unseen = (new \Medora\Admin\ChatNotifier())->unseen_count();
        $title  = __('Medora AI', 'signteb-web-chat');
        $badge  = sprintf(
            ' <span class="awaiting-mod swc-menu-count"%s>%s</span>',
            $unseen > 0 ? '' : ' style="display:none"',
            esc_html(number_format_i18n($unseen))
        );

        add_menu_page(
            __('Medora AI', 'signteb-web-chat'),
            $title . $badge,
            'manage_options',
            'swc-chat',
            [$this->page, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue(string $hook): void
    {
        // Every plugin admin page (swc-chat, swc-seo, swc-branches, …) shares
        // these assets; anything more page-specific enqueues its own on top.
        if (strpos($hook, '_page_swc-') === false && strpos($hook, 'swc-chat') === false) {
            return;
        }
        wp_enqueue_style('swc-admin', SWC_URL . 'assets/css/admin.css', [], SWC_VERSION);
        wp_enqueue_script('swc-admin', SWC_URL . 'assets/js/admin.js', [], SWC_VERSION, true);
        wp_localize_script('swc-admin', 'SWC_ADMIN', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('swc_export'),
            'strings' => [
                'working' => __('در حال انجام…', 'signteb-web-chat'),
                'ok'      => __('موفق', 'signteb-web-chat'),
                'failed'  => __('ناموفق', 'signteb-web-chat'),
                'noSel'   => __('ابتدا چند مورد را انتخاب کنید.', 'signteb-web-chat'),
                'queued'  => __('در صف پردازش پس‌زمینه', 'signteb-web-chat'),
            ],
        ]);
    }
}

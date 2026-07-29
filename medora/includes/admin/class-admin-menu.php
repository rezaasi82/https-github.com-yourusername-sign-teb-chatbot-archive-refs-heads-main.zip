<?php
/**
 * Registers the "Medora" menu and a single tabbed page.
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
        $title  = __('Medora AI', 'medora');
        $badge  = sprintf(
            ' <span class="awaiting-mod mdr-menu-count"%s>%s</span>',
            $unseen > 0 ? '' : ' style="display:none"',
            esc_html(number_format_i18n($unseen))
        );

        add_menu_page(
            __('Medora AI', 'medora'),
            $title . $badge,
            'manage_options',
            'mdr-chat',
            [$this->page, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue(string $hook): void
    {
        // Every plugin admin page (mdr-chat, mdr-seo, mdr-branches, …) shares
        // these assets; anything more page-specific enqueues its own on top.
        if (strpos($hook, '_page_mdr-') === false && strpos($hook, 'mdr-chat') === false) {
            return;
        }
        wp_enqueue_style('mdr-admin', MDR_URL . 'assets/css/admin.css', [], MDR_VERSION);
        wp_enqueue_script('mdr-admin', MDR_URL . 'assets/js/admin.js', [], MDR_VERSION, true);
        wp_localize_script('mdr-admin', 'MDR_ADMIN', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('mdr_export'),
            'strings' => [
                'working' => __('در حال انجام…', 'medora'),
                'ok'      => __('موفق', 'medora'),
                'failed'  => __('ناموفق', 'medora'),
                'noSel'   => __('ابتدا چند مورد را انتخاب کنید.', 'medora'),
                'queued'  => __('در صف پردازش پس‌زمینه', 'medora'),
            ],
        ]);
    }
}

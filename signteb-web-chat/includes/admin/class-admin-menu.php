<?php
/**
 * SWC_Admin_Menu — registers the "SignTeb Chat" menu and a single tabbed page.
 *
 * Tabs: AI Provider | Clinic | Appearance | Conversations | Stats | License.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Admin_Menu
{
    private SWC_Settings_Page $page;

    public function __construct()
    {
        $this->page = new SWC_Settings_Page();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this->page, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('SignTeb Chat', 'signteb-web-chat'),
            __('SignTeb Chat', 'signteb-web-chat'),
            'manage_options',
            'swc-chat',
            [$this->page, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    public function enqueue(string $hook): void
    {
        if (strpos($hook, 'swc-chat') === false) {
            return;
        }
        wp_enqueue_style('swc-admin', SWC_URL . 'assets/css/admin.css', [], SWC_VERSION);
        wp_enqueue_script('swc-admin', SWC_URL . 'assets/js/admin.js', [], SWC_VERSION, true);
    }
}

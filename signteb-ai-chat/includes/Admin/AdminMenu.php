<?php

namespace STMC_Chat\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the "SignTeb" → "AI Chat" admin menu and its subpages.
 */
class AdminMenu
{
    private SettingsPage $settings;
    private ConversationsPage $conversations;
    private StatsPage $stats;

    public function __construct()
    {
        $this->settings      = new SettingsPage();
        $this->conversations = new ConversationsPage();
        $this->stats         = new StatsPage();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this->settings, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        $cap = 'manage_options';

        add_menu_page(
            __('SignTeb AI Chat', 'signteb-ai-chat'),
            __('SignTeb', 'signteb-ai-chat'),
            $cap,
            'stmc-chat',
            [$this->stats, 'render'],
            'dashicons-format-chat',
            58
        );

        add_submenu_page('stmc-chat', __('داشبورد', 'signteb-ai-chat'), __('داشبورد', 'signteb-ai-chat'), $cap, 'stmc-chat', [$this->stats, 'render']);
        add_submenu_page('stmc-chat', __('تنظیمات', 'signteb-ai-chat'), __('تنظیمات', 'signteb-ai-chat'), $cap, 'stmc-chat-settings', [$this->settings, 'render']);
        add_submenu_page('stmc-chat', __('مکالمات', 'signteb-ai-chat'), __('مکالمات', 'signteb-ai-chat'), $cap, 'stmc-chat-conversations', [$this->conversations, 'render']);
    }

    public function enqueue(string $hook): void
    {
        if (strpos($hook, 'stmc-chat') === false) {
            return;
        }
        wp_enqueue_style('stmc-chat-admin', STMC_CHAT_URL . 'assets/css/admin.css', [], STMC_CHAT_VERSION);
    }
}

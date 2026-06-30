<?php

namespace STMC_Chat\Frontend;

use STMC_Chat\Core\Settings;
use STMC_Chat\Integration\MedicalCoreBridge;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Floating chat widget. Enqueues vanilla JS/CSS (no jQuery) and prints the
 * markup in the footer. Assets only load when the widget is actually rendered.
 */
class Widget
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'render']);
    }

    private function should_render(): bool
    {
        if (! $this->settings->is_enabled()) {
            return false;
        }
        if (is_admin() || is_feed() || is_robots()) {
            return false;
        }
        return (bool) apply_filters('stmc_chat_should_render', true);
    }

    public function enqueue(): void
    {
        if (! $this->should_render()) {
            return;
        }

        wp_enqueue_style(
            'stmc-chat-widget',
            STMC_CHAT_URL . 'assets/css/widget.css',
            [],
            STMC_CHAT_VERSION
        );

        wp_enqueue_script(
            'stmc-chat-widget',
            STMC_CHAT_URL . 'assets/js/widget.js',
            [],
            STMC_CHAT_VERSION,
            true
        );

        wp_localize_script('stmc-chat-widget', 'STMC_CHAT', [
            'restUrl'   => esc_url_raw(rest_url('signteb-ai-chat/v1/message')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl'   => esc_url_raw(admin_url('admin-ajax.php')),
            'ajaxNonce' => wp_create_nonce('stmc_chat_nonce'),
            'pageUrl'   => esc_url_raw((string) ( $_SERVER['REQUEST_URI'] ?? '' )),
            'strings'   => [
                'placeholder' => __('پیام خود را بنویسید…', 'signteb-ai-chat'),
                'send'        => __('ارسال', 'signteb-ai-chat'),
                'title'       => __('دستیار هوشمند', 'signteb-ai-chat'),
                'typing'      => __('در حال نوشتن…', 'signteb-ai-chat'),
                'book'        => __('رزرو نوبت', 'signteb-ai-chat'),
                'whatsapp'    => __('واتس‌اپ', 'signteb-ai-chat'),
                'call'        => __('تماس تلفنی', 'signteb-ai-chat'),
                'error'       => __('خطا در ارتباط. دوباره تلاش کنید.', 'signteb-ai-chat'),
            ],
        ]);
    }

    public function render(): void
    {
        if (! $this->should_render()) {
            return;
        }

        $bridge = new MedicalCoreBridge($this->settings);
        $nap    = $bridge->nap();
        $s      = $this->settings;

        $bot_name = trim((string) $s->get('bot_name', ''));
        if ($bot_name === '') {
            $bot_name = (string) ($nap['clinic_name'] ?: __('دستیار هوشمند', 'signteb-ai-chat'));
        }

        $config = [
            'widget_color'    => (string) $s->get('widget_color', '#0f1f3d'),
            'accent_color'    => (string) $s->get('accent_color', '#c8a04e'),
            'welcome'         => (string) $s->get('welcome_message', ''),
            'offhours'        => (string) $s->get('offhours_message', ''),
            'quick_replies'   => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $s->get('quick_replies', ''))))),
            'within_hours'    => $this->within_business_hours(),
            'nap'             => $nap,
            // White-label fields.
            'bot_name'        => $bot_name,
            'avatar_url'      => esc_url((string) $s->get('avatar_url', '')),
            'show_branding'   => (bool) $s->get('show_branding', 1),
            'powered_by_text' => (string) $s->get('powered_by_text', ''),
            'powered_by_url'  => (string) $s->get('powered_by_url', ''),
        ];

        // Template handles all escaping.
        include STMC_CHAT_DIR . 'templates/widget.php';
    }

    private function within_business_hours(): bool
    {
        $raw = trim((string) $this->settings->get('business_hours', ''));
        if ($raw === '') {
            return true; // unset = always "open"
        }
        // Format: "HH:MM-HH:MM" in site timezone; lenient parse.
        if (! preg_match('/(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})/', $raw, $m)) {
            return true;
        }
        $now   = (int) current_time('Hi');
        $start = (int) sprintf('%02d%02d', (int) $m[1], (int) $m[2]);
        $end   = (int) sprintf('%02d%02d', (int) $m[3], (int) $m[4]);
        return $now >= $start && $now <= $end;
    }
}

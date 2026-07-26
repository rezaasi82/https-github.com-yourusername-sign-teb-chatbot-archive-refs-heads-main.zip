<?php
/**
 * Floating chat widget.
 *
 * Enqueues vanilla JS/CSS (no jQuery) and prints the markup in the footer.
 * Assets load ONLY when the widget actually renders, so an install never slows
 * down pages where the widget is not shown.
 *
 * @package Pazira
 */

namespace Pazira\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

class Widget
{
    private \Pazira\Core\Settings $settings;

    public function __construct()
    {
        $this->settings = new \Pazira\Core\Settings();
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
        return (bool) apply_filters('pzr_should_render', true);
    }

    public function enqueue(): void
    {
        if (! $this->should_render()) {
            return;
        }
        $this->enqueue_assets();
    }

    /**
     * Register + enqueue the widget CSS/JS and localize config. Idempotent, so
     * both the footer widget and the [pazira_chat] shortcode can call it.
     */
    public function enqueue_assets(): void
    {
        if (wp_script_is('pzr-widget', 'enqueued')) {
            return;
        }

        // Optional self-hosted Vazirmatn font (only if the file is bundled).
        if ((int) $this->settings->get('use_bundled_font', 1) === 1
            && file_exists(PZR_DIR . 'assets/fonts/vazirmatn.woff2')) {
            wp_enqueue_style('pzr-font', PZR_URL . 'assets/fonts/font.css', [], PZR_VERSION);
        }

        wp_enqueue_style('pzr-widget', PZR_URL . 'assets/css/widget.css', [], PZR_VERSION);
        wp_enqueue_script('pzr-widget', PZR_URL . 'assets/js/widget.js', [], PZR_VERSION, true);

        wp_localize_script('pzr-widget', 'PZR_CONFIG', [
            'restUrl'   => esc_url_raw(rest_url('pazira/v1/message')),
            'eventUrl'  => esc_url_raw(rest_url('pazira/v1/event')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl'   => esc_url_raw(admin_url('admin-ajax.php')),
            'ajaxNonce' => wp_create_nonce('pzr_chat_nonce'),
            'pageUrl'   => \Pazira\Core\Input::request_uri(),
            'strings'   => [
                'placeholder' => __('پیام خود را بنویسید…', 'pazira'),
                'send'        => __('ارسال', 'pazira'),
                'typing'      => __('در حال نوشتن…', 'pazira'),
                'book'        => __('رزرو نوبت آنلاین', 'pazira'),
                'whatsapp'    => __('واتساپ', 'pazira'),
                'call'        => __('تماس با مطب', 'pazira'),
                'bale'        => __('ارتباط در بله', 'pazira'),
                'error'       => __('خطا در ارتباط. دوباره تلاش کنید.', 'pazira'),
                'ctaTitle'    => __('آماده دریافت نوبت هستید؟', 'pazira'),
                'ctaText'     => __('برای رزرو آنلاین و انتخاب زمان مراجعه روی دکمه زیر کلیک کنید.', 'pazira'),
            ],
        ]);
    }

    public function render(): void
    {
        if (! $this->should_render()) {
            return;
        }
        $config = $this->build_config(false);
        // Template handles all escaping.
        include PZR_DIR . 'templates/widget.php';
    }

    /**
     * Inline (embedded) render for the [pazira_chat] shortcode / sidebar block.
     * Returns the markup instead of echoing so it can nest anywhere.
     */
    public function render_inline(): string
    {
        // Same gate as the floating widget, minus the "is this a normal page"
        // checks — a shortcode is only reached on a rendered page anyway.
        if (! $this->settings->is_enabled()) {
            return '';
        }
        $this->enqueue_assets();
        $config = $this->build_config(true);

        ob_start();
        include PZR_DIR . 'templates/widget.php';
        return (string) ob_get_clean();
    }

    /**
     * Assemble the view config shared by the floating and inline renders.
     *
     * @return array<string,mixed>
     */
    private function build_config(bool $inline): array
    {
        $s = $this->settings;
        return [
            'direction'     => $s->get('direction', 'rtl') === 'ltr' ? 'ltr' : 'rtl',
            'widget_color'  => (string) $s->get('widget_color', '#0f1f3d'),
            'accent_color'  => (string) $s->get('accent_color', '#c8a04e'),
            'bot_name'      => (string) $s->get('bot_name', __('دستیار هوشمند', 'pazira')),
            'avatar_url'    => esc_url_raw((string) $s->get('avatar_url', '')),
            'brand_footer'  => (string) $s->get('brand_footer', ''),
            'welcome'       => (string) $s->get('welcome_message', ''),
            'offhours'      => (string) $s->get('offhours_message', ''),
            'teaser'        => (string) $s->get('teaser_message', ''),
            'teaser_delay'  => max(0, (int) $s->get('teaser_delay', 3)),
            'teaser_sound'  => (int) $s->get('teaser_sound', 1) === 1,
            'inline'        => $inline,
            'quick_replies' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $s->get('quick_replies', ''))))),
            'within_hours'  => $this->within_business_hours(),
            'booking_url'   => esc_url_raw((string) $s->get('booking_url', '')),
            'whatsapp'      => (string) $s->get('whatsapp', ''),
            'phone'         => (string) $s->get('phone', ''),
            'bale_url'      => esc_url_raw((string) $s->get('bale_url', '')),
            'branch'        => (int) apply_filters('pzr_widget_branch', (int) $s->get('default_branch', 0)),
            'lead_capture'  => (int) $s->get('lead_capture', 1) === 1,
            'channels'      => [
                'booking'  => (int) $s->get('ch_booking', 1) === 1 && (string) $s->get('booking_url', '') !== '',
                'whatsapp' => (int) $s->get('ch_whatsapp', 1) === 1 && (string) $s->get('whatsapp', '') !== '',
                'call'     => (int) $s->get('ch_call', 1) === 1 && (string) $s->get('phone', '') !== '',
                'bale'     => (int) $s->get('ch_bale', 0) === 1 && (string) $s->get('bale_url', '') !== '',
            ],
        ];
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

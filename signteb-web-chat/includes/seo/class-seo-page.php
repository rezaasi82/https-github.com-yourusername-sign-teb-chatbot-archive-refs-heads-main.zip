<?php
/**
 * SWC_Seo_Page — the SEO Intelligence Center.
 *
 * Surfaces the conversation-mined SEO signals and, on demand, asks the active
 * AI provider to turn them into blog titles, FAQ ideas and SEO recommendations.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Seo_Page
{
    private const NONCE = 'swc_seo';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20); // after the parent menu (priority 10).
        add_action('wp_ajax_swc_seo_generate', [$this, 'ajax_generate']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'swc-chat',
            __('مرکز هوش سئو', 'signteb-web-chat'),
            __('مرکز سئو', 'signteb-web-chat'),
            'manage_options',
            'swc-seo',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $analyzer  = new SWC_Seo_Analyzer();
        $questions = $analyzer->top_questions(30, 15);
        $keywords  = $analyzer->keywords(30, 30);
        $topics    = $analyzer->topics(30);
        $nonce     = wp_create_nonce(self::NONCE);
        $cached    = get_transient('swc_seo_ideas');

        include SWC_DIR . 'includes/admin/views/seo.php';
    }

    public function ajax_generate(): void
    {
        SWC_Json_Guard::arm();

        if (SWC_Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            SWC_Security::note_failure('seo_generate');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }
        if (! (new SWC_License_Manager())->allows('seo')) {
            wp_send_json(['ok' => false, 'error' => __('این قابلیت نیازمند لایسنس فعال است.', 'signteb-web-chat')], 403);
        }
        // Protect the API budget: a few generations per hour is plenty.
        if (! SWC_Security::rate_limit('seo_generate', 10, HOUR_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => __('محدودیت درخواست. کمی بعد دوباره تلاش کنید.', 'signteb-web-chat')], 429);
        }

        $result = $this->generate();
        if (empty($result['ok'])) {
            wp_send_json(['ok' => false, 'error' => $result['error'] ?? 'error'], 400);
        }

        set_transient('swc_seo_ideas', $result['ideas'], 6 * HOUR_IN_SECONDS);
        SWC_Audit_Log::record('seo_generate', ['object' => 'ideas']);
        wp_send_json(['ok' => true, 'ideas' => $result['ideas']]);
    }

    /**
     * @return array{ok:bool,ideas?:string,error?:string}
     */
    private function generate(): array
    {
        $settings = new SWC_Settings();
        $provider = (new SWC_Provider_Factory($settings))->create_active();
        if ($provider === null) {
            return ['ok' => false, 'error' => __('کلید API تنظیم نشده است.', 'signteb-web-chat')];
        }

        $analyzer  = new SWC_Seo_Analyzer();
        $questions = array_map(static fn($r) => trim((string) $r->q), $analyzer->top_questions(30, 12));
        $keywords  = array_keys($analyzer->keywords(30, 25));

        if ($questions === [] && $keywords === []) {
            return ['ok' => false, 'error' => __('هنوز داده‌ی گفتگوی کافی برای تحلیل وجود ندارد.', 'signteb-web-chat')];
        }

        $clinic = (string) $settings->get('clinic_name', get_bloginfo('name'));
        $system = implode("\n", [
            "تو یک متخصص سئوی محتوای پزشکی برای «{$clinic}» هستی.",
            'بر اساس پرسش‌های واقعی بیماران و کلمات کلیدیِ داده‌شده، خروجی فارسی و کاربردی تولید کن با این ساختار دقیق:',
            '۱) ۶ ایده‌ی عنوان مقاله‌ی بلاگ سئوشده (هر کدام یک خط).',
            '۲) ۵ پرسش برای بخش سؤالات متداول (FAQ).',
            '۳) ۴ توصیه‌ی عملی سئو برای جذب بیمار بیشتر.',
            'کوتاه، دقیق و بدون مقدمه‌ی اضافه بنویس.',
        ]);
        $message = "پرسش‌های پرتکرار بیماران:\n- " . implode("\n- ", array_slice($questions, 0, 12))
            . "\n\nکلمات کلیدی پرتکرار:\n" . implode('، ', array_slice($keywords, 0, 25));

        $result = $provider->generate_reply($message, [
            'system'      => $system,
            'model'       => $settings->active_model(),
            'max_tokens'  => 900,
            'temperature' => 0.7,
        ]);

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => __('خطا در ارتباط با هوش مصنوعی.', 'signteb-web-chat')];
        }
        return ['ok' => true, 'ideas' => (string) $result['content']];
    }
}

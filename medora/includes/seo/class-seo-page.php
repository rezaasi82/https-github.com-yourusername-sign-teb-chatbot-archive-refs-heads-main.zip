<?php
/**
 * The SEO Intelligence Center.
 *
 * Surfaces the conversation-mined SEO signals and, on demand, asks the active
 * AI provider to turn them into blog titles, FAQ ideas and SEO recommendations.
 *
 * @package Medora
 */

namespace Medora\Seo;

if (! defined('ABSPATH')) {
    exit;
}

class SeoPage
{
    private const NONCE = 'mdr_seo';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('wp_ajax_mdr_seo_generate', [$this, 'ajax_generate']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'mdr-chat',
            __('مرکز هوش سئو', 'medora'),
            __('مرکز سئو', 'medora'),
            'manage_options',
            'mdr-seo',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $analyzer  = new \Medora\Seo\SeoAnalyzer();
        $questions = $analyzer->top_questions(30, 15);
        $keywords  = $analyzer->keywords(30, 30);
        $topics    = $analyzer->topics(30);
        $nonce     = wp_create_nonce(self::NONCE);
        $cached    = get_transient('mdr_seo_ideas');

        include MDR_DIR . 'includes/admin/views/seo.php';
    }

    public function ajax_generate(): void
    {
        \Medora\Core\JsonGuard::arm();

        if (\Medora\Security\Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            \Medora\Security\Security::note_failure('seo_generate');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }
        // Protect the API budget: a few generations per hour is plenty.
        if (! \Medora\Security\Security::rate_limit('seo_generate', 10, HOUR_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => __('محدودیت درخواست. کمی بعد دوباره تلاش کنید.', 'medora')], 429);
        }

        $result = $this->generate();
        if (empty($result['ok'])) {
            wp_send_json(['ok' => false, 'error' => $result['error'] ?? 'error'], 400);
        }

        set_transient('mdr_seo_ideas', $result['ideas'], 6 * HOUR_IN_SECONDS);
        \Medora\Security\AuditLog::record('seo_generate', ['object' => 'ideas']);
        wp_send_json(['ok' => true, 'ideas' => $result['ideas']]);
    }

    /**
     * @return array{ok:bool,ideas?:string,error?:string}
     */
    private function generate(): array
    {
        $settings = new \Medora\Core\Settings();
        $provider = (new \Medora\Ai\ProviderFactory($settings))->create_active();
        if ($provider === null) {
            return ['ok' => false, 'error' => __('کلید API تنظیم نشده است.', 'medora')];
        }

        $analyzer  = new \Medora\Seo\SeoAnalyzer();
        $questions = array_map(static fn($r) => trim((string) $r->q), $analyzer->top_questions(30, 12));
        $keywords  = array_keys($analyzer->keywords(30, 25));

        if ($questions === [] && $keywords === []) {
            return ['ok' => false, 'error' => __('هنوز داده‌ی گفتگوی کافی برای تحلیل وجود ندارد.', 'medora')];
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
            return ['ok' => false, 'error' => __('خطا در ارتباط با هوش مصنوعی.', 'medora')];
        }
        return ['ok' => true, 'ideas' => (string) $result['content']];
    }
}

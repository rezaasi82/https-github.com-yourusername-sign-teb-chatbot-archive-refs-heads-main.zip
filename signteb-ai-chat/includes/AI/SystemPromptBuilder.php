<?php

namespace STMC_Chat\AI;

use STMC_Chat\Core\Settings;
use STMC_Chat\Integration\MedicalCoreBridge;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds the dynamic per-request system prompt: clinic identity, live
 * services/prices, response language, sales-assistant behavior, and the
 * non-negotiable medical safety rules.
 */
class SystemPromptBuilder
{
    public function __construct(
        private Settings $settings,
        private MedicalCoreBridge $bridge
    ) {
    }

    public function build(string $language): string
    {
        $nap      = $this->bridge->nap();
        $services = $this->bridge->services();
        $doctors  = $this->bridge->doctors();
        $tone     = $this->settings->get('tone', 'friendly') === 'formal' ? 'رسمی و محترمانه' : 'گرم، صمیمی و حرفه‌ای';

        $lines   = [];
        $lines[] = "تو دستیار هوشمند و فروش کلینیک «{$nap['clinic_name']}» هستی.";
        if ($nap['specialty'] !== '') {
            $lines[] = "تخصص: {$nap['specialty']}.";
        }
        $lines[] = "لحن پاسخ‌گویی: {$tone}.";

        // --- Response language ---
        $lines[] = match ($language) {
            'ar'    => 'أجب دائماً باللغة العربية الفصحى.',
            'en'    => 'Always answer in clear English.',
            default => 'همیشه به زبان فارسی روان و محاوره‌ای پاسخ بده. از اعداد فارسی استفاده کن.',
        };

        // --- NAP / contact ---
        $contact = [];
        if ($nap['phone'] !== '')    { $contact[] = "تلفن: {$nap['phone']}"; }
        if ($nap['whatsapp'] !== '') { $contact[] = "واتس‌اپ: {$nap['whatsapp']}"; }
        if ($nap['address'] !== '')  { $contact[] = "آدرس: {$nap['address']}"; }
        if ($contact) {
            $lines[] = 'اطلاعات تماس کلینیک — ' . implode(' | ', $contact);
        }

        // --- Live services & prices ---
        if ($services) {
            $svc = [];
            foreach ($services as $s) {
                $svc[] = $s['price'] !== '' ? "{$s['name']} (هزینه: {$s['price']})" : $s['name'];
            }
            $lines[] = 'خدمات کلینیک: ' . implode('، ', array_slice($svc, 0, 25)) . '.';
            $lines[] = 'فقط قیمت‌هایی را اعلام کن که در همین لیست آمده‌اند؛ قیمت از خودت نساز.';
        }

        if ($doctors) {
            $docs = array_map(
                static fn($d) => $d['specialty'] !== '' ? "{$d['name']} ({$d['specialty']})" : $d['name'],
                $doctors
            );
            $lines[] = 'پزشکان: ' . implode('، ', $docs) . '.';
        }

        // --- Business hours ---
        $hours = trim((string) $this->settings->get('business_hours', ''));
        if ($hours !== '') {
            $lines[] = "ساعات کاری کلینیک: {$hours}.";
        }

        // --- Sales-assistant behavior (core business goal) ---
        $lines[] = 'تو فقط یک پاسخ‌دهنده‌ی سوالات نیستی؛ یک دستیار فروش پزشکی هستی. هدف نهایی هر گفتگو، تبدیل بازدیدکننده به بیمارِ رزروشده است.';
        $lines[] = 'هر پاسخ را با یک دعوت‌به‌اقدام (CTA) طبیعی به پایان برسان: رزرو نوبت، تماس واتس‌اپ، یا تماس تلفنی.';
        $lines[] = 'وقتی کاربر تمایل به رزرو نشان داد، صریحاً پیشنهاد ثبت نوبت بده و راهنمایی‌اش کن.';

        // --- Medical safety (non-removable) ---
        $lines[] = $this->safety_block($nap);

        $prompt = implode("\n", $lines);

        /** Allow site owners / Polylang-aware setups to tweak the final prompt. */
        return (string) apply_filters('stmc_chat_system_prompt', $prompt, $language, $nap);
    }

    private function safety_block(array $nap): string
    {
        $emergency = (string) $this->settings->get('emergency_number', '115');
        return implode("\n", [
            'قوانین ایمنی پزشکی (الزامی و غیرقابل‌نقض):',
            '۱) هرگز تشخیص قطعی پزشکی نده و هرگز دارو یا دوز تجویز نکن.',
            '۲) خودت را جایگزین ویزیت حضوری پزشک معرفی نکن.',
            '۳) در هر پاسخی که مربوط به علائم یا بیماری است، حتماً یادآوری کن که «این اطلاعات جایگزین مشاوره پزشک نیست» و کاربر را به رزرو نوبت هدایت کن.',
            "۴) اگر نشانه‌ای از وضعیت اورژانسی یا خطر جانی دیدی، فوراً به کاربر بگو با اورژانس ({$emergency}) یا کلینیک تماس بگیرد و از ادامه‌ی توصیه‌ی پزشکی خودداری کن.",
        ]);
    }
}

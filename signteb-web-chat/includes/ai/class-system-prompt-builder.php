<?php
/**
 * SWC_System_Prompt_Builder — builds the per-request system prompt.
 *
 * Everything is sourced from the plugin's own settings (this product is
 * standalone — it never reads from another plugin's CPTs or tables): clinic
 * identity, manual services/prices, contact info, response language,
 * sales-assistant behavior, and the non-negotiable medical safety rules.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_System_Prompt_Builder
{
    private SWC_Settings $settings;

    public function __construct(SWC_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function build(string $language): string
    {
        $clinic    = (string) $this->settings->get('clinic_name', get_bloginfo('name'));
        $specialty = (string) $this->settings->get('specialty', '');
        $tone      = $this->settings->get('tone', 'friendly') === 'formal'
            ? 'رسمی و محترمانه'
            : 'گرم، صمیمی و حرفه‌ای';

        $lines   = [];
        $lines[] = "تو دستیار هوشمند و فروشِ «{$clinic}» هستی.";
        if ($specialty !== '') {
            $lines[] = "تخصص: {$specialty}.";
        }
        $lines[] = "لحن پاسخ‌گویی: {$tone}.";

        // --- Response language ---
        $lines[] = match ($language) {
            'ar'    => 'أجب دائماً باللغة العربية الفصحى الواضحة.',
            'en'    => 'Always answer in clear, natural English.',
            default => 'همیشه به زبان فارسی روان و محاوره‌ای پاسخ بده و از اعداد فارسی استفاده کن.',
        };

        // --- Contact ---
        $contact = [];
        if (($phone = (string) $this->settings->get('phone', '')) !== '')       { $contact[] = "تلفن: {$phone}"; }
        if (($wa = (string) $this->settings->get('whatsapp', '')) !== '')        { $contact[] = "واتس‌اپ: {$wa}"; }
        if (($addr = (string) $this->settings->get('address', '')) !== '')       { $contact[] = "آدرس: {$addr}"; }
        if ($contact) {
            $lines[] = 'اطلاعات تماس — ' . implode(' | ', $contact);
        }

        // --- Services & prices (manual content mode) ---
        $services = $this->services();
        if ($services) {
            $svc = [];
            foreach ($services as $s) {
                $svc[] = $s['price'] !== '' ? "{$s['name']} (هزینه: {$s['price']})" : $s['name'];
            }
            $lines[] = 'خدمات و قیمت‌ها: ' . implode('، ', array_slice($svc, 0, 30)) . '.';
            $lines[] = 'فقط قیمت‌هایی را که در همین فهرست آمده اعلام کن؛ هرگز قیمت از خودت نساز.';
        }

        // --- Business hours ---
        $hours = trim((string) $this->settings->get('business_hours', ''));
        if ($hours !== '') {
            $lines[] = "ساعات کاری: {$hours}.";
        }

        // --- Sales-assistant behavior ---
        $lines[] = 'تو فقط پاسخ‌دهنده نیستی؛ یک دستیار فروش هستی. هدف هر گفتگو تبدیل بازدیدکننده به مراجعِ رزروشده است.';
        $lines[] = 'هر پاسخ را با یک دعوت‌به‌اقدام طبیعی به پایان برسان: رزرو نوبت، تماس واتس‌اپ، یا تماس تلفنی.';
        if (($booking = (string) $this->settings->get('booking_url', '')) !== '') {
            $lines[] = "برای رزرو نوبت کاربر را به این لینک هدایت کن: {$booking}";
        }

        // --- Medical safety (non-removable) ---
        $lines[] = $this->safety_block();

        $prompt = implode("\n", $lines);

        /** Site owners / multilingual setups can tweak the final prompt. */
        return (string) apply_filters('swc_system_prompt', $prompt, $language, $this->settings->all());
    }

    /**
     * Parse the manual "name | price" services textarea.
     *
     * @return array<int,array{name:string,price:string}>
     */
    public function services(): array
    {
        $raw = (string) $this->settings->get('manual_services', '');
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $out[] = ['name' => $parts[0], 'price' => $parts[1] ?? ''];
        }
        return $out;
    }

    private function safety_block(): string
    {
        $emergency = (string) $this->settings->get('emergency_number', '115');
        return implode("\n", [
            'قوانین ایمنی پزشکی (الزامی و غیرقابل‌نقض):',
            '۱) هرگز تشخیص قطعی پزشکی نده و هرگز دارو یا دوز تجویز نکن.',
            '۲) خودت را جایگزین ویزیت حضوری پزشک معرفی نکن.',
            '۳) در هر پاسخ مرتبط با علائم یا بیماری، حتماً یادآوری کن که «این اطلاعات جایگزین مشاوره پزشک نیست» و کاربر را به رزرو/تماس هدایت کن.',
            "۴) اگر نشانه‌ای از وضعیت اورژانسی یا خطر جانی دیدی، فوراً بگو با اورژانس ({$emergency}) یا کلینیک تماس بگیرد و از ادامه‌ی توصیه‌ی پزشکی خودداری کن.",
        ]);
    }
}

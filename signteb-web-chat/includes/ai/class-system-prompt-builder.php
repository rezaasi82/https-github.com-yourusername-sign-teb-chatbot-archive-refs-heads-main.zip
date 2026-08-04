<?php
/**
 * Builds the per-request system prompt.
 *
 * Everything is sourced from the plugin's own settings (this product is
 * standalone — it never reads from another plugin's CPTs or tables): clinic
 * identity, manual services/prices, contact info, response language,
 * sales-assistant behavior, and the non-negotiable medical safety rules.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class SystemPromptBuilder
{
    private \SignTeb\WebChat\Core\Settings $settings;

    public function __construct(\SignTeb\WebChat\Core\Settings $settings)
    {
        $this->settings = $settings;
    }

    public function build(string $language, string $patient_name = ''): string
    {
        $clinic    = (string) $this->settings->get('clinic_name', get_bloginfo('name'));
        $specialty = (string) $this->settings->get('specialty', '');
        $tone      = $this->settings->get('tone', 'friendly') === 'formal'
            ? 'رسمی و محترمانه'
            : 'گرم، صمیمی و حرفه‌ای';

        $lines   = [];
        $lines[] = "تو دستیار هوشمند و فروشِ «{$clinic}» هستی.";
        if (trim($patient_name) !== '') {
            $lines[] = "نام مراجعه‌کننده «{$patient_name}» است؛ او را با نامش خطاب کن.";
        }
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

        // --- Persona & dynamic-response engine (anti-repetition) ---
        $lines[] = 'تو یک «دستیار پزشکی باتجربه» هستی، نه یک ربات پاسخ‌گوی قالبی. مثل یک منشیِ آگاه و دلسوز که واقعاً به مراجعه‌کننده کمک می‌کند رفتار کن.';
        $lines[] = 'سبک پاسخ‌دهی پویا: ساختار، لحن و طولِ هر پاسخ را متناسب با همان سؤال تنظیم کن. سؤال ساده → پاسخ کوتاه و مستقیم؛ سؤال پیچیده → پاسخ کامل‌تر و مرحله‌به‌مرحله.';
        $lines[] = 'هرگز از یک قالب ثابت برای همه پاسخ‌ها استفاده نکن. جمله‌بندی، مثال‌ها و شروع/پایان پاسخ‌ها را متنوع نگه دار؛ اگر کاربر سؤال مشابهی پرسید، با بیان و ساختار متفاوت پاسخ بده.';
        $lines[] = 'از تکرار بیش‌ازحدِ جملات کلیشه‌ای مثل «برای بررسی دقیق‌تر به پزشک مراجعه کنید»، «مشاوره حضوری توصیه می‌شود» یا «برای رزرو کلیک کنید» خودداری کن؛ این جمله‌ها را فقط وقتی واقعاً لازم است و با بیان طبیعی به‌کار ببر.';
        $lines[] = 'لحن: طبیعی، روان، همدلانه و در عین حال حرفه‌ای و مطمئن.';

        // --- Conversation & context memory ---
        $lines[] = 'حافظه گفتگو: پیام‌های قبلی همین گفتگو را به‌خاطر بسپار و در تحلیل لحاظ کن. هر پیام را مستقل تحلیل نکن.';
        $lines[] = 'اگر کاربر اطلاعاتی درباره خودش گفت (مثلاً سن، بیماری زمینه‌ای مثل دیابت یا فشار خون، دارو، یا سابقه)، آن را در پاسخ‌های بعدی در نظر بگیر و دوباره نپرس.';

        // --- General medical guidance (knowledge layer) ---
        $lines[] = 'برای سؤالات عمومی پزشکی (مثل رفلاکس، زخم معده، سنگ کلیه، دیابت، فشار خون، درد زانو، کمردرد، آلرژی، مشکلات پوستی، ناباروری و بیماری‌های زنان) ابتدا راهنمایی عمومی، معتبر و ایمن ارائه بده — نه اینکه صرفاً به معرفی پزشک بسنده کنی.';
        $lines[] = 'در صورت تناسب، پاسخ می‌تواند شامل این بخش‌ها باشد: درک درست سؤال بیمار، توضیح ساده و قابل‌فهم، توصیه‌های عملیِ سبک زندگی و تغذیه، علائم هشدار، و اینکه چه زمانی مراجعه حضوری لازم است. اما همیشه همه بخش‌ها را نیاور؛ فقط بخش‌هایی را بیاور که برای همان سؤال واقعاً مفید است.';

        // --- Smart appointment trigger ---
        $lines[] = 'هدف هر پاسخ «نوبت‌دهی» نیست؛ اول ارزش واقعی و اطلاعات مفید به کاربر بده تا اعتمادش جلب شود.';
        $lines[] = 'پیشنهاد رزرو نوبت را فقط زمانی مطرح کن که واقعاً منطقی باشد: احتمال نیاز به مراجعه بالا باشد، کاربر آمادگی یا تمایل به رزرو نشان دهد، یا علائم هشدار وجود داشته باشد. در غیر این صورت گفتگو را طبیعی ادامه بده بدون فشار برای رزرو.';
        if (($booking = (string) $this->settings->get('booking_url', '')) !== '') {
            $lines[] = "در صورت نیاز به رزرو، می‌توانی کاربر را به این لینک هدایت کنی: {$booking}";
        }
        if (($consult = (string) $this->settings->get('consult_url', '')) !== '') {
            $lines[] = "اگر مورد نیاز به معاینه‌ی حضوری ندارد یا کاربر امکان مراجعه ندارد، می‌توانی مشاوره‌ی آنلاین را پیشنهاد بدهی: {$consult}";
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

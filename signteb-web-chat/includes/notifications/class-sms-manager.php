<?php
/**
 * SWC_Sms_Manager — the SMS/messaging hub.
 *
 * - Factory for the configured SMS gateway (Iranian panels + a custom HTTP
 *   provider for foreign services).
 * - Owns the four editable message templates and placeholder substitution.
 * - Builds messenger deep-links (Bale, WhatsApp, Telegram, Eitaa, device SMS)
 *   so a lead can be reached without any gateway credentials.
 *
 * Only the panel's activation code (API key) and sender line are needed to go
 * live; everything else has sensible defaults.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Manager
{
    public const OPTION_KEY    = 'swc_sms_key_enc';
    public const OPTION_SECRET = 'swc_sms_secret_enc';

    /** id => provider class. Add a panel by adding one line + one class. */
    private const PROVIDERS = [
        'kavenegar'   => 'SWC_Sms_Kavenegar',
        'melipayamak' => 'SWC_Sms_Melipayamak',
        'smsir'       => 'SWC_Sms_Smsir',
        'ghasedak'    => 'SWC_Sms_Ghasedak',
        'custom'      => 'SWC_Sms_Custom',
    ];

    private SWC_Settings $settings;

    public function __construct(?SWC_Settings $settings = null)
    {
        $this->settings = $settings ?? new SWC_Settings();
    }

    /* -------------------- providers -------------------- */

    /** @return array<string,string> id => label for the settings dropdown. */
    public function providers(): array
    {
        $out = [];
        foreach (self::PROVIDERS as $id => $class) {
            $out[$id] = (new $class($this->settings))->label();
        }
        return $out;
    }

    public function active_id(): string
    {
        $id = (string) $this->settings->get('sms_provider', 'kavenegar');
        return isset(self::PROVIDERS[$id]) ? $id : 'kavenegar';
    }

    public function active(): ?SWC_Sms_Provider_Interface
    {
        $class = self::PROVIDERS[$this->active_id()] ?? '';
        return $class !== '' ? new $class($this->settings) : null;
    }

    public function is_enabled(): bool
    {
        return (int) $this->settings->get('sms_enabled', 0) === 1;
    }

    /**
     * Whether a real gateway send is possible (enabled + credentials present).
     * The soft license lock also disables sending (a premium capability).
     */
    public function is_configured(): bool
    {
        if (! $this->is_enabled()) {
            return false;
        }
        if ($this->active_id() === 'custom') {
            return trim((string) $this->settings->get('sms_custom_url', '')) !== '';
        }
        return self::key() !== '';
    }

    /**
     * Send a free-text message through the active gateway.
     *
     * @return array{ok:bool,error?:string,code?:int}
     */
    public function send(string $to, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => __('متن پیام خالی است.', 'signteb-web-chat')];
        }
        return $this->dispatch(static fn(SWC_Sms_Provider_Interface $p) => $p->send($to, $text));
    }

    /**
     * Send one of the stored templates to a lead. When the template has a
     * pattern/service-line code and the panel supports it, the message is sent
     * via the verified-line API (ordered variables); otherwise the rendered
     * text is sent normally.
     *
     * @param array<string,string> $vars placeholder => value
     * @return array{ok:bool,error?:string,code?:int}
     */
    public function send_lead(string $to, string $template_key, array $vars): array
    {
        $tpl_text = $this->template_text($template_key);
        $code     = $this->template_code($template_key);

        return $this->dispatch(function (SWC_Sms_Provider_Interface $p) use ($to, $tpl_text, $code, $vars) {
            if ($code !== '' && $p->supports_pattern()) {
                return $p->send_pattern($to, $code, $this->ordered_params($tpl_text, $vars));
            }
            $text = trim($this->render($tpl_text, $vars));
            if ($text === '') {
                return ['ok' => false, 'error' => __('متن پیام خالی است.', 'signteb-web-chat')];
            }
            return $p->send($to, $text);
        });
    }

    /**
     * Shared guard + provider resolution + audit around any send call.
     *
     * @param callable(SWC_Sms_Provider_Interface):array $call
     * @return array{ok:bool,error?:string,code?:int}
     */
    private function dispatch(callable $call): array
    {
        if (! (new SWC_License_Manager())->allows('sms')) {
            return ['ok' => false, 'error' => __('ارسال پیامک نیازمند لایسنس فعال است.', 'signteb-web-chat')];
        }
        if (! $this->is_configured()) {
            return ['ok' => false, 'error' => __('پنل پیامک پیکربندی نشده است.', 'signteb-web-chat')];
        }
        $provider = $this->active();
        if ($provider === null) {
            return ['ok' => false, 'error' => __('سرویس پیامک نامعتبر است.', 'signteb-web-chat')];
        }
        $result = $call($provider);
        SWC_Audit_Log::record('sms_send', ['object' => $provider->id(), 'severity' => ! empty($result['ok']) ? 'info' : 'warning']);
        return $result;
    }

    /**
     * Values of the placeholders that appear in a template, in first-appearance
     * order — the order pattern/service-line APIs expect the variables.
     *
     * @param array<string,string> $vars
     * @return array<string,string>
     */
    private function ordered_params(string $text, array $vars): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $m);
        $out = [];
        foreach ($m[1] as $key) {
            // The opt-out sentence is baked into the approved panel pattern, so
            // it is never a dynamic parameter.
            if ($key === 'optout' || ! isset($vars[$key]) || isset($out[$key])) {
                continue;
            }
            $out[$key] = (string) $vars[$key];
        }
        return $out;
    }

    /* -------------------- credentials (encrypted) -------------------- */

    public static function key(): string
    {
        $enc = get_option(self::OPTION_KEY, '');
        return is_string($enc) && $enc !== '' ? SWC_Encryption::decrypt($enc) : '';
    }

    public static function secret(): string
    {
        $enc = get_option(self::OPTION_SECRET, '');
        return is_string($enc) && $enc !== '' ? SWC_Encryption::decrypt($enc) : '';
    }

    public static function save_key(string $plain): void
    {
        self::store(self::OPTION_KEY, $plain);
    }

    public static function save_secret(string $plain): void
    {
        self::store(self::OPTION_SECRET, $plain);
    }

    private static function store(string $option, string $plain): void
    {
        $plain = trim($plain);
        if ($plain === '') {
            delete_option($option);
            return;
        }
        update_option($option, SWC_Encryption::encrypt($plain), false);
    }

    /* -------------------- templates -------------------- */

    /**
     * Default templates — clinic owners edit these in Settings. Keys are stable
     * so a saved override always maps back to the right slot.
     *
     * @return array<string,array{label:string,text:string}>
     */
    public static function default_templates(): array
    {
        return [
            'welcome'  => [
                'label' => __('خوش‌آمد به لید', 'signteb-web-chat'),
                'text'  => __('{name} عزیز، از تماس شما با {clinic} سپاسگزاریم. کارشناسان ما به‌زودی برای هماهنگی با شما تماس می‌گیرند. 🌿 {optout}', 'signteb-web-chat'),
            ],
            'referral' => [
                'label' => __('ارجاع لید به همکار', 'signteb-web-chat'),
                'text'  => __('لید جدید در {clinic}: {name} - {phone} - امتیاز: {score}. لطفاً پیگیری کنید. {optout}', 'signteb-web-chat'),
            ],
            'reminder' => [
                'label' => __('یادآوری پیگیری', 'signteb-web-chat'),
                'text'  => __('{name} عزیز، جهت تکمیل مشاوره و رزرو نوبت با {clinic} در ارتباط باشید. منتظر شما هستیم. {optout}', 'signteb-web-chat'),
            ],
            'custom'   => [
                'label' => __('پیام سفارشی', 'signteb-web-chat'),
                'text'  => __('{name} عزیز، {clinic} در خدمت شماست. {optout}', 'signteb-web-chat'),
            ],
        ];
    }

    /**
     * Merged templates: stored overrides on top of the defaults.
     *
     * @return array<string,array{label:string,text:string}>
     */
    public function templates(): array
    {
        $stored = $this->settings->get('sms_templates', []);
        $stored = is_array($stored) ? $stored : [];
        $out    = self::default_templates();
        foreach ($out as $key => $def) {
            if (isset($stored[$key]) && is_string($stored[$key]) && trim($stored[$key]) !== '') {
                $out[$key]['text'] = $stored[$key];
            }
        }
        return $out;
    }

    public function template_text(string $key): string
    {
        $all = $this->templates();
        return $all[$key]['text'] ?? '';
    }

    /** Pattern / service-line code the clinic registered for this template. */
    public function template_code(string $key): string
    {
        $codes = $this->settings->get('sms_template_codes', []);
        $codes = is_array($codes) ? $codes : [];
        return trim((string) ($codes[$key] ?? ''));
    }

    /**
     * Substitute {name} {phone} {score} {status} {clinic} {summary} in a body.
     *
     * @param array<string,string> $vars
     */
    public function render(string $text, array $vars): string
    {
        $repl = [];
        foreach ($vars as $k => $v) {
            $repl['{' . $k . '}'] = (string) $v;
        }
        return strtr($text, $repl);
    }

    /**
     * Build the placeholder map for a lead conversation row.
     *
     * @return array<string,string>
     */
    public function vars_for_lead(object $c): array
    {
        $scores = ['hot' => __('داغ', 'signteb-web-chat'), 'warm' => __('متوسط', 'signteb-web-chat'), 'cold' => __('سرد', 'signteb-web-chat')];
        return [
            'name'    => trim((string) ($c->patient_name ?? '')) ?: __('کاربر', 'signteb-web-chat'),
            'phone'   => trim((string) ($c->patient_phone ?? '')),
            'score'   => $scores[$c->lead_score ?? ''] ?? '—',
            'status'  => SWC_Lead_CRM::label((string) ($c->lead_status ?? 'new')),
            'clinic'  => (string) $this->settings->get('clinic_name', get_bloginfo('name')),
            'summary' => trim((string) ($c->summary ?? '')),
            'optout'  => $this->optout_text(),
        ];
    }

    /** The opt-out sentence appended to service-line SMS (regulatory). */
    public function optout_text(): string
    {
        return trim((string) $this->settings->get('sms_optout', ''));
    }

    /**
     * Saved staff / colleague numbers for quick referral. One per line,
     * "Name,09xxxxxxxxx" or just the number.
     *
     * @return array<int,array{name:string,phone:string}>
     */
    public function staff_numbers(): array
    {
        $raw = (string) $this->settings->get('sms_staff_numbers', '');
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode(',', $line, 2));
            if (count($parts) === 2 && $parts[1] !== '') {
                $out[] = ['name' => $parts[0], 'phone' => $parts[1]];
            } else {
                $out[] = ['name' => '', 'phone' => $parts[0]];
            }
        }
        return $out;
    }

    /* -------------------- messenger deep-links -------------------- */

    /**
     * Client-openable messenger links for a phone + prefilled text. No gateway
     * needed — opens the messenger app on the operator's device.
     *
     * @return array<string,array{label:string,url:string}>
     */
    public static function messenger_links(string $phone, string $text): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        // WhatsApp wants an international number; assume Iran (98) when local.
        $intl = $digits;
        if (str_starts_with($digits, '0')) {
            $intl = '98' . substr($digits, 1);
        }
        $enc = rawurlencode($text);
        return [
            'sms'      => ['label' => __('پیامک دستگاه', 'signteb-web-chat'), 'url' => 'sms:' . $digits . '?&body=' . $enc],
            'whatsapp' => ['label' => 'WhatsApp', 'url' => 'https://wa.me/' . $intl . '?text=' . $enc],
            'telegram' => ['label' => 'Telegram', 'url' => 'https://t.me/share/url?url=&text=' . $enc],
            'bale'     => ['label' => 'بله', 'url' => 'https://ble.ir/share?text=' . $enc],
            'eitaa'    => ['label' => 'ایتا', 'url' => 'https://eitaa.com/share/url?text=' . $enc],
        ];
    }
}

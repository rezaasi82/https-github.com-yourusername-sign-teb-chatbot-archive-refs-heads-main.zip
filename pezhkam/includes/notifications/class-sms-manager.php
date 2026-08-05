<?php
/**
 * The SMS/messaging hub.
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
 * @package Pezhkam
 */

namespace Pezhkam\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

class SmsManager
{
    public const OPTION_KEY    = 'pzk_sms_key_enc';
    public const OPTION_SECRET = 'pzk_sms_secret_enc';

    /** id => provider class. Add a panel by adding one line + one class. */
    private const PROVIDERS = [
        'kavenegar'   => '\Pezhkam\Notifications\Providers\SmsKavenegar',
        'melipayamak' => '\Pezhkam\Notifications\Providers\SmsMelipayamak',
        'smsir'       => '\Pezhkam\Notifications\Providers\SmsSmsir',
        'ghasedak'    => '\Pezhkam\Notifications\Providers\SmsGhasedak',
        'custom'      => '\Pezhkam\Notifications\Providers\SmsCustom',
    ];

    private \Pezhkam\Core\Settings $settings;

    public function __construct(?\Pezhkam\Core\Settings $settings = null)
    {
        $this->settings = $settings ?? new \Pezhkam\Core\Settings();
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

    public function active(): ?\Pezhkam\Notifications\SmsProviderInterface
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
            return ['ok' => false, 'error' => __('متن پیام خالی است.', 'pezhkam')];
        }
        return $this->dispatch(static fn(\Pezhkam\Notifications\SmsProviderInterface $p) => $p->send($to, $text));
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
        $order    = $this->template_vars($template_key);

        return $this->dispatch(function (\Pezhkam\Notifications\SmsProviderInterface $p) use ($to, $tpl_text, $code, $vars, $order) {
            if ($code !== '' && $p->supports_pattern()) {
                return $p->send_pattern($to, $code, $this->ordered_params($tpl_text, $vars, $order));
            }
            $text = trim($this->render($tpl_text, $vars, $order));
            if ($text === '') {
                return ['ok' => false, 'error' => __('متن پیام خالی است.', 'pezhkam')];
            }
            return $p->send($to, $text);
        });
    }

    /**
     * Canonical variable order for a template's numbered placeholders —
     * {0} means the first entry, {1} the second, …
     *
     * @return array<int,string>
     */
    public function template_vars(string $key): array
    {
        $defaults = self::default_templates();
        return $defaults[$key]['vars'] ?? ['name', 'clinic'];
    }

    /**
     * Shared guard + provider resolution + audit around any send call.
     *
     * @param callable(SmsProviderInterface):array $call
     * @return array{ok:bool,error?:string,code?:int}
     */
    private function dispatch(callable $call): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'error' => __('پنل پیامک پیکربندی نشده است.', 'pezhkam')];
        }
        $provider = $this->active();
        if ($provider === null) {
            return ['ok' => false, 'error' => __('سرویس پیامک نامعتبر است.', 'pezhkam')];
        }
        $result = $call($provider);
        \Pezhkam\Security\AuditLog::record('sms_send', ['object' => $provider->id(), 'severity' => ! empty($result['ok']) ? 'info' : 'warning']);
        return $result;
    }

    /**
     * Values of the placeholders that appear in a template, in first-appearance
     * order — the order pattern/service-line APIs expect the variables.
     *
     * @param array<string,string> $vars
     * @return array<string,string>
     */
    private function ordered_params(string $text, array $vars, array $order = []): array
    {
        preg_match_all('/\{([a-z_]+|\d+)\}/', $text, $m);
        $out = [];
        foreach ($m[1] as $key) {
            // Numbered placeholders (the MeliPayamak-approved style) map to the
            // template's canonical variable order: {0} = first, {1} = second…
            if (ctype_digit($key)) {
                $key = $order[(int) $key] ?? '';
            }
            // The opt-out sentence is baked into the approved panel pattern, so
            // it is never a dynamic parameter.
            if ($key === '' || $key === 'optout' || ! isset($vars[$key]) || isset($out[$key])) {
                continue;
            }
            $out[$key] = (string) $vars[$key];
        }
        return $out;
    }

    /**
     * Full connection diagnosis for the settings screen. Reports the stored
     * configuration plus (when the provider supports it) a live credential
     * check against the panel — without sending any SMS.
     *
     * @return array{ok:bool,lines:array<int,string>}
     */
    public function diagnose(): array
    {
        $lines   = [];
        $lines[] = sprintf(__('سرویس انتخاب‌شده: %s', 'pezhkam'), $this->providers()[$this->active_id()] ?? $this->active_id());
        $lines[] = sprintf(__('فعال‌سازی: %s', 'pezhkam'), $this->is_enabled() ? '✓' : __('✗ (تیک «فعال‌سازی» را بزنید)', 'pezhkam'));
        $lines[] = sprintf(__('APIKey ذخیره شده: %s', 'pezhkam'), self::key() !== '' ? '✓' : '✗');
        $lines[] = sprintf(__('رمز عبور ذخیره شده: %s', 'pezhkam'), self::secret() !== '' ? __('✓ (حالت نام‌کاربری/رمز)', 'pezhkam') : __('— (حالت توکن)', 'pezhkam'));
        $lines[] = sprintf(__('شماره فرستنده: %s', 'pezhkam'), trim((string) $this->settings->get('sms_sender', '')) !== '' ? $this->settings->get('sms_sender') : __('خالی (برای خط اشتراکی درست است)', 'pezhkam'));

        $codes = array_filter(array_map([$this, 'template_code'], array_keys(self::default_templates())));
        $lines[] = sprintf(__('کد الگو تنظیم‌شده: %d قالب', 'pezhkam'), count($codes));

        $provider = $this->active();
        if ($provider !== null && method_exists($provider, 'check')) {
            $check   = $provider->check();
            $lines[] = ($check['ok'] ? '✅ ' : '❌ ') . $check['detail'];
            return ['ok' => (bool) $check['ok'], 'lines' => $lines];
        }

        $lines[] = __('این سرویس بررسی زنده ندارد؛ با «ارسال پیامک تست» امتحان کنید.', 'pezhkam');
        return ['ok' => $this->is_configured(), 'lines' => $lines];
    }

    /* -------------------- credentials (encrypted) -------------------- */

    public static function key(): string
    {
        $enc = get_option(self::OPTION_KEY, '');
        return is_string($enc) && $enc !== '' ? \Pezhkam\Core\Encryption::decrypt($enc) : '';
    }

    public static function secret(): string
    {
        $enc = get_option(self::OPTION_SECRET, '');
        return is_string($enc) && $enc !== '' ? \Pezhkam\Core\Encryption::decrypt($enc) : '';
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
        update_option($option, \Pezhkam\Core\Encryption::encrypt($plain), false);
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
        // Texts follow the MeliPayamak-approved pattern style: numbered
        // placeholders {0} {1} {2}. `vars` defines what each number means for
        // that template (the order the panel pattern expects its variables).
        return [
            'welcome'  => [
                'label' => __('خوش‌آمد به لید', 'pezhkam'),
                'text'  => __('{0} عزیز، از تماس شما با {1} سپاسگزاریم. کارشناسان ما به‌زودی برای هماهنگی با شما تماس می‌گیرند. 🌿 {optout}', 'pezhkam'),
                'vars'  => ['name', 'clinic'],
            ],
            'referral' => [
                'label' => __('ارجاع لید به همکار', 'pezhkam'),
                'text'  => __('لید جدید در سایت : نام {0} -شماره {1} - امتیاز: {2}. لطفاً پیگیری کنید. {optout}', 'pezhkam'),
                'vars'  => ['name', 'phone', 'score'],
            ],
            'reminder' => [
                'label' => __('یادآوری پیگیری', 'pezhkam'),
                'text'  => __('{0} عزیز، جهت تکمیل مشاوره و رزرو نوبت با {1} در ارتباط باشید. منتظر شما هستیم. {optout}', 'pezhkam'),
                'vars'  => ['name', 'clinic'],
            ],
            'custom'   => [
                'label' => __('پیام سفارشی', 'pezhkam'),
                'text'  => __('{0} عزیز، {1} در خدمت شماست. {optout}', 'pezhkam'),
                'vars'  => ['name', 'clinic'],
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
     * Substitute placeholders in a body — both named ({name} {clinic} …) and
     * the panel-approved numbered style ({0} {1} …, resolved via $order).
     *
     * @param array<string,string> $vars
     * @param array<int,string>    $order canonical variable order for {0}{1}…
     */
    public function render(string $text, array $vars, array $order = []): string
    {
        $repl = [];
        foreach ($vars as $k => $v) {
            $repl['{' . $k . '}'] = (string) $v;
        }
        foreach ($order as $i => $name) {
            $repl['{' . $i . '}'] = (string) ($vars[$name] ?? '');
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
        $scores = ['hot' => __('داغ', 'pezhkam'), 'warm' => __('متوسط', 'pezhkam'), 'cold' => __('سرد', 'pezhkam')];
        return [
            'name'    => trim((string) ($c->patient_name ?? '')) ?: __('کاربر', 'pezhkam'),
            'phone'   => trim((string) ($c->patient_phone ?? '')),
            'score'   => $scores[$c->lead_score ?? ''] ?? '—',
            'status'  => \Pezhkam\Crm\LeadCrm::label((string) ($c->lead_status ?? 'new')),
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
     * Saved staff / colleagues for quick referral. One per line:
     * "Name,09xxxxxxxxx,email@example.com" — email optional, or just a number.
     *
     * @return array<int,array{name:string,phone:string,email:string}>
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
            $parts = array_map('trim', explode(',', $line, 3));
            if (count($parts) === 1) {
                $out[] = ['name' => '', 'phone' => $parts[0], 'email' => ''];
                continue;
            }
            $out[] = [
                'name'  => $parts[0],
                'phone' => $parts[1] ?? '',
                'email' => is_email($parts[2] ?? '') ? $parts[2] : '',
            ];
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
            'sms'      => ['label' => __('پیامک دستگاه', 'pezhkam'), 'url' => 'sms:' . $digits . '?&body=' . $enc],
            'whatsapp' => ['label' => 'WhatsApp', 'url' => 'https://wa.me/' . $intl . '?text=' . $enc],
            'telegram' => ['label' => 'Telegram', 'url' => 'https://t.me/share/url?url=&text=' . $enc],
            'bale'     => ['label' => 'بله', 'url' => 'https://ble.ir/share?text=' . $enc],
            'eitaa'    => ['label' => 'ایتا', 'url' => 'https://eitaa.com/share/url?text=' . $enc],
        ];
    }
}

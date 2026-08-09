<?php
/**
 * MeliPayamak (ملی‌پیامک) — supports BOTH auth styles, auto-detected:
 *
 *  - Legacy REST (username + password): username in the APIKey field and the
 *    password in the secondary field →
 *      free text : rest.payamak-panel.com/api/SendSMS/SendSMS
 *      pattern   : rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
 *
 *  - Console token (github.com/Melipayamak): a single token from
 *    console.melipayamak.com in the APIKey field, secondary field empty →
 *      free text : console.melipayamak.com/api/send/simple/{token}
 *      pattern   : console.melipayamak.com/api/send/shared/{token}
 *
 * The password field being filled selects legacy mode; otherwise token mode.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Notifications\Providers;

if (! defined('ABSPATH')) {
    exit;
}

class SmsMelipayamak extends \Pezhkam\Notifications\SmsProviderBase
{
    private const CONSOLE = 'https://console.melipayamak.com/api/send';
    private const LEGACY  = 'https://rest.payamak-panel.com/api/SendSMS';

    public function id(): string
    {
        return 'melipayamak';
    }

    public function label(): string
    {
        return 'ملی‌پیامک (MeliPayamak)';
    }

    public function supports_pattern(): bool
    {
        return true;
    }

    /** Password present = the classic username/password web service. */
    private function is_legacy(): bool
    {
        return $this->api_secret() !== '';
    }

    /**
     * The panel's webservice APIKey is a UUID; usernames (mobile numbers)
     * never look like this. Used to detect swapped fields.
     */
    private function looks_like_webservice_key(string $v): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
    }

    /**
     * Raw GetCredit probe.
     *
     * @return array{ok:bool,credit:string,msg:string,neterr:string}
     */
    private function get_credit(string $user, string $pass): array
    {
        $r = $this->http(self::LEGACY . '/GetCredit', [
            'method' => 'POST',
            'body'   => ['username' => $user, 'password' => $pass],
        ]);
        if (! empty($r['error'])) {
            return ['ok' => false, 'credit' => '', 'msg' => '', 'neterr' => $r['error']];
        }
        $data = json_decode($r['body'], true);
        $ret  = is_array($data) ? (int) ($data['RetStatus'] ?? 0) : 0;
        return [
            'ok'     => $ret === 1,
            'credit' => is_array($data) ? (string) ($data['Value'] ?? '?') : '?',
            'msg'    => is_array($data) ? (string) ($data['StrRetStatus'] ?? ('HTTP ' . $r['code'])) : ('HTTP ' . $r['code']),
            'neterr' => '',
        ];
    }

    /**
     * Credential check without sending an SMS. Detects swapped fields (the
     * panel's webservice APIKey pasted as username) and repairs them
     * automatically when the swapped pair actually authenticates.
     *
     * @return array{ok:bool,detail:string}
     */
    public function check(): array
    {
        $key    = $this->api_key();
        $secret = $this->api_secret();
        if ($key === '') {
            return ['ok' => false, 'detail' => __('هیچ APIKey/نام کاربری ذخیره نشده است.', 'pezhkam')];
        }

        // Webservice APIKey (UUID) stored alone: it belongs in the password
        // parameter, next to the panel username — not in console-token mode.
        if (! $this->is_legacy() && $this->looks_like_webservice_key($key)) {
            return ['ok' => false, 'detail' => __('این کد «APIKey وب‌سرویس» ملی‌پیامک است، نه توکن کنسول. نام کاربری پنل (معمولاً شماره موبایل) را در فیلد APIKey و همین کد را در فیلد «رمز عبور» وارد کنید و ذخیره کنید.', 'pezhkam')];
        }

        if ($this->is_legacy()) {
            $probe = $this->get_credit($key, $secret);
            if ($probe['neterr'] !== '') {
                return ['ok' => false, 'detail' => sprintf(__('سرور سایت به پنل دسترسی ندارد: %s', 'pezhkam'), $probe['neterr'])];
            }
            if ($probe['ok']) {
                return ['ok' => true, 'detail' => sprintf(__('حالت نام‌کاربری/رمز — اعتبار پنل: %s', 'pezhkam'), $probe['credit'])];
            }

            // Rejected — if the UUID sits in the username slot, try swapped and
            // self-repair when the swap authenticates.
            if ($this->looks_like_webservice_key($key) && ! $this->looks_like_webservice_key($secret)) {
                $swapped = $this->get_credit($secret, $key);
                if ($swapped['ok']) {
                    \Pezhkam\Notifications\SmsManager::save_key($secret);
                    \Pezhkam\Notifications\SmsManager::save_secret($key);
                    return ['ok' => true, 'detail' => sprintf(__('جای دو فیلد برعکس بود؛ به‌طور خودکار اصلاح و ذخیره شد — اعتبار پنل: %s', 'pezhkam'), $swapped['credit'])];
                }
            }

            return ['ok' => false, 'detail' => sprintf(__('پنل اتصال را رد کرد (GetCredit): %s — چیدمان درست: نام کاربری پنل در فیلد APIKey، و «APIKey وب‌سرویس» (کد UUID از تنظیمات وبسرویس پنل) در فیلد رمز عبور. اگر باز رد شد، وب‌سرویس را در پنل فعال و در صورت نیاز IP سرور سایت را مجاز کنید.', 'pezhkam'), $probe['msg'])];
        }

        return ['ok' => true, 'detail' => __('حالت توکن کنسول شناسایی شد (رمز خالی است). برای آزمون واقعی، «ارسال پیامک تست» را بزنید.', 'pezhkam')];
    }

    public function send(string $to, string $text): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '') {
            return ['ok' => false, 'error' => __('APIKey (یا نام کاربری) و شماره مقصد لازم است.', 'pezhkam')];
        }

        if ($this->is_legacy()) {
            if ($this->sender() === '') {
                return ['ok' => false, 'error' => __('برای ارسال متن آزاد، شماره خط لازم است. با خط خدماتی اشتراکی، «کد الگو» را برای قالب تنظیم کنید.', 'pezhkam')];
            }
            $r = $this->http(self::LEGACY . '/SendSMS', [
                'method' => 'POST',
                'body'   => [
                    'username' => $key,
                    'password' => $this->api_secret(),
                    'to'       => $to,
                    'from'     => $this->sender(),
                    'text'     => $text,
                    'isflash'  => 'false',
                ],
            ]);
            return $this->parse_legacy($r);
        }

        if ($this->sender() === '') {
            return ['ok' => false, 'error' => __('برای ارسال متن آزاد، شماره خط اختصاصی لازم است. با خط خدماتی اشتراکی، «کد الگو» را برای قالب تنظیم کنید تا از مسیر الگویی ارسال شود.', 'pezhkam')];
        }
        $r = $this->http(self::CONSOLE . '/simple/' . rawurlencode($key), [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode(['from' => $this->sender(), 'to' => $to, 'text' => $text]),
        ]);
        return $this->parse_console($r);
    }

    /**
     * Shared service line pattern: bodyId + ordered variables. Works in both
     * auth modes; the panel picks the sending line itself.
     */
    public function send_pattern(string $to, string $code, array $params): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '' || $code === '') {
            return ['ok' => false, 'error' => __('APIKey (یا نام کاربری)، شماره یا کد الگو تنظیم نشده است.', 'pezhkam')];
        }

        if ($this->is_legacy()) {
            $text = implode(';', array_map(static fn($v) => str_replace(';', '،', (string) $v), array_values($params)));
            $r = $this->http(self::LEGACY . '/BaseServiceNumber', [
                'method' => 'POST',
                'body'   => ['username' => $key, 'password' => $this->api_secret(), 'text' => $text, 'to' => $to, 'bodyId' => $code],
            ]);
            return $this->parse_legacy($r);
        }

        $r = $this->http(self::CONSOLE . '/shared/' . rawurlencode($key), [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode(['bodyId' => (int) $code, 'to' => $to, 'args' => array_values(array_map('strval', $params))]),
        ]);
        return $this->parse_console($r);
    }

    /**
     * Legacy API: RetStatus 1 (or a long numeric recId in Value) = success.
     *
     * @param array{ok:bool,code:int,body:string,error?:string} $r
     * @return array{ok:bool,error?:string,code?:int}
     */
    private function parse_legacy(array $r): array
    {
        $data = json_decode($r['body'], true);
        $ret  = is_array($data) ? (int) ($data['RetStatus'] ?? 0) : 0;
        $val  = is_array($data) ? (string) ($data['Value'] ?? '') : '';
        if ($ret === 1 || (is_numeric($val) && strlen($val) > 6)) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['StrRetStatus'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' && $msg !== 'Ok' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'pezhkam'))];
    }

    /**
     * Console API: a positive recId = queued/sent.
     *
     * @param array{ok:bool,code:int,body:string,error?:string} $r
     * @return array{ok:bool,error?:string,code?:int}
     */
    private function parse_console(array $r): array
    {
        $data = json_decode($r['body'], true);
        $rec  = is_array($data) ? ($data['recId'] ?? 0) : 0;
        if (is_numeric($rec) && (int) $rec > 0) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['status'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'pezhkam'))];
    }
}

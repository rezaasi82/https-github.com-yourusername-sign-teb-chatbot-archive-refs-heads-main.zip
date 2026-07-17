<?php
/**
 * MeliPayamak (ملی‌پیامک) — official console token API (github.com/Melipayamak).
 *
 * Auth: a single API key (the token from console.melipayamak.com), entered in
 * the "activation code" field — no username/password needed.
 *   - free text : POST /api/send/simple/{key}   { from, to, text }
 *   - pattern   : POST /api/send/shared/{key}    { bodyId, to, args[] }
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Melipayamak extends SWC_Sms_Provider_Base
{
    private const BASE = 'https://console.melipayamak.com/api/send';

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

    public function send(string $to, string $text): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '') {
            return ['ok' => false, 'error' => __('توکن API یا شماره مقصد تنظیم نشده است.', 'signteb-web-chat')];
        }
        // Free-text needs a dedicated line; shared service lines send only via
        // the pattern (bodyId) path where the panel picks the line itself.
        if ($this->sender() === '') {
            return ['ok' => false, 'error' => __('برای ارسال متن آزاد، شماره خط اختصاصی لازم است. با خط خدماتی اشتراکی، «کد الگو» را برای قالب تنظیم کنید تا از مسیر الگویی ارسال شود.', 'signteb-web-chat')];
        }

        $r = $this->http(self::BASE . '/simple/' . rawurlencode($key), [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode(['from' => $this->sender(), 'to' => $to, 'text' => $text]),
        ]);
        return $this->parse($r);
    }

    /**
     * Shared service line: the approved pattern is chosen by bodyId; variable
     * parts go into args[] in the order the template expects.
     */
    public function send_pattern(string $to, string $code, array $params): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '' || $code === '') {
            return ['ok' => false, 'error' => __('توکن API، شماره یا کد الگو تنظیم نشده است.', 'signteb-web-chat')];
        }

        $r = $this->http(self::BASE . '/shared/' . rawurlencode($key), [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode(['bodyId' => (int) $code, 'to' => $to, 'args' => array_values(array_map('strval', $params))]),
        ]);
        return $this->parse($r);
    }

    /**
     * Console API returns { recId, status }. A positive recId means queued/sent.
     *
     * @param array{ok:bool,code:int,body:string,error?:string} $r
     * @return array{ok:bool,error?:string,code?:int}
     */
    private function parse(array $r): array
    {
        $data = json_decode($r['body'], true);
        $rec  = is_array($data) ? ($data['recId'] ?? 0) : 0;
        if (is_numeric($rec) && (int) $rec > 0) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['status'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
    }
}

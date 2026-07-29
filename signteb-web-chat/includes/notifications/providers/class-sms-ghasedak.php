<?php
/**
 * Ghasedak (قاصدک) — simple send API.
 * Auth: a single API key in the "apikey" header.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Notifications\Providers;

if (! defined('ABSPATH')) {
    exit;
}

class SmsGhasedak extends \SignTeb\WebChat\Notifications\SmsProviderBase
{
    public function id(): string
    {
        return 'ghasedak';
    }

    public function label(): string
    {
        return 'قاصدک (Ghasedak)';
    }

    public function supports_pattern(): bool
    {
        return true;
    }

    /**
     * Verification send — approved template chosen by name/code; variables go
     * in param1, param2, … in order.
     */
    public function send_pattern(string $to, string $code, array $params): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '' || $code === '') {
            return ['ok' => false, 'error' => __('کلید API، شماره یا کد الگو تنظیم نشده است.', 'signteb-web-chat')];
        }

        $body = ['receptor' => $to, 'type' => '1', 'template' => $code];
        $i = 1;
        foreach (array_values($params) as $val) {
            $body['param' . $i] = (string) $val;
            $i++;
        }

        $r = $this->http('https://api.ghasedak.me/v2/verification/send/simple', [
            'method'  => 'POST',
            'headers' => ['apikey' => $key, 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => $body,
        ]);
        $data = json_decode($r['body'], true);
        $c    = is_array($data) ? (int) ($data['result']['code'] ?? 0) : 0;
        if ($c === 200) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['result']['message'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
    }

    public function send(string $to, string $text): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '') {
            return ['ok' => false, 'error' => __('کلید API یا شماره مقصد تنظیم نشده است.', 'signteb-web-chat')];
        }

        $body = ['message' => $text, 'receptor' => $to];
        if ($this->sender() !== '') {
            $body['linenumber'] = $this->sender();
        }

        $r = $this->http('https://api.ghasedak.me/v2/sms/send/simple', [
            'method'  => 'POST',
            'headers' => [
                'apikey'       => $key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $body,
        ]);
        $data = json_decode($r['body'], true);
        $code = is_array($data) ? (int) ($data['result']['code'] ?? 0) : 0;
        if ($code === 200) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['result']['message'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
    }
}

<?php
/**
 * Ghasedak (قاصدک) — simple send API.
 * Auth: a single API key in the "apikey" header.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Ghasedak extends SWC_Sms_Provider_Base
{
    public function id(): string
    {
        return 'ghasedak';
    }

    public function label(): string
    {
        return 'قاصدک (Ghasedak)';
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

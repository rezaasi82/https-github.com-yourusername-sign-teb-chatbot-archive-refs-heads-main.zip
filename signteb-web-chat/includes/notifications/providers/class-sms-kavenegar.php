<?php
/**
 * Kavenegar (کاوه‌نگار) — one of the most widely used Iranian SMS panels.
 * Auth: a single API key (activation code) from the panel dashboard.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Kavenegar extends SWC_Sms_Provider_Base
{
    public function id(): string
    {
        return 'kavenegar';
    }

    public function label(): string
    {
        return 'کاوه‌نگار (Kavenegar)';
    }

    public function send(string $to, string $text): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '') {
            return ['ok' => false, 'error' => __('کلید API یا شماره مقصد تنظیم نشده است.', 'signteb-web-chat')];
        }

        $url  = 'https://api.kavenegar.com/v1/' . rawurlencode($key) . '/sms/send.json';
        $body = ['receptor' => $to, 'message' => $text];
        if ($this->sender() !== '') {
            $body['sender'] = $this->sender();
        }

        $r = $this->http($url, ['method' => 'POST', 'body' => $body]);
        $data = json_decode($r['body'], true);
        $status = is_array($data) ? (int) ($data['return']['status'] ?? 0) : 0;
        if ($status === 200) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['return']['message'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
    }
}

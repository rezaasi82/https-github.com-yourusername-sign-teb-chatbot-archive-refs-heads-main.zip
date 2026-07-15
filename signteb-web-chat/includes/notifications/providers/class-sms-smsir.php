<?php
/**
 * SMS.ir (اس‌ام‌اس دات آی‌آر) — v1 bulk API.
 * Auth: a single API key sent in the X-API-KEY header. The sender line goes in
 * "lineNumber".
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Smsir extends SWC_Sms_Provider_Base
{
    public function id(): string
    {
        return 'smsir';
    }

    public function label(): string
    {
        return 'اس‌ام‌اس دات آی‌آر (SMS.ir)';
    }

    public function send(string $to, string $text): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '') {
            return ['ok' => false, 'error' => __('کلید API یا شماره مقصد تنظیم نشده است.', 'signteb-web-chat')];
        }

        $payload = [
            'messageText' => $text,
            'mobiles'     => [$to],
        ];
        if ($this->sender() !== '') {
            $payload['lineNumber'] = $this->sender();
        }

        $r = $this->http('https://api.sms.ir/v1/send/bulk', [
            'method'  => 'POST',
            'headers' => [
                'X-API-KEY'    => $key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
        ]);
        $data   = json_decode($r['body'], true);
        $status = is_array($data) ? (int) ($data['status'] ?? 0) : 0;
        if ($status === 1) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['message'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
    }
}

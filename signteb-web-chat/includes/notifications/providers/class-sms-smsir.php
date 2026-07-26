<?php
/**
 * SMS.ir (اس‌ام‌اس دات آی‌آر) — v1 bulk API.
 * Auth: a single API key sent in the X-API-KEY header. The sender line goes in
 * "lineNumber".
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Notifications\Providers;

if (! defined('ABSPATH')) {
    exit;
}

class SmsSmsir extends \Medora\Notifications\SmsProviderBase
{
    public function id(): string
    {
        return 'smsir';
    }

    public function label(): string
    {
        return 'اس‌ام‌اس دات آی‌آر (SMS.ir)';
    }

    public function supports_pattern(): bool
    {
        return true;
    }

    /**
     * Credential check via the credit endpoint — no SMS is sent.
     *
     * @return array{ok:bool,detail:string}
     */
    public function check(): array
    {
        $key = $this->api_key();
        if ($key === '') {
            return ['ok' => false, 'detail' => __('هیچ APIKey ذخیره نشده است.', 'signteb-web-chat')];
        }
        $r = $this->http('https://api.sms.ir/v1/credit', [
            'method'  => 'GET',
            'headers' => ['X-API-KEY' => $key, 'Accept' => 'application/json'],
        ]);
        if (! empty($r['error'])) {
            return ['ok' => false, 'detail' => sprintf(__('سرور سایت به پنل دسترسی ندارد: %s', 'signteb-web-chat'), $r['error'])];
        }
        $data = json_decode($r['body'], true);
        if ((int) ($data['status'] ?? 0) === 1) {
            return ['ok' => true, 'detail' => sprintf(__('اتصال برقرار ✓ — اعتبار: %s', 'signteb-web-chat'), (string) ($data['data'] ?? '?'))];
        }
        return ['ok' => false, 'detail' => sprintf(__('پنل اتصال را رد کرد: %s', 'signteb-web-chat'), (string) ($data['message'] ?? ('HTTP ' . $r['code'])))];
    }

    /**
     * Verify send — templateId + named parameters. Parameter names use the
     * upper-cased placeholder key (NAME, PHONE, …); define them the same way in
     * the SMS.ir template.
     */
    public function send_pattern(string $to, string $code, array $params): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '' || $code === '') {
            return ['ok' => false, 'error' => __('کلید API، شماره یا کد الگو تنظیم نشده است.', 'signteb-web-chat')];
        }

        $parameters = [];
        $i = 1;
        foreach ($params as $name => $val) {
            $pname = is_string($name) ? strtoupper($name) : ('PARAM' . $i);
            $parameters[] = ['name' => $pname, 'value' => (string) $val];
            $i++;
        }

        $r = $this->http('https://api.sms.ir/v1/send/verify', [
            'method'  => 'POST',
            'headers' => ['X-API-KEY' => $key, 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['mobile' => $to, 'templateId' => (int) $code, 'parameters' => $parameters]),
        ]);
        $data   = json_decode($r['body'], true);
        $status = is_array($data) ? (int) ($data['status'] ?? 0) : 0;
        if ($status === 1) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['message'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
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

<?php
/**
 * Kavenegar (کاوه‌نگار) — one of the most widely used Iranian SMS panels.
 * Auth: a single API key (activation code) from the panel dashboard.
 *
 * @package Medora
 */

namespace Medora\Notifications\Providers;

if (! defined('ABSPATH')) {
    exit;
}

class SmsKavenegar extends \Medora\Notifications\SmsProviderBase
{
    public function id(): string
    {
        return 'kavenegar';
    }

    public function label(): string
    {
        return 'کاوه‌نگار (Kavenegar)';
    }

    public function supports_pattern(): bool
    {
        return true;
    }

    /**
     * Credential check via account/info — no SMS is sent.
     *
     * @return array{ok:bool,detail:string}
     */
    public function check(): array
    {
        $key = $this->api_key();
        if ($key === '') {
            return ['ok' => false, 'detail' => __('هیچ APIKey ذخیره نشده است.', 'medora')];
        }
        $r = $this->http('https://api.kavenegar.com/v1/' . rawurlencode($key) . '/account/info.json', ['method' => 'GET']);
        if (! empty($r['error'])) {
            return ['ok' => false, 'detail' => sprintf(__('سرور سایت به پنل دسترسی ندارد: %s', 'medora'), $r['error'])];
        }
        $data = json_decode($r['body'], true);
        if ((int) ($data['return']['status'] ?? 0) === 200) {
            return ['ok' => true, 'detail' => sprintf(__('اتصال برقرار ✓ — اعتبار: %s', 'medora'), (string) ($data['entries']['remaincredit'] ?? '?'))];
        }
        return ['ok' => false, 'detail' => sprintf(__('پنل اتصال را رد کرد: %s', 'medora'), (string) ($data['return']['message'] ?? ('HTTP ' . $r['code'])))];
    }

    public function send(string $to, string $text): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '') {
            return ['ok' => false, 'error' => __('کلید API یا شماره مقصد تنظیم نشده است.', 'medora')];
        }

        $url  = 'https://api.kavenegar.com/v1/' . rawurlencode($key) . '/sms/send.json';
        $body = ['receptor' => $to, 'message' => $text];
        if ($this->sender() !== '') {
            $body['sender'] = $this->sender();
        }

        return $this->parse($this->http($url, ['method' => 'POST', 'body' => $body]));
    }

    /**
     * Verify Lookup — send an approved pattern (service line). Values map to
     * token, token2, token3 (Kavenegar rejects spaces here, so they become a
     * ZWNJ), then token10 / token20 which do accept spaces.
     */
    public function send_pattern(string $to, string $code, array $params): array
    {
        $key = $this->api_key();
        $to  = $this->normalize($to);
        if ($key === '' || $to === '' || $code === '') {
            return ['ok' => false, 'error' => __('کلید API، شماره یا کد الگو تنظیم نشده است.', 'medora')];
        }

        $slots = ['token', 'token2', 'token3', 'token10', 'token20'];
        $body  = ['receptor' => $to, 'template' => $code];
        $i = 0;
        foreach (array_values($params) as $val) {
            if (! isset($slots[$i])) {
                break;
            }
            $val = trim(preg_replace('/\s+/u', ' ', (string) $val));
            // token / token2 / token3 disallow spaces; token10 / token20 allow.
            if ($i < 3) {
                $val = str_replace(' ', '‌', $val); // ZWNJ keeps Persian readable
            }
            $body[$slots[$i]] = $val !== '' ? $val : '-';
            $i++;
        }

        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($key) . '/verify/lookup.json';
        return $this->parse($this->http($url, ['method' => 'POST', 'body' => $body]));
    }

    /**
     * @param array{ok:bool,code:int,body:string,error?:string} $r
     * @return array{ok:bool,error?:string,code?:int}
     */
    private function parse(array $r): array
    {
        $data   = json_decode($r['body'], true);
        $status = is_array($data) ? (int) ($data['return']['status'] ?? 0) : 0;
        if ($status === 200) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['return']['message'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'medora'))];
    }
}

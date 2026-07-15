<?php
/**
 * MeliPayamak (ملی‌پیامک) — classic REST SendSMS endpoint.
 * Auth: username + password from the panel (username in the "activation code"
 * field, password in the secondary field).
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Melipayamak extends SWC_Sms_Provider_Base
{
    public function id(): string
    {
        return 'melipayamak';
    }

    public function label(): string
    {
        return 'ملی‌پیامک (MeliPayamak)';
    }

    public function send(string $to, string $text): array
    {
        $user = $this->api_key();
        $pass = $this->api_secret();
        $to   = $this->normalize($to);
        if ($user === '' || $pass === '' || $to === '') {
            return ['ok' => false, 'error' => __('نام کاربری/رمز یا شماره مقصد تنظیم نشده است.', 'signteb-web-chat')];
        }

        $r = $this->http('https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
            'method' => 'POST',
            'body'   => [
                'username' => $user,
                'password' => $pass,
                'to'       => $to,
                'from'     => $this->sender(),
                'text'     => $text,
                'isflash'  => 'false',
            ],
        ]);
        $data = json_decode($r['body'], true);
        $ret  = is_array($data) ? (int) ($data['RetStatus'] ?? 0) : 0;
        if ($ret === 1) {
            return ['ok' => true, 'code' => 200];
        }
        $msg = is_array($data) ? (string) ($data['StrRetStatus'] ?? '') : '';
        return ['ok' => false, 'code' => $r['code'], 'error' => $msg !== '' && $msg !== 'Ok' ? $msg : ($r['error'] ?? __('ارسال ناموفق بود.', 'signteb-web-chat'))];
    }
}

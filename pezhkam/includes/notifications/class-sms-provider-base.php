<?php
/**
 * Shared plumbing for the concrete SMS gateways
 * (config access, phone normalisation, a single wp_remote_* wrapper).
 *
 * @package Pezhkam
 */

namespace Pezhkam\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

abstract class SmsProviderBase implements \Pezhkam\Notifications\SmsProviderInterface
{
    protected \Pezhkam\Core\Settings $settings;

    /** Most panels support pattern SMS; individual providers override. */
    public function supports_pattern(): bool
    {
        return false;
    }

    /** Default: no pattern support — the manager falls back to text send. */
    public function send_pattern(string $to, string $code, array $params): array
    {
        return ['ok' => false, 'error' => __('این سرویس از ارسال الگو پشتیبانی نمی‌کند.', 'pezhkam')];
    }

    public function __construct(?\Pezhkam\Core\Settings $settings = null)
    {
        $this->settings = $settings ?? new \Pezhkam\Core\Settings();
    }

    /** Decrypted API key / activation code entered from the panel. */
    protected function api_key(): string
    {
        return \Pezhkam\Notifications\SmsManager::key();
    }

    /** Optional second credential (e.g. MeliPayamak password). */
    protected function api_secret(): string
    {
        return \Pezhkam\Notifications\SmsManager::secret();
    }

    /** Configured sender line / originator number. */
    protected function sender(): string
    {
        return trim((string) $this->settings->get('sms_sender', ''));
    }

    /**
     * Normalise an Iranian mobile to the panel-friendly 09xxxxxxxxx form and
     * strip anything non-numeric. Foreign numbers keep a leading +.
     */
    protected function normalize(string $to): string
    {
        $to = trim($to);
        $plus = str_starts_with($to, '+') || str_starts_with($to, '0098') || str_starts_with($to, '98');
        $digits = preg_replace('/\D+/', '', $to);
        if ($digits === '') {
            return '';
        }
        // 98xxxxxxxxxx / 0098… -> 0xxxxxxxxxx for Iranian panels.
        if (str_starts_with($digits, '0098')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        return $plus && ! str_starts_with($digits, '0') ? '+' . $digits : $digits;
    }

    /**
     * Thin wrapper over wp_remote_request with the house 22s timeout.
     *
     * @param array<string,mixed> $args
     * @return array{ok:bool,code:int,body:string,error?:string}
     */
    protected function http(string $url, array $args): array
    {
        $args = wp_parse_args($args, ['timeout' => 22]);
        $res  = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            return ['ok' => false, 'code' => 0, 'body' => '', 'error' => $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => (string) wp_remote_retrieve_body($res)];
    }
}

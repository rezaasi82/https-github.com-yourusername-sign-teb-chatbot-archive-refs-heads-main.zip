<?php
/**
 * Custom HTTP gateway — for foreign providers (Twilio, Vonage, MessageBird, …)
 * or any panel with an HTTP API. Fully configurable: endpoint, method, headers
 * and a body template. Placeholders {to} {text} {key} {secret} {sender} are
 * substituted (URL-encoded inside the body). Success = any 2xx response.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sms_Custom extends SWC_Sms_Provider_Base
{
    public function id(): string
    {
        return 'custom';
    }

    public function label(): string
    {
        return __('سفارشی / خارجی (Custom HTTP)', 'signteb-web-chat');
    }

    public function send(string $to, string $text): array
    {
        $url    = trim((string) $this->settings->get('sms_custom_url', ''));
        $method = strtoupper((string) $this->settings->get('sms_custom_method', 'POST'));
        $method = in_array($method, ['GET', 'POST', 'PUT'], true) ? $method : 'POST';
        if ($url === '') {
            return ['ok' => false, 'error' => __('آدرس سرویس سفارشی تنظیم نشده است.', 'signteb-web-chat')];
        }

        $vals = [
            '{to}'     => $to,
            '{text}'   => $text,
            '{key}'    => $this->api_key(),
            '{secret}' => $this->api_secret(),
            '{sender}' => $this->sender(),
        ];

        // The URL always needs query-safe encoding (GET-based panels).
        $url = strtr($url, array_map('rawurlencode', $vals));

        $headers = $this->parse_headers((string) $this->settings->get('sms_custom_headers', ''));
        $bodyTpl = (string) $this->settings->get('sms_custom_body', '');
        // Encode body values to match the declared content-type so Persian text
        // and special characters survive: JSON-escape for JSON, url-encode for
        // form bodies, raw otherwise.
        $body = $bodyTpl !== '' ? strtr($bodyTpl, $this->encode_for_body($vals, $headers)) : '';

        $args = ['method' => $method, 'headers' => $headers];
        if ($method !== 'GET' && $body !== '') {
            $args['body'] = $body;
        }

        $r = $this->http($url, $args);
        if ($r['ok']) {
            return ['ok' => true, 'code' => $r['code']];
        }
        return ['ok' => false, 'code' => $r['code'], 'error' => $r['error'] ?? sprintf(__('پاسخ سرویس: HTTP %d', 'signteb-web-chat'), $r['code'])];
    }

    /**
     * Encode placeholder values for the request body according to the declared
     * Content-Type header.
     *
     * @param array<string,string> $vals    placeholder => raw value
     * @param array<string,string> $headers request headers
     * @return array<string,string> placeholder => encoded value
     */
    private function encode_for_body(array $vals, array $headers): array
    {
        $ctype = '';
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'content-type') {
                $ctype = strtolower($v);
            }
        }
        $is_json = strpos($ctype, 'json') !== false;
        $is_form = strpos($ctype, 'form-urlencoded') !== false;

        $out = [];
        foreach ($vals as $ph => $val) {
            if ($is_json) {
                // JSON string escaping without the surrounding quotes.
                $out[$ph] = substr((string) wp_json_encode((string) $val), 1, -1);
            } elseif ($is_form) {
                $out[$ph] = rawurlencode((string) $val);
            } else {
                $out[$ph] = (string) $val;
            }
        }
        return $out;
    }

    /**
     * Parse "Key: Value" lines into a headers array.
     *
     * @return array<string,string>
     */
    private function parse_headers(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }
}

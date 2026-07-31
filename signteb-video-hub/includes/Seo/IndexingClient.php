<?php

namespace SignTeb\VideoHub\Seo;

use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 13 — Google Indexing API.
 *
 * Authenticates with a service-account JWT (RS256) exchanged for an access
 * token; no Google SDK is required, which keeps the plugin dependency-free.
 * The account must be added as an owner of the property in Search Console or
 * every call returns 403.
 */
class IndexingClient
{
    private const TOKEN_ENDPOINT    = 'https://oauth2.googleapis.com/token';
    private const PUBLISH_ENDPOINT  = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    private const SCOPE             = 'https://www.googleapis.com/auth/indexing';
    private const TOKEN_TRANSIENT   = 'stvh_google_token';

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function is_configured(): bool
    {
        return $this->settings->bool('google_indexing') && $this->credentials() !== [];
    }

    /**
     * Notify Google that a URL was published or updated.
     *
     * @return array{ok:bool,message:string}
     */
    public function publish(string $url, string $type = 'URL_UPDATED'): array
    {
        if ($url === '') {
            return ['ok' => false, 'message' => 'آدرس خالی است.'];
        }
        if (! $this->is_configured()) {
            return ['ok' => false, 'message' => 'ایندکس گوگل تنظیم نشده است.'];
        }

        $token = $this->access_token();
        if ($token === '') {
            return ['ok' => false, 'message' => 'دریافت توکن گوگل ناموفق بود.'];
        }

        $response = wp_remote_post(self::PUBLISH_ENDPOINT, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'url'  => $url,
                'type' => in_array($type, ['URL_UPDATED', 'URL_DELETED'], true) ? $type : 'URL_UPDATED',
            ]),
        ]);

        if (is_wp_error($response)) {
            Logger::error('indexing', $response->get_error_message(), ['url' => $url]);
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = Json::decode((string) wp_remote_retrieve_body($response));

        if ($code !== 200) {
            $message = (string) Json::dig($body, 'error.message', sprintf('گوگل کد %d برگرداند.', $code));
            Logger::error('indexing', $message, ['url' => $url, 'code' => $code]);
            return ['ok' => false, 'message' => $message];
        }

        update_option('stvh_last_index_ping', current_time('mysql'), false);

        return ['ok' => true, 'message' => 'درخواست ایندکس ارسال شد.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function test_connection(): array
    {
        if ($this->credentials() === []) {
            return ['ok' => false, 'message' => 'فایل JSON سرویس‌اکانت وارد نشده یا معتبر نیست.'];
        }
        if (! function_exists('openssl_sign')) {
            return ['ok' => false, 'message' => 'افزونه OpenSSL روی سرور فعال نیست.'];
        }

        return $this->access_token() !== ''
            ? ['ok' => true, 'message' => 'احراز هویت گوگل موفق بود.']
            : ['ok' => false, 'message' => 'احراز هویت گوگل ناموفق بود؛ فایل سرویس‌اکانت را بررسی کنید.'];
    }

    /**
     * Cached OAuth token (Google issues them for an hour; we keep 55 minutes).
     */
    private function access_token(): string
    {
        $cached = get_transient(self::TOKEN_TRANSIENT);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $jwt = $this->build_jwt();
        if ($jwt === '') {
            return '';
        }

        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout' => 12,
            'body'    => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::error('indexing', 'توکن گوگل: ' . $response->get_error_message());
            return '';
        }

        $body  = Json::decode((string) wp_remote_retrieve_body($response));
        $token = (string) ($body['access_token'] ?? '');

        if ($token === '') {
            Logger::error('indexing', (string) Json::dig($body, 'error_description', 'دریافت توکن ناموفق بود.'));
            return '';
        }

        set_transient(self::TOKEN_TRANSIENT, $token, 55 * MINUTE_IN_SECONDS);

        return $token;
    }

    private function build_jwt(): string
    {
        $credentials = $this->credentials();
        if ($credentials === [] || ! function_exists('openssl_sign')) {
            return '';
        }

        $now    = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_ENDPOINT,
            'exp'   => $now + HOUR_IN_SECONDS,
            'iat'   => $now,
        ];

        $payload   = $this->base64url(Json::encode($header)) . '.' . $this->base64url(Json::encode($claims));
        $signature = '';

        if (! openssl_sign($payload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            Logger::error('indexing', 'امضای JWT ناموفق بود؛ کلید خصوصی معتبر نیست.');
            return '';
        }

        return $payload . '.' . $this->base64url($signature);
    }

    /**
     * @return array{client_email:string,private_key:string}|array{}
     */
    private function credentials(): array
    {
        $raw = $this->settings->secret('google_service_json');
        if ($raw === '') {
            return [];
        }

        $data  = Json::decode($raw);
        $email = (string) ($data['client_email'] ?? '');
        $key   = (string) ($data['private_key'] ?? '');

        if ($email === '' || $key === '') {
            return [];
        }

        // Pasting through a textarea turns real newlines into "\n" literals.
        $key = str_replace('\\n', "\n", $key);

        return ['client_email' => $email, 'private_key' => $key];
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function last_ping(): string
    {
        return (string) get_option('stvh_last_index_ping', '');
    }
}

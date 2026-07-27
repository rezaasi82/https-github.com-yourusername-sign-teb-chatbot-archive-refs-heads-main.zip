<?php

namespace SignTeb\VideoHub\Api\Aparat;

use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * HTTP transport for Aparat.
 *
 * Aparat has shipped two generations of its public read API and shared hosts
 * see both, so we try the v1 endpoint first and fall back to the legacy /etc/
 * one. Neither needs a key — a channel username is enough (feature 1).
 */
class AparatClient
{
    private const V1_ENDPOINT     = 'https://www.aparat.com/api/fa/v1/video/video/list/username/%s';
    private const LEGACY_ENDPOINT = 'https://www.aparat.com/etc/api/videoByUser/username/%s/perpage/%d';
    private const PROFILE_V1      = 'https://www.aparat.com/api/fa/v1/user/user/information/username/%s';

    private const TIMEOUT = 20;

    /**
     * Raw video list for a channel, newest first.
     *
     * @return array{ok:bool,items:array<int,array<string,mixed>>,error?:string}
     */
    public function videos_by_username(string $username, int $limit): array
    {
        $username = self::normalize_username($username);
        if ($username === '') {
            return ['ok' => false, 'items' => [], 'error' => 'نام کانال آپارات خالی است.'];
        }

        $limit = max(1, min(100, $limit));

        $v1 = $this->request(sprintf(self::V1_ENDPOINT, rawurlencode($username)));
        if ($v1['ok']) {
            $items = $this->extract_v1_items($v1['body']);
            if ($items !== []) {
                return ['ok' => true, 'items' => array_slice($items, 0, $limit)];
            }
        }

        $legacy = $this->request(sprintf(self::LEGACY_ENDPOINT, rawurlencode($username), $limit));
        if ($legacy['ok']) {
            $items = $this->extract_legacy_items($legacy['body']);
            if ($items !== []) {
                return ['ok' => true, 'items' => array_slice($items, 0, $limit)];
            }
        }

        $error = $v1['error'] ?? ($legacy['error'] ?? 'پاسخ آپارات خالی یا غیرقابل خواندن بود.');
        Logger::error('aparat', $error, ['username' => $username]);

        return ['ok' => false, 'items' => [], 'error' => $error];
    }

    /**
     * Channel existence probe used by the "تست اتصال" button.
     *
     * @return array{ok:bool,message:string}
     */
    public function test_channel(string $username): array
    {
        $username = self::normalize_username($username);
        if ($username === '') {
            return ['ok' => false, 'message' => 'نام کانال وارد نشده است.'];
        }

        $profile = $this->request(sprintf(self::PROFILE_V1, rawurlencode($username)));
        if ($profile['ok'] && Json::dig($profile['body'], 'data.attributes.username') !== null) {
            $name = (string) Json::dig($profile['body'], 'data.attributes.name', $username);
            return ['ok' => true, 'message' => sprintf('اتصال برقرار است — کانال «%s».', $name)];
        }

        // Some hosts block the profile route but not the video route.
        $videos = $this->videos_by_username($username, 1);
        if ($videos['ok']) {
            return ['ok' => true, 'message' => 'اتصال برقرار است.'];
        }

        return ['ok' => false, 'message' => $videos['error'] ?? 'کانال پیدا نشد.'];
    }

    /**
     * A channel can be given as "user", "@user" or a full aparat.com URL.
     */
    public static function normalize_username(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        if (str_contains($input, 'aparat.com')) {
            $path  = (string) wp_parse_url($input, PHP_URL_PATH);
            $parts = array_values(array_filter(explode('/', $path)));
            // /v/xyz is a video, /{username} (or /u/{username}) is a channel.
            $input = $parts !== [] ? (string) end($parts) : '';
        }
        return ltrim(trim($input), '@');
    }

    /**
     * @return array{ok:bool,body:array<mixed>,error?:string}
     */
    private function request(string $url): array
    {
        $response = wp_remote_get($url, [
            'timeout'    => self::TIMEOUT,
            'user-agent' => 'SignTeb-Video-Hub/' . STVH_VERSION . '; ' . home_url('/'),
            'headers'    => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'body' => [], 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return ['ok' => false, 'body' => [], 'error' => sprintf('آپارات کد %d برگرداند.', $code)];
        }

        $body = Json::decode((string) wp_remote_retrieve_body($response));
        if ($body === []) {
            return ['ok' => false, 'body' => [], 'error' => 'پاسخ آپارات JSON معتبر نبود.'];
        }

        return ['ok' => true, 'body' => $body];
    }

    /**
     * v1 wraps records in JSON:API style {data:[{attributes:{…}}]}.
     *
     * @param array<mixed> $body
     * @return array<int,array<string,mixed>>
     */
    private function extract_v1_items(array $body): array
    {
        $data = $body['data'] ?? [];
        if (! is_array($data)) {
            return [];
        }

        $items = [];
        foreach ($data as $record) {
            if (! is_array($record)) {
                continue;
            }
            $attributes = $record['attributes'] ?? $record;
            if (is_array($attributes) && ($attributes['uid'] ?? $attributes['id'] ?? null) !== null) {
                $items[] = $attributes;
            }
        }
        return $items;
    }

    /**
     * The legacy endpoint returns {videobyuser:[…]} with flat records.
     *
     * @param array<mixed> $body
     * @return array<int,array<string,mixed>>
     */
    private function extract_legacy_items(array $body): array
    {
        $data = $body['videobyuser'] ?? $body['videos'] ?? [];
        if (! is_array($data)) {
            return [];
        }
        return array_values(array_filter($data, 'is_array'));
    }
}

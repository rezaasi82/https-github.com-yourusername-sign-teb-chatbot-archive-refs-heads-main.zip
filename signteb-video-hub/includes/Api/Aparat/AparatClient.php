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
    private const PLAYLISTS_V1    = 'https://www.aparat.com/api/fa/v1/video/playlist/getuserplaylist/username/%s';
    private const PLAYLIST_V1     = 'https://www.aparat.com/api/fa/v1/video/playlist/one/playlist_id/%s';

    private const TIMEOUT = 12;

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
     * Playlists published on a channel.
     *
     * @return array{ok:bool,items:array<int,array{id:string,title:string,count:int}>,error?:string}
     */
    public function playlists_by_username(string $username): array
    {
        $username = self::normalize_username($username);
        if ($username === '') {
            return ['ok' => false, 'items' => [], 'error' => 'نام کانال آپارات خالی است.'];
        }

        $response = $this->request(sprintf(self::PLAYLISTS_V1, rawurlencode($username)));
        if (! $response['ok']) {
            return ['ok' => false, 'items' => [], 'error' => $response['error'] ?? 'دریافت فهرست‌ها ناموفق بود.'];
        }

        $items = [];
        foreach ((array) ($response['body']['data'] ?? []) as $record) {
            if (! is_array($record)) {
                continue;
            }
            $attributes = $record['attributes'] ?? $record;
            $id         = (string) ($record['id'] ?? $attributes['id'] ?? '');
            $title      = trim((string) ($attributes['title'] ?? $attributes['name'] ?? ''));

            if ($id === '' || $title === '') {
                continue;
            }

            $items[] = [
                'id'    => $id,
                'title' => $title,
                'count' => (int) ($attributes['cnt'] ?? $attributes['video_cnt'] ?? $attributes['count'] ?? 0),
            ];
        }

        return $items === []
            ? ['ok' => false, 'items' => [], 'error' => 'این کانال فهرستی ندارد یا ساختار پاسخ شناخته نشد.']
            : ['ok' => true, 'items' => $items];
    }

    /**
     * Videos inside one playlist.
     *
     * @return array{ok:bool,items:array<int,array<string,mixed>>,error?:string}
     */
    public function videos_by_playlist(string $playlist_id, int $limit): array
    {
        $playlist_id = trim($playlist_id);
        if ($playlist_id === '') {
            return ['ok' => false, 'items' => [], 'error' => 'شناسه فهرست خالی است.'];
        }

        $response = $this->request(sprintf(self::PLAYLIST_V1, rawurlencode($playlist_id)));
        if (! $response['ok']) {
            return ['ok' => false, 'items' => [], 'error' => $response['error'] ?? 'دریافت فهرست ناموفق بود.'];
        }

        $items = $this->extract_playlist_items($response['body']);

        return $items === []
            ? ['ok' => false, 'items' => [], 'error' => 'فهرست خالی بود یا ساختار پاسخ شناخته نشد.']
            : ['ok' => true, 'items' => array_slice($items, 0, max(1, min(100, $limit)))];
    }

    /**
     * Aparat nests playlist videos a level deeper than the channel list, and
     * the exact shape has moved between API revisions — so every plausible
     * container is checked rather than assuming one.
     *
     * @param array<mixed> $body
     * @return array<int,array<string,mixed>>
     */
    private function extract_playlist_items(array $body): array
    {
        $candidates = [
            $body['data']['attributes']['videos'] ?? null,
            $body['data']['videos'] ?? null,
            $body['included'] ?? null,
            $body['videos'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            $items = [];
            foreach ($candidate as $record) {
                if (! is_array($record)) {
                    continue;
                }
                $attributes = $record['attributes'] ?? $record;
                if (is_array($attributes) && ($attributes['uid'] ?? $attributes['id'] ?? null) !== null) {
                    $items[] = $attributes;
                }
            }

            if ($items !== []) {
                return $items;
            }
        }

        return [];
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

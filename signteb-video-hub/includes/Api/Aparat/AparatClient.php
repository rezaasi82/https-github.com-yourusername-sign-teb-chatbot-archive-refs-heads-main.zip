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

    private const TIMEOUT = 12;

    /** Shorter, because endpoint discovery tries several in a row. */
    private const PROBE_TIMEOUT = 6;

    /**
     * Candidate shapes for "videos in a playlist", newest API style first.
     *
     * Aparat publishes no API reference and the one path that looked right
     * answered 405 in production. Rather than guess again from an environment
     * that cannot reach aparat.com, the plugin probes these on the site's own
     * server — which can — and remembers whichever answers with usable JSON.
     * The /etc/ family is listed because the channel endpoint that already
     * works in production lives there.
     */
    private const PLAYLIST_CANDIDATES = [
        'https://www.aparat.com/etc/api/playlist/id/%s',
        'https://www.aparat.com/etc/api/playlistVideos/id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/getone/id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/listbyid/playlist_id/%s',
    ];

    private const ENDPOINT_CACHE = 'stvh_aparat_playlist_endpoint';

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
     * A playlist id from a URL like https://www.aparat.com/playlist/1234567,
     * or a bare id. Returns '' when nothing numeric can be found.
     */
    public static function normalize_playlist(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (preg_match('~playlist/(\d+)~', $input, $m)) {
            return $m[1];
        }

        return ctype_digit($input) ? $input : '';
    }

    /**
     * Videos inside a playlist.
     *
     * @return array{ok:bool,items:array<int,array<string,mixed>>,error?:string,endpoint?:string}
     */
    public function videos_by_playlist(string $playlist_id, int $limit): array
    {
        $playlist_id = self::normalize_playlist($playlist_id);
        if ($playlist_id === '') {
            return ['ok' => false, 'items' => [], 'error' => 'شناسه فهرست پخش معتبر نیست.'];
        }

        // A known-good endpoint is reused directly; only the first run probes.
        $known = get_transient(self::ENDPOINT_CACHE);
        $order = is_string($known) && $known !== ''
            ? array_merge([$known], array_diff(self::PLAYLIST_CANDIDATES, [$known]))
            : self::PLAYLIST_CANDIDATES;

        $errors = [];
        foreach ($order as $template) {
            $response = $this->request(sprintf($template, rawurlencode($playlist_id)), self::PROBE_TIMEOUT);

            if (! $response['ok']) {
                $errors[] = sprintf('%s → %s', $this->endpoint_label($template), $response['error'] ?? '?');
                continue;
            }

            $items = $this->extract_playlist_items($response['body']);
            if ($items === []) {
                $errors[] = sprintf('%s → پاسخ بدون ویدئو', $this->endpoint_label($template));
                continue;
            }

            set_transient(self::ENDPOINT_CACHE, $template, WEEK_IN_SECONDS);
            Logger::info('aparat', 'endpoint فهرست پخش پیدا شد: ' . $this->endpoint_label($template));

            return [
                'ok'       => true,
                'items'    => array_slice($items, 0, max(1, min(100, $limit))),
                'endpoint' => $template,
            ];
        }

        delete_transient(self::ENDPOINT_CACHE);

        return [
            'ok'    => false,
            'items' => [],
            'error' => 'هیچ‌کدام از مسیرهای فهرست پخش آپارات جواب نداد — ' . implode(' | ', $errors),
        ];
    }

    /**
     * Just the path, for a log line that stays readable.
     */
    private function endpoint_label(string $template): string
    {
        return (string) wp_parse_url(sprintf($template, '0'), PHP_URL_PATH);
    }

    /**
     * Playlist payloads nest a level deeper than the channel list and the
     * shape has moved between revisions, so every plausible container is
     * checked rather than assuming one.
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
            $body['playlist'] ?? null,
            $body['videos'] ?? null,
            $body['videobyuser'] ?? null,
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
    private function request(string $url, ?int $timeout = null): array
    {
        $response = wp_remote_get($url, [
            'timeout'    => $timeout ?? self::TIMEOUT,
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

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
        'https://www.aparat.com/etc/api/playlist/id/%s/perpage/100',
        'https://www.aparat.com/etc/api/playlistVideos/id/%s/perpage/100',
        'https://www.aparat.com/api/fa/v1/video/playlist/getone/id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/listbyid/playlist_id/%s',
    ];

    private const ENDPOINT_CACHE = 'stvh_aparat_playlist_endpoint';

    /**
     * Ceiling on a whole discovery pass. Probing is only worth doing if it
     * cannot itself become the slow request — that is what took the site down
     * before this budget existed.
     */
    private const PROBE_BUDGET = 15;

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

        $errors   = [];
        $deadline = microtime(true) + self::PROBE_BUDGET;

        foreach ($order as $template) {
            if (microtime(true) >= $deadline) {
                $errors[] = 'مهلت بررسی مسیرها تمام شد';
                break;
            }

            $response = $this->request(sprintf($template, rawurlencode($playlist_id)), self::PROBE_TIMEOUT);

            if (! $response['ok']) {
                $errors[] = sprintf('%s → %s', $this->endpoint_label($template), $response['error'] ?? '?');
                continue;
            }

            $items = self::extract_playlist_items($response['body']);
            if ($items === []) {
                // The route answered; only the shape is unknown. Report it, so
                // the next step is a fix rather than another guess.
                $errors[] = sprintf(
                    '%s → پاسخ ۲۰۰ ولی ویدئویی شناسایی نشد؛ ساختار پاسخ: %s',
                    $this->endpoint_label($template),
                    mb_substr(self::describe_shape($response['body']), 0, 300)
                );
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
     * Just the path, for a log line that stays readable. The id is shown as a
     * placeholder — substituting a real-looking one made an earlier report
     * read as though the plugin had requested playlist 0.
     */
    private function endpoint_label(string $template): string
    {
        return (string) wp_parse_url(sprintf($template, 'ID'), PHP_URL_PATH);
    }

    /**
     * Videos anywhere inside a playlist payload.
     *
     * The first attempt guessed at a fixed list of containers and found
     * nothing, even though two routes answered 200 with real JSON — the route
     * was right and the shape was wrong. Guessing container names again would
     * repeat that, so this searches the whole tree instead and keeps the
     * largest set of records that actually look like videos.
     *
     * @param array<mixed> $body
     * @return array<int,array<string,mixed>>
     */
    public static function extract_playlist_items(array $body): array
    {
        return self::find_video_records($body);
    }

    /**
     * @param array<mixed> $node
     * @return array<int,array<string,mixed>>
     */
    private static function find_video_records(array $node, int $depth = 0): array
    {
        if ($depth > 6) {
            return [];
        }

        // JSON:API wraps each record in {type, id, attributes:{…}}, so unwrap
        // before judging. A sibling list of videos is the common case.
        $direct = [];
        foreach ($node as $record) {
            if (! is_array($record)) {
                continue;
            }
            $fields = isset($record['attributes']) && is_array($record['attributes'])
                ? $record['attributes']
                : $record;
            if (self::looks_like_video($fields)) {
                $direct[] = $fields;
            }
        }

        if ($direct !== []) {
            return $direct;
        }

        $best = [];
        foreach ($node as $child) {
            if (! is_array($child)) {
                continue;
            }
            $found = self::find_video_records($child, $depth + 1);
            if (count($found) > count($best)) {
                $best = $found;
            }
        }

        return $best;
    }

    /**
     * A playlist payload also contains the playlist itself, its owner and
     * assorted UI nodes — all of which carry `id` and `title`. Only fields
     * that exist per-video can tell them apart, so a bare id is never enough.
     *
     * @param array<string,mixed> $record
     */
    private static function looks_like_video(array $record): bool
    {
        foreach (['uid', 'videohash', 'hash'] as $key) {
            if (isset($record[$key]) && is_scalar($record[$key]) && (string) $record[$key] !== '') {
                return true;
            }
        }

        if (! isset($record['id']) || ! is_scalar($record['id']) || (string) $record['id'] === '') {
            return false;
        }

        foreach (['frame', 'big_poster', 'small_poster', 'poster', 'preview_src', 'file_link'] as $key) {
            if (isset($record[$key]) && $record[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * A compact outline of a JSON body's keys.
     *
     * When a route answers 200 but nothing parses, the useful thing to report
     * is not "no videos" but what actually came back — otherwise the next fix
     * is another guess.
     *
     * @param array<mixed> $body
     */
    public static function describe_shape(array $body, int $depth = 0): string
    {
        $parts = [];
        $shown = 0;

        foreach ($body as $key => $value) {
            if ($shown++ >= 6) {
                $parts[] = '…';
                break;
            }

            $label = is_int($key) ? '[' . $key . ']' : (string) $key;

            if (is_array($value) && $depth < 2 && $value !== []) {
                $parts[] = $label . '{' . self::describe_shape($value, $depth + 1) . '}';
                continue;
            }

            $parts[] = $label;
        }

        return implode(',', $parts);
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

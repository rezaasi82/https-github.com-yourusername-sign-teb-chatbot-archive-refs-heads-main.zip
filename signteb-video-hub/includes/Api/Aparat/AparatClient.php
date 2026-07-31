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
        // `/playlist/list` answered 400, not 405 — the only route so far that
        // parsed the request and objected to its parameters, which makes its
        // parameter shape the most promising thing left to vary.
        'https://www.aparat.com/api/fa/v1/video/playlist/list?id=%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/list/id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/list?playlist_id=%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/videos/id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/videos?id=%s',
        // Path-segment style, as used by the working channel endpoint.
        'https://www.aparat.com/api/fa/v1/video/playlist/getone/id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/getone?id=%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/getone/playlist_id/%s',
        'https://www.aparat.com/api/fa/v1/video/playlist/listbyid/playlist_id/%s',
        // The /etc/ family answers "username or password missing", so it is an
        // authenticated API rather than a public one. Kept last and only for
        // completeness — it is not expected to work without credentials.
        'https://www.aparat.com/etc/api/playlist/id/%s',
        'https://www.aparat.com/etc/api/playlistVideos/id/%s',
    ];

    /** The public playlist page — no API, no key, just HTML. */
    private const PLAYLIST_PAGE = 'https://www.aparat.com/playlist/%s';

    private const ENDPOINT_CACHE = 'stvh_aparat_playlist_endpoint';

    /** Written when a full probe finds nothing, so syncs stop re-probing. */
    private const ENDPOINT_NONE = 'none';

    /**
     * Ceiling on a whole discovery pass. Probing is only worth doing if it
     * cannot itself become the slow request — that is what took the site down
     * before this budget existed.
     */
    private const PROBE_BUDGET = 15;

    /** Below this there is no point starting a request; it would only time out. */
    private const MIN_TIMEOUT = 3;

    /** Wall-clock instant after which no new request may start. */
    private ?float $deadline = null;

    public function set_deadline(?float $deadline): void
    {
        $this->deadline = $deadline;
    }

    /**
     * Seconds this call may take, or null when the deadline has already
     * passed and the call must not be made at all.
     */
    private function budgeted_timeout(int $preferred): ?int
    {
        if ($this->deadline === null) {
            return $preferred;
        }

        $left = $this->deadline - microtime(true);

        return $left >= self::MIN_TIMEOUT ? (int) min($preferred, floor($left)) : null;
    }

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
     * Video ids from a pasted list of Aparat links or bare hashes.
     *
     * The one selection method that depends on nothing Aparat has to grant:
     * the admin can see the playlist in a browser even when the server cannot,
     * so they can supply the list directly. Separators are deliberately loose
     * — newlines, commas and spaces all work, because a copied column of links
     * arrives in whichever of those a browser felt like using.
     *
     * @return array<int,string>
     */
    public static function normalize_video_ids(string $input): array
    {
        $input = str_replace('\\/', '/', trim($input));
        if ($input === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[\s,،;]+/u', $input) ?: [] as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            if (preg_match('~/v/([A-Za-z0-9_-]{4,24})~', $token, $m)) {
                $ids[] = $m[1];
                continue;
            }

            // A bare hash. Anything with a slash or a dot is a URL we failed to
            // read, not an id, and silently importing the wrong thing is worse
            // than skipping it.
            if (preg_match('~^[A-Za-z0-9_-]{4,24}$~', $token)) {
                $ids[] = $token;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Videos inside a playlist.
     *
     * @return array{ok:bool,items:array<int,array<string,mixed>>,error?:string,endpoint?:string}
     */
    public function videos_by_playlist(string $playlist_id, int $limit, bool $force = false): array
    {
        $playlist_id = self::normalize_playlist($playlist_id);
        if ($playlist_id === '') {
            return ['ok' => false, 'items' => [], 'error' => 'شناسه فهرست پخش معتبر نیست.'];
        }

        $known = get_transient(self::ENDPOINT_CACHE);
        $known = is_string($known) && $known !== '' && $known !== self::ENDPOINT_NONE ? $known : '';

        // Discovery is an admin action, never a background one. A sync gets one
        // request against an endpoint already known to work — trying nine in a
        // row is exactly the kind of unbounded work that turns a cron tick into
        // an unreachable site, and it would repeat on every single run.
        if (! $force) {
            if ($known === '') {
                return [
                    'ok'    => false,
                    'items' => [],
                    'error' => 'مسیر API فهرست پخش شناخته‌شده نیست؛ کشف مسیر فقط با دکمه‌ی «تست فهرست پخش» انجام می‌شود.',
                ];
            }

            $order = [$known];
        } else {
            $order = $known !== ''
                ? array_merge([$known], array_diff(self::PLAYLIST_CANDIDATES, [$known]))
                : self::PLAYLIST_CANDIDATES;
        }

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
                // The legacy API answers 200 with {"login":{type,value}} when a
                // method does not exist, so a 200 is not proof of a live route.
                // Report its own words rather than "no videos".
                $complaint = (string) Json::dig($response['body'], 'login.value', '');

                $errors[] = sprintf(
                    '%s → %s',
                    $this->endpoint_label($template),
                    $complaint !== ''
                        ? 'آپارات: ' . mb_substr($complaint, 0, 120)
                        : 'پاسخ ۲۰۰ ولی ویدئویی شناسایی نشد؛ ساختار پاسخ: '
                            . mb_substr(self::describe_shape($response['body']), 0, 200)
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

        // Only a full pass may conclude that nothing works. A single failed
        // request during a sync says nothing about the other candidates, and
        // must not discard an endpoint that was proven to work.
        if ($force) {
            set_transient(self::ENDPOINT_CACHE, self::ENDPOINT_NONE, DAY_IN_SECONDS);
        }

        return [
            'ok'    => false,
            'items' => [],
            'error' => 'هیچ‌کدام از مسیرهای فهرست پخش آپارات جواب نداد — ' . implode(' | ', $errors),
        ];
    }

    /**
     * Video ids listed on a playlist's public page.
     *
     * Aparat's playlist API could not be found: four legacy paths answer 200
     * with the `login` error envelope and two v1 paths answer 405. The page
     * itself needs no API and no key, and the site's own server can fetch it —
     * so the ids are read from the markup and matched against the channel
     * list, an endpoint that already works.
     *
     * @return array{ok:bool,ids:array<int,string>,error?:string,strict?:bool}
     */
    public function playlist_video_ids(string $playlist_id): array
    {
        $playlist_id = self::normalize_playlist($playlist_id);
        if ($playlist_id === '') {
            return ['ok' => false, 'ids' => [], 'error' => 'شناسه فهرست پخش معتبر نیست.'];
        }

        $timeout = $this->budgeted_timeout(self::TIMEOUT);
        if ($timeout === null) {
            return ['ok' => false, 'ids' => [], 'error' => 'مهلت این اجرا تمام شد؛ ادامه در اجرای بعدی.'];
        }

        $response = wp_remote_get(sprintf(self::PLAYLIST_PAGE, rawurlencode($playlist_id)), [
            'timeout'    => $timeout,
            // The conventional identified-crawler form. A bare product token
            // is what many CDNs treat as unknown and serve an empty shell to.
            'user-agent' => 'Mozilla/5.0 (compatible; SignTeb-Video-Hub/' . STVH_VERSION . '; +' . home_url('/') . ')',
            'headers'    => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'ids' => [], 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return ['ok' => false, 'ids' => [], 'error' => sprintf('صفحه‌ی فهرست پخش کد %d برگرداند.', $code)];
        }

        $html  = (string) wp_remote_retrieve_body($response);
        $found = self::extract_playlist_ids($html, $playlist_id);

        if ($found['ids'] === []) {
            return [
                'ok'    => false,
                'ids'   => [],
                'error' => 'در صفحه‌ی فهرست پخش شناسه‌ی ویدئویی پیدا نشد — ' . self::page_fingerprint($html),
            ];
        }

        return ['ok' => true, 'ids' => $found['ids'], 'strict' => $found['strict'], 'via' => $found['via']];
    }

    /**
     * Video hashes in playlist-page markup, most reliable form first.
     *
     * A page that renders on the server links each video with the playlist id
     * in its own URL (`/v/{hash}/list/{playlist_id}`); those matches are
     * exact. A page that renders in the browser has no links at all, but ships
     * its data as JSON in the markup, where videos appear as `"uid":"…"`. Both
     * are read, because which one arrives depends on what Aparat decides to
     * serve. `strict` reports whether the playlist id actually backed the
     * match, so a caller never presents a loose result as a certain one.
     *
     * @return array{ids:array<int,string>,strict:bool,via:string}
     */
    public static function extract_playlist_ids(string $html, string $playlist_id): array
    {
        // JSON embedded in the page escapes its slashes.
        $html = str_replace('\\/', '/', $html);

        $strict = self::match_ids(
            '~/v/([A-Za-z0-9_-]{4,24})[^"\'\s<>]*' . preg_quote($playlist_id, '~') . '~',
            $html
        );
        if ($strict !== []) {
            return ['ids' => $strict, 'strict' => true, 'via' => 'link+id'];
        }

        $embedded = self::match_ids('~"(?:uid|videohash|video_hash)"\s*:\s*"([A-Za-z0-9_-]{4,24})"~', $html);
        if ($embedded !== []) {
            return ['ids' => $embedded, 'strict' => false, 'via' => 'json'];
        }

        $links = self::match_ids('~/v/([A-Za-z0-9_-]{4,24})~', $html);

        return ['ids' => $links, 'strict' => false, 'via' => $links === [] ? '' : 'link'];
    }

    /**
     * What a page contained, when it contained no ids.
     *
     * "No videos found" is not a diagnosis. An empty shell, a JS challenge and
     * a redirect all look identical from the outside, and they need different
     * fixes — so report the evidence rather than the conclusion.
     */
    private static function page_fingerprint(string $html): string
    {
        $markers = [];
        foreach (['__NUXT__', '__NEXT_DATA__', 'aparat.com/v/', '"uid"', 'ld+json', 'captcha', 'cf-chl', 'noscript'] as $needle) {
            if (stripos($html, $needle) !== false) {
                $markers[] = $needle;
            }
        }

        $title = preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m) ? trim(wp_strip_all_tags($m[1])) : '';

        return sprintf(
            '%s بایت%s%s',
            number_format_i18n(strlen($html)),
            $title !== '' ? '، عنوان: «' . mb_substr($title, 0, 60) . '»' : '',
            $markers === [] ? '، بدون نشانه‌ی شناخته‌شده' : '، شامل: ' . implode(' ', $markers)
        );
    }

    /**
     * @return array<int,string>
     */
    private static function match_ids(string $pattern, string $html): array
    {
        if (! preg_match_all($pattern, $html, $matches)) {
            return [];
        }

        // Page order is playlist order, so dedupe must preserve it.
        return array_values(array_unique($matches[1]));
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
        $timeout = $this->budgeted_timeout($timeout ?? self::TIMEOUT);
        if ($timeout === null) {
            return ['ok' => false, 'body' => [], 'error' => 'مهلت این اجرا تمام شد؛ ادامه در اجرای بعدی.'];
        }

        $response = wp_remote_get($url, [
            'timeout'    => $timeout,
            'user-agent' => 'SignTeb-Video-Hub/' . STVH_VERSION . '; ' . home_url('/'),
            'headers'    => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'body' => [], 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            // A 400 names the parameter it wanted; a 405 usually does not. The
            // body is worth far more than the status on its own.
            $detail = trim(wp_strip_all_tags((string) wp_remote_retrieve_body($response)));

            return [
                'ok'    => false,
                'body'  => [],
                'error' => sprintf('آپارات کد %d برگرداند.', $code)
                    . ($detail !== '' ? ' پاسخ: ' . mb_substr(preg_replace('/\s+/u', ' ', $detail) ?? '', 0, 160) : ''),
            ];
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

<?php

namespace SignTeb\VideoHub\Api\Aparat;

use SignTeb\VideoHub\Api\VideoDto;
use SignTeb\VideoHub\Api\PlaylistAwareInterface;
use SignTeb\VideoHub\Api\VideoSourceInterface;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps Aparat payloads onto VideoDto. Field names differ between the v1 and
 * legacy endpoints, so every read goes through pick() with a candidate list.
 */
class AparatSource implements VideoSourceInterface, PlaylistAwareInterface
{
    public const ID = 'aparat';

    private Settings $settings;
    private AparatClient $client;

    public function __construct(?Settings $settings = null, ?AparatClient $client = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->client   = $client ?? new AparatClient();
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return __('آپارات', 'signteb-video-hub');
    }

    public function is_configured(): bool
    {
        return $this->username() !== '';
    }

    public function username(): string
    {
        return AparatClient::normalize_username($this->settings->str('aparat_username'));
    }

    public function fetch(int $limit): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'videos' => [], 'error' => 'شناسه کانال آپارات تنظیم نشده است.'];
        }

        // A pasted playlist URL is the most explicit thing an admin can say, so
        // it wins over the category dropdown. It can still fail — Aparat's
        // playlist route is discovered at runtime, not documented — and the
        // category filter below is the fallback that is already known to work.
        $playlist = $this->playlist_id();
        if ($playlist !== '') {
            $result = $this->fetch_playlist($playlist, $limit);
            if ($result['videos'] !== []) {
                return ['ok' => true, 'videos' => $result['videos']];
            }
            Logger::warning(
                'aparat',
                'فهرست پخش خوانده نشد؛ به فیلتر دسته برگشتیم. ' . $result['error'],
                ['playlist' => $playlist]
            );
        }

        $category = $this->selected_playlist();

        // Filtering happens over the channel list, so ask for more than the
        // limit — otherwise a category holding a handful of videos would be
        // starved by whatever happens to be newest.
        $fetch_limit = $category !== '' ? 100 : $limit;

        $raw = $this->client->videos_by_username($this->username(), $fetch_limit);
        if (! $raw['ok']) {
            return ['ok' => false, 'videos' => [], 'error' => $raw['error'] ?? 'خطای نامشخص آپارات.'];
        }

        $items = $raw['items'];
        if ($category !== '') {
            $matched = array_values(array_filter(
                $items,
                fn(array $item): bool => $this->category_key($item) === $category
            ));

            // An empty result means the category was renamed or emptied at the
            // source. Import the channel rather than silently nothing, and say
            // so in the log.
            if ($matched === []) {
                Logger::warning('aparat', 'دسته‌ی انتخاب‌شده ویدئویی نداشت؛ کل کانال وارد شد.', ['category' => $category]);
            } else {
                $items = $matched;
            }
        }

        return ['ok' => true, 'videos' => $this->to_dtos($items, $limit)];
    }

    /**
     * A playlist's videos, by whichever route works.
     *
     * The API route is tried first because it is cheapest when it works. The
     * page route is the one that does not depend on an undocumented endpoint:
     * it reads video ids from the playlist's public HTML and matches them
     * against the channel list, so every field still comes from the payload
     * the importer already parses.
     *
     * @return array{videos:array<int,VideoDto>,error:string,route:string}
     */
    private function fetch_playlist(string $playlist, int $limit, bool $force = false): array
    {
        $reasons = [];

        $list = $this->client->videos_by_playlist($playlist, $limit, $force);
        if ($list['ok']) {
            return ['videos' => $this->to_dtos($list['items'], $limit), 'error' => '', 'route' => 'api'];
        }
        $reasons[] = (string) ($list['error'] ?? '');

        $page = $this->client->playlist_video_ids($playlist);
        if (! $page['ok']) {
            $reasons[] = (string) ($page['error'] ?? '');
            return ['videos' => [], 'error' => implode(' | ', array_filter($reasons)), 'route' => ''];
        }

        $channel = $this->client->videos_by_username($this->username(), 100);
        if (! $channel['ok']) {
            $reasons[] = (string) ($channel['error'] ?? '');
            return ['videos' => [], 'error' => implode(' | ', array_filter($reasons)), 'route' => ''];
        }

        $matched = $this->order_by_ids($channel['items'], $page['ids']);
        if ($matched === []) {
            $reasons[] = 'ویدئوهای فهرست پخش در ۱۰۰ ویدئوی اخیر کانال نبودند.';
            return ['videos' => [], 'error' => implode(' | ', array_filter($reasons)), 'route' => ''];
        }

        return [
            'videos' => $this->to_dtos($matched, $limit),
            'error'  => '',
            'route'  => ($page['strict'] ?? false) ? 'page' : 'page-loose',
        ];
    }

    /**
     * Channel records whose id appears in the playlist, in the playlist's own
     * order — page order is playlist order, and it is rarely upload order.
     *
     * @param array<int,array<string,mixed>> $items
     * @param array<int,string>              $ids
     * @return array<int,array<string,mixed>>
     */
    private function order_by_ids(array $items, array $ids): array
    {
        $by_id = [];
        foreach ($items as $item) {
            $uid = (string) $this->pick($item, ['uid', 'hash', 'videohash', 'id']);
            if ($uid !== '') {
                $by_id[$uid] = $item;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($by_id[$id])) {
                $ordered[] = $by_id[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,VideoDto>
     */
    private function to_dtos(array $items, int $limit): array
    {
        $videos = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $dto = $this->to_dto($item);
            if ($dto !== null && $dto->is_valid()) {
                $videos[] = $dto;
            }
        }
        return $videos;
    }

    /** The chosen category key, or '' for the whole channel. */
    public function selected_playlist(): string
    {
        return trim((string) $this->settings->get('aparat_playlist', ''));
    }

    /** The numeric id from the pasted playlist URL, or '' when unset/invalid. */
    public function playlist_id(): string
    {
        return AparatClient::normalize_playlist((string) $this->settings->get('aparat_playlist_url', ''));
    }

    /**
     * Probes the playlist route so an admin learns whether the pasted URL
     * works before a sync silently falls back to the category filter.
     *
     * @return array{ok:bool,message:string}
     */
    public function test_playlist(): array
    {
        $raw = trim((string) $this->settings->get('aparat_playlist_url', ''));
        if ($raw === '') {
            return ['ok' => false, 'message' => 'آدرس فهرست پخش وارد نشده است.'];
        }

        $id = $this->playlist_id();
        if ($id === '') {
            return [
                'ok'      => false,
                'message' => 'از این آدرس شناسه‌ای پیدا نشد. نمونه درست: https://www.aparat.com/playlist/1234567',
            ];
        }

        // An explicit test is the one moment worth re-probing the API route,
        // even if a previous pass wrote it off.
        $result = $this->fetch_playlist($id, 100, true);

        if ($result['videos'] === []) {
            return [
                'ok'      => false,
                'message' => ($result['error'] !== '' ? $result['error'] : 'خواندن فهرست پخش ناموفق بود.')
                    . ' — همگام‌سازی به فیلتر دسته برمی‌گردد.',
            ];
        }

        $count = count($result['videos']);

        return [
            'ok'      => true,
            'message' => match ($result['route']) {
                'api'   => sprintf('فهرست پخش %s از API خوانده شد — %d ویدئو.', $id, $count),
                'page'  => sprintf('فهرست پخش %s از صفحه‌ی آپارات خوانده شد — %d ویدئو.', $id, $count),
                default => sprintf(
                    'فهرست پخش %s خوانده شد — %d ویدئو. توجه: لینک‌های صفحه شناسه‌ی فهرست را نداشتند،'
                        . ' پس ممکن است ویدئوهای پیشنهادی صفحه هم شمرده شده باشند؛ اگر این عدد از فهرست شما بیشتر است بگویید.',
                    $id,
                    $count
                ),
            },
        ];
    }

    /**
     * Categories present on the channel.
     *
     * Derived from the channel's own video list rather than a dedicated
     * endpoint. Aparat's playlist routes could not be verified — the two
     * documented-looking paths both answered 405 — and an unverifiable guess
     * that fails at runtime is worse than none. This reads the exact payload
     * the importer already parses successfully, so if videos import, the
     * category list works too.
     *
     * @return array{ok:bool,items:array<int,array{id:string,title:string,count:int}>,error?:string}
     */
    public function playlists(): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'items' => [], 'error' => 'شناسه کانال آپارات تنظیم نشده است.'];
        }

        $raw = $this->client->videos_by_username($this->username(), 100);
        if (! $raw['ok']) {
            return ['ok' => false, 'items' => [], 'error' => $raw['error'] ?? 'دریافت ویدئوهای کانال ناموفق بود.'];
        }

        $groups = [];
        foreach ($raw['items'] as $item) {
            $key = $this->category_key($item);
            if ($key === '') {
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = ['id' => $key, 'title' => $this->category_title($item, $key), 'count' => 0];
            }
            $groups[$key]['count']++;
        }

        if ($groups === []) {
            return [
                'ok'    => false,
                'items' => [],
                'error' => 'آپارات برای ویدئوهای این کانال دسته‌بندی برنگرداند.',
            ];
        }

        // Biggest first: the category an admin wants is usually the main one.
        usort($groups, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['ok' => true, 'items' => array_values($groups)];
    }

    /**
     * A stable key for a video's category. Aparat has used several field
     * names across API revisions, so each candidate is tried in turn and the
     * first tag is the last resort.
     *
     * @param array<string,mixed> $item
     */
    private function category_key(array $item): string
    {
        $id = (string) $this->pick($item, ['cat_id', 'catId', 'category_id', 'cat']);
        if ($id !== '') {
            return 'cat:' . $id;
        }

        $name = (string) $this->pick($item, ['cat_name', 'catName', 'category', 'category_name']);
        if ($name !== '') {
            return 'cat:' . sanitize_title($name);
        }

        $tags = $this->tags($item);

        return $tags === [] ? '' : 'tag:' . sanitize_title($tags[0]);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function category_title(array $item, string $key): string
    {
        $name = trim((string) $this->pick($item, ['cat_name', 'catName', 'category', 'category_name']));
        if ($name !== '') {
            return $name;
        }

        if (str_starts_with($key, 'tag:')) {
            $tags = $this->tags($item);
            if ($tags !== []) {
                return $tags[0];
            }
        }

        return $key;
    }

    public function test_connection(): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'message' => 'شناسه کانال آپارات تنظیم نشده است.'];
        }
        return $this->client->test_channel($this->username());
    }

    /**
     * @param array<string,mixed> $item
     */
    private function to_dto(array $item): ?VideoDto
    {
        $uid = (string) $this->pick($item, ['uid', 'hash', 'videohash', 'id']);
        if ($uid === '') {
            return null;
        }

        return VideoDto::from_array(self::ID, [
            'source_id'    => $uid,
            'title'        => $this->pick($item, ['title', 'name']),
            'description'  => $this->pick($item, ['description', 'descr', 'summary']),
            'thumbnail'    => $this->pick($item, ['big_poster', 'poster', 'preview_src', 'small_poster']),
            'embed_url'    => $this->embed_url($item, $uid),
            'source_url'   => $this->watch_url($item, $uid),
            'duration'     => $this->pick($item, ['duration', 'time', 'length']),
            'published_at' => $this->pick($item, ['create_date', 'sdate_rss', 'sdate_timediff', 'sdate', 'date']),
            'views'        => (int) $this->pick($item, ['visit_cnt_int', 'visit_cnt', 'visit']),
            'tags'         => $this->tags($item),
        ]);
    }

    /**
     * Aparat sometimes hands back a full <iframe> in `frame`; we only ever want
     * the src, because the markup carries its own sizing we cannot control.
     *
     * @param array<string,mixed> $item
     */
    private function embed_url(array $item, string $uid): string
    {
        $frame = (string) $this->pick($item, ['frame', 'embed', 'embed_url']);
        if ($frame !== '' && preg_match('/src=["\']([^"\']+)["\']/i', $frame, $m)) {
            return $m[1];
        }
        if (str_starts_with($frame, 'http')) {
            return $frame;
        }
        return sprintf('https://www.aparat.com/video/video/embed/videohash/%s/vt/frame', rawurlencode($uid));
    }

    /**
     * @param array<string,mixed> $item
     */
    private function watch_url(array $item, string $uid): string
    {
        $url = (string) $this->pick($item, ['url', 'link', 'permalink']);
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        return 'https://www.aparat.com/v/' . rawurlencode($uid);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<int,string>
     */
    private function tags(array $item): array
    {
        $tags = $item['tags'] ?? $item['tag'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[,،]/', $tags) ?: [];
        }
        if (! is_array($tags)) {
            return [];
        }

        $out = [];
        foreach ($tags as $tag) {
            // v1 returns objects like {name: "کبد"}; legacy returns strings.
            $value = is_array($tag) ? ($tag['name'] ?? $tag['title'] ?? '') : $tag;
            $value = trim((string) $value);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * First non-empty value among the candidate keys.
     *
     * @param array<string,mixed> $item
     * @param array<int,string>   $keys
     */
    private function pick(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && $item[$key] !== '' && $item[$key] !== null) {
                return $item[$key];
            }
        }
        return '';
    }
}

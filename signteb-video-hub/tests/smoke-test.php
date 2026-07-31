<?php
/**
 * Dependency-free smoke tests for the pure-logic layer.
 *
 * These cover the parts that are easy to get wrong and expensive to debug in
 * production: duration parsing across three provider formats, JSON extraction
 * from a chatty model response, and the sync change-detection hash.
 *
 * Run with: php tests/smoke-test.php
 *
 * @package SignTeb\VideoHub
 */

define('ABSPATH', __DIR__);
define('STVH_DIR', dirname(__DIR__) . '/');
define('STVH_VERSION', '1.0.0');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);

// Minimal WordPress surface used by the classes under test.
function wp_strip_all_tags($text)
{
    return strip_tags((string) $text);
}

function esc_url_raw($url)
{
    return $url;
}

function wp_json_encode($value, $flags = 0)
{
    return json_encode($value, $flags);
}

function apply_filters($tag, $value)
{
    return $value;
}

function wp_parse_url($url, $component = -1)
{
    return parse_url($url, $component);
}

// Mirrors the part of WordPress' sanitize_title() the slug builder relies on.
function current_time($type = 'mysql')
{
    return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s');
}

function update_option($key, $value, $autoload = null)
{
    return true;
}

function get_option($key, $default = false)
{
    return $GLOBALS['stvh_test_options'] ?? $default;
}

function sanitize_title($title)
{
    $title = mb_strtolower(trim((string) $title));
    $title = preg_replace('/\s+/u', '-', $title);

    return trim((string) $title, '-');
}

require STVH_DIR . 'includes/Core/Autoloader.php';
\SignTeb\VideoHub\Core\Autoloader::register();

use SignTeb\VideoHub\Api\Aparat\AparatClient;
use SignTeb\VideoHub\Api\Aparat\AparatSource;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Api\VideoDto;
use SignTeb\VideoHub\Helpers\Format;
use SignTeb\VideoHub\Helpers\Json;

$failures = 0;
$total    = 0;

$check = static function (string $label, $actual, $expected) use (&$failures, &$total): void {
    $total++;
    if ($actual === $expected) {
        printf("  ok   %s\n", $label);
        return;
    }
    $failures++;
    printf(
        "  FAIL %s\n       got:      %s\n       expected: %s\n",
        $label,
        var_export($actual, true),
        var_export($expected, true)
    );
};

echo "Format\n";
$check('duration 754 → 12:34', Format::duration(754), '12:34');
$check('duration 3723 → 1:02:03', Format::duration(3723), '1:02:03');
$check('duration 0 → empty', Format::duration(0), '');
$check('iso 3723', Format::iso8601_duration(3723), 'PT1H2M3S');
$check('iso 45', Format::iso8601_duration(45), 'PT45S');
$check('parse raw seconds', Format::parse_duration(90), 90);
$check('parse clock notation', Format::parse_duration('1:02:03'), 3723);
$check('parse iso-8601', Format::parse_duration('PT2M30S'), 150);
$check('parse garbage', Format::parse_duration('نامشخص'), 0);
$check('views 12500', Format::views(12500), '12.5K');
$check('views 900', Format::views(900), '900');
$check('rate guards divide-by-zero', Format::rate(5, 0), 0.0);
$check('rate 5/20', Format::rate(5, 20), 25.0);

echo "\nJson\n";
$check('extract from code fence', Json::extract("بله:\n```json\n{\"a\":1}\n```"), ['a' => 1]);
$check(
    'extract from prose',
    Json::extract('حتماً! {"links":[{"url":"/x","anchor":"y"}]} تمام'),
    ['links' => [['url' => '/x', 'anchor' => 'y']]]
);
$check('extract from non-json', Json::extract('هیچ JSON ندارد'), []);
$check('dig nested', Json::dig(['a' => ['b' => ['c' => 7]]], 'a.b.c'), 7);
$check('dig missing returns default', Json::dig(['a' => 1], 'x.y', 'def'), 'def');

echo "\nAparatClient\n";
$check('username plain', AparatClient::normalize_username('mychannel'), 'mychannel');
$check('username with @', AparatClient::normalize_username('@mychannel'), 'mychannel');
$check('username from url', AparatClient::normalize_username('https://www.aparat.com/mychannel'), 'mychannel');
$check('playlist id from url', AparatClient::normalize_playlist('https://www.aparat.com/playlist/1234567'), '1234567');
$check('playlist id from url with query', AparatClient::normalize_playlist('https://www.aparat.com/playlist/1234567?x=1'), '1234567');
$check('bare playlist id', AparatClient::normalize_playlist('1234567'), '1234567');
$check('a channel url is not a playlist', AparatClient::normalize_playlist('https://www.aparat.com/mychannel'), '');
$check('empty input', AparatClient::normalize_playlist('  '), '');

echo "\nAparat playlist payloads — two routes answered 200 with JSON the first\n";
echo "  parser did not recognise, so extraction now searches the whole tree.\n";

$stvh_flat = ['playlist' => ['videos' => [
    ['uid' => 'aaa', 'title' => 'یک'],
    ['uid' => 'bbb', 'title' => 'دو'],
]]];
$check('finds videos nested two levels down', count(AparatClient::extract_playlist_items($stvh_flat)), 2);

$stvh_jsonapi = ['data' => [
    'type'       => 'Playlist',
    'id'         => '1058203',
    'attributes' => ['title' => 'فهرست من'],
    'videos'     => [
        ['type' => 'Video', 'id' => '1', 'attributes' => ['uid' => 'ccc', 'title' => 'سه']],
    ],
]];
$stvh_found = AparatClient::extract_playlist_items($stvh_jsonapi);
$check('unwraps JSON:API attributes', count($stvh_found), 1);
$check('and returns the inner fields', $stvh_found[0]['uid'], 'ccc');

$stvh_meta_only = ['playlist' => ['id' => '1058203', 'title' => 'فهرست من', 'cnt' => '12']];
$check('playlist metadata is not mistaken for a video', AparatClient::extract_playlist_items($stvh_meta_only), []);

$stvh_owner = [
    'playlist' => ['id' => '1058203', 'title' => 'فهرست'],
    'user'     => ['id' => '99', 'name' => 'کانال'],
    'list'     => [['id' => '7', 'title' => 'چهار', 'big_poster' => 'https://x/y.jpg']],
];
$stvh_found = AparatClient::extract_playlist_items($stvh_owner);
$check('a bare id needs a video-only field to count', count($stvh_found), 1);
$check('and it is the video, not the playlist or the owner', $stvh_found[0]['title'], 'چهار');

$check('an unrecognisable body yields nothing', AparatClient::extract_playlist_items(['ui' => ['theme' => 'dark']]), []);

$check(
    'the shape report names the keys that came back',
    AparatClient::describe_shape(['playlist' => ['id' => 1, 'title' => 'x'], 'ui' => ['theme' => 'dark']]),
    'playlist{id,title},ui{theme}'
);

echo "\nPlaylist page scraping — the API answers `login{type,value}`, its own\n";
echo "  error envelope, so ids come from the page instead.\n";

$stvh_html = '<a href="/v/aaa111/list/1058203">یک</a>'
    . '<a href="/v/bbb222/list/1058203">دو</a>'
    . '<a href="/v/zzz999">ویدئوی پیشنهادی</a>';
$stvh_ids = AparatClient::extract_playlist_ids($stvh_html, '1058203');
$check('links carrying the playlist id are preferred', $stvh_ids['ids'], ['aaa111', 'bbb222']);
$check('and that is reported as the exact match', $stvh_ids['strict'], true);

$stvh_loose = AparatClient::extract_playlist_ids('<a href="/v/ccc333">سه</a><a href="/v/ccc333">تکراری</a>', '1058203');
$check('without the id, every video link is taken', $stvh_loose['ids'], ['ccc333']);
$check('and the caller is told the match was loose', $stvh_loose['strict'], false);

$stvh_escaped = AparatClient::extract_playlist_ids('{"url":"https:\\/\\/www.aparat.com\\/v\\/ddd444\\/list\\/1058203"}', '1058203');
$check('slashes escaped inside embedded JSON still match', $stvh_escaped['ids'], ['ddd444']);

$check('a page with no video links yields nothing', AparatClient::extract_playlist_ids('<html></html>', '1')['ids'], []);

echo "\nFormat::slug — the real titles that produced unusable slugs\n";
$check(
    'drops the Persian question mark and the author suffix',
    Format::slug('درمان ریفلاکس معده چیست؟ | دکتر محمد طالب‌پور'),
    'درمان-ریفلاکس-معده-چیست'
);
$check(
    'drops parenthesised qualifiers',
    Format::slug('جراحی اسلیو معده چیست؟ | دکتر محمدطالب‌پور (فوق تخصص لاپاروسکوپی)'),
    'جراحی-اسلیو-معده-چیست'
);
$check(
    'strips colons and exclamation marks',
    Format::slug('فیبرواسکن کبد: هرآنچه باید بدانید!'),
    'فیبرواسکن-کبد-هرآنچه-باید-بدانید'
);
$check(
    'leaves an already-clean title alone',
    Format::slug('بهترین فوق تخصص گوارش تهران'),
    'بهترین-فوق-تخصص-گوارش-تهران'
);
$check('trims at a word boundary', Format::slug('یک دو سه چهار پنج شش هفت هشت نه ده', 20), 'یک-دو-سه-چهار-پنج');
$check('empty title yields empty slug', Format::slug('!!! ???'), '');

echo "\nAparat category filtering — the 405 from the playlist API forced this\n";
echo "  onto the channel list, which is the payload the importer already reads.\n";

/** A client stub returning a fixed payload, so no network is involved. */
final class StvhStubAparatClient extends AparatClient
{
    /** @var array<int,array<string,mixed>> */
    public array $items = [];

    /** @var array<int,array<string,mixed>>|null null means the playlist route failed. */
    public ?array $playlist_items = null;

    public string $asked_playlist = '';

    public function videos_by_username(string $username, int $limit): array
    {
        return ['ok' => true, 'items' => $this->items];
    }

    /** @var array<int,string>|null null means the playlist page could not be read. */
    public ?array $page_ids = null;

    public bool $page_strict = true;

    public function videos_by_playlist(string $playlist_id, int $limit, bool $force = false): array
    {
        $this->asked_playlist = $playlist_id;

        if ($this->playlist_items === null) {
            return ['ok' => false, 'items' => [], 'error' => 'آپارات کد 405 برگرداند.'];
        }

        return ['ok' => true, 'items' => array_slice($this->playlist_items, 0, $limit)];
    }

    public function playlist_video_ids(string $playlist_id): array
    {
        if ($this->page_ids === null) {
            return ['ok' => false, 'ids' => [], 'error' => 'صفحه‌ی فهرست پخش خوانده نشد.'];
        }

        return ['ok' => true, 'ids' => $this->page_ids, 'strict' => $this->page_strict];
    }
}

$stvh_items = [
    ['uid' => 'a', 'title' => 'سلامت ۱', 'cat_id' => '12', 'cat_name' => 'سلامت'],
    ['uid' => 'b', 'title' => 'آموزش ۱', 'cat_id' => '30', 'cat_name' => 'آموزش'],
    ['uid' => 'c', 'title' => 'سلامت ۲', 'cat_id' => '12', 'cat_name' => 'سلامت'],
];

$stvh_source = static function (string $category, array $items): AparatSource {
    $GLOBALS['stvh_test_options'] = ['aparat_username' => 'x', 'aparat_playlist' => $category];
    $client        = new StvhStubAparatClient();
    $client->items = $items;

    return new AparatSource(new Settings(), $client);
};

$check('no filter imports the whole channel', count($stvh_source('', $stvh_items)->fetch(10)['videos']), 3);
$check('a category imports only its own videos', count($stvh_source('cat:12', $stvh_items)->fetch(10)['videos']), 2);
$check('and the correct ones', $stvh_source('cat:12', $stvh_items)->fetch(10)['videos'][0]->title, 'سلامت ۱');
$check('a stale category falls back to the channel', count($stvh_source('cat:999', $stvh_items)->fetch(10)['videos']), 3);
$check('the sync limit still applies after filtering', count($stvh_source('cat:12', $stvh_items)->fetch(1)['videos']), 1);

$stvh_groups = $stvh_source('', $stvh_items)->playlists();
$check('categories are derived from the video list', $stvh_groups['ok'], true);
$check('one entry per distinct category', count($stvh_groups['items']), 2);
$check('largest category first', $stvh_groups['items'][0]['title'], 'سلامت');

$stvh_tagged = $stvh_source('', [['uid' => 'a', 'title' => 'v', 'tags' => ['کبد']]])->playlists();
$check('falls back to the first tag when no category field exists', $stvh_tagged['items'][0]['title'], 'کبد');

$stvh_bare = $stvh_source('', [['uid' => 'a', 'title' => 'v']])->playlists();
$check('says so plainly when nothing groups the videos', $stvh_bare['ok'], false);

echo "\nAparat playlist URL — takes precedence, but must fall back safely\n";

$stvh_playlist_source = static function (
    string $url,
    string $category,
    ?array $playlist_items,
    ?array $page_ids = null,
    bool $strict = true
) use ($stvh_items): array {
    $GLOBALS['stvh_test_options'] = [
        'aparat_username'     => 'x',
        'aparat_playlist'     => $category,
        'aparat_playlist_url' => $url,
    ];
    $client                 = new StvhStubAparatClient();
    $client->items          = $stvh_items;
    $client->playlist_items = $playlist_items;
    $client->page_ids       = $page_ids;
    $client->page_strict    = $strict;

    return [new AparatSource(new Settings(), $client), $client];
};

$stvh_list = [
    ['uid' => 'p1', 'title' => 'فهرست ۱'],
    ['uid' => 'p2', 'title' => 'فهرست ۲'],
];

[$stvh_src, $stvh_client] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', 'cat:12', $stvh_list);
$stvh_fetched = $stvh_src->fetch(10);
$check('a working playlist wins over the category', count($stvh_fetched['videos']), 2);
$check('and its videos are the ones imported', $stvh_fetched['videos'][0]->title, 'فهرست ۱');
$check('the id is extracted before the request', $stvh_client->asked_playlist, '1234567');
$check('the sync limit applies to playlist results too', count($stvh_src->fetch(1)['videos']), 1);

[$stvh_src] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', 'cat:12', null);
$check('a 405 falls back to the category filter', count($stvh_src->fetch(10)['videos']), 2);

[$stvh_src] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', '', null);
$check('…and to the whole channel when no category is set', count($stvh_src->fetch(10)['videos']), 3);

[$stvh_src, $stvh_client] = $stvh_playlist_source('https://www.aparat.com/mychannel', 'cat:12', $stvh_list);
$check('a non-playlist url is ignored, not requested', $stvh_client->asked_playlist, '');
$check('so the category filter still runs', count($stvh_src->fetch(10)['videos']), 2);

echo "\nThe page route — when the API fails, ids from the page are matched\n";
echo "  against the channel list, which already returns full metadata.\n";

[$stvh_src] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', 'cat:12', null, ['c', 'a']);
$stvh_fetched = $stvh_src->fetch(10);
$check('the page route rescues a failed API probe', count($stvh_fetched['videos']), 2);
$check('playlist order wins over channel order', $stvh_fetched['videos'][0]->title, 'سلامت ۲');
$check('and the second is the other one', $stvh_fetched['videos'][1]->title, 'سلامت ۱');

[$stvh_src] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', 'cat:12', null, ['a', 'unknown']);
$check('ids missing from the channel are skipped, not faked', count($stvh_src->fetch(10)['videos']), 1);

[$stvh_src] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', 'cat:12', null, ['zzz']);
$check('no overlap at all falls back to the category', count($stvh_src->fetch(10)['videos']), 2);

[$stvh_src] = $stvh_playlist_source('https://www.aparat.com/playlist/1234567', 'cat:12', null, ['a', 'b', 'c']);
$check('the sync limit applies to page results too', count($stvh_src->fetch(2)['videos']), 2);

echo "\nVideoDto\n";
$dto = VideoDto::from_array('aparat', [
    'source_id'    => 'abc',
    'title'        => ' <b>کبد چرب</b> ',
    'duration'     => '12:34',
    'published_at' => 1700000000,
]);
$check('valid record', $dto->is_valid(), true);
$check('title is stripped and trimmed', $dto->title, 'کبد چرب');
$check('duration normalised', $dto->duration, 754);

$same = VideoDto::from_array('aparat', [
    'source_id'    => 'abc',
    'title'        => 'کبد چرب',
    'duration'     => 754,
    'published_at' => 1700000000,
]);
$check('hash is stable across equivalent payloads', $dto->hash(), $same->hash());

$changed = VideoDto::from_array('aparat', [
    'source_id'    => 'abc',
    'title'        => 'کبد چرب — بخش دوم',
    'duration'     => 754,
    'published_at' => 1700000000,
]);
$check('hash changes when the title changes', $dto->hash() !== $changed->hash(), true);
$check('record without an id is invalid', VideoDto::from_array('aparat', ['title' => 'x'])->is_valid(), false);

printf("\n%d assertions, %d failures\n", $total, $failures);

exit($failures === 0 ? 0 : 1);

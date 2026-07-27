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

require STVH_DIR . 'includes/Core/Autoloader.php';
\SignTeb\VideoHub\Core\Autoloader::register();

use SignTeb\VideoHub\Api\Aparat\AparatClient;
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
$check('username plain', AparatClient::normalize_username('drhamedzamani'), 'drhamedzamani');
$check('username with @', AparatClient::normalize_username('@drhamedzamani'), 'drhamedzamani');
$check('username from url', AparatClient::normalize_username('https://www.aparat.com/drhamedzamani'), 'drhamedzamani');

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

#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apply a reviewer's corrections to a locale's .po.
 *
 * The review sheet exports `['English source' => 'ترجمهٔ اصلاح‌شده', ...]`. This
 * writes those into the `.po` and nothing else.
 *
 * Only the `.po`, deliberately. `merge-po.php` already prefers an existing `.po`
 * translation over the map, precisely so a reviewer's work survives a re-merge —
 * so the `.po` is where corrected text belongs, and the map stays what its
 * header says it is: the unreviewed draft it started as. Writing to both would
 * mean two authorities for one string, and a hand-formatted PHP file rewritten
 * by a script is how alignment and comments quietly get destroyed.
 *
 * Corrections are checked before anything is written, and a single bad entry
 * stops the whole run: a half-applied review is worse than an unapplied one,
 * because nobody can tell which half.
 *
 * Usage:
 *   php bin/apply-review.php fa_IR languages/medora-authority-fa_IR.review.php
 *   php bin/apply-review.php fa_IR <file> --dry-run
 */

const DOMAIN = 'medora-authority';

$root   = dirname(__DIR__);
$locale = $argv[1] ?? '';
$file   = $argv[2] ?? '';
$dryRun = in_array('--dry-run', $argv, true);

if ($locale === '' || $file === '') {
    fwrite(STDERR, "Usage: php bin/apply-review.php <locale> <corrections.php> [--dry-run]\n");
    exit(1);
}

$poPath = $root . '/languages/' . DOMAIN . '-' . $locale . '.po';

if (! is_readable($poPath)) {
    fwrite(STDERR, "No catalogue at {$poPath}\n");
    exit(1);
}

if (! is_readable($file)) {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}

/** @var array<string, string> $corrections */
$corrections = (array) require realpath($file);

if ($corrections === []) {
    print("Nothing to apply.\n");
    exit(0);
}

$po      = (string) file_get_contents($poPath);
$blocks  = preg_split('/\n\n+/', trim($po)) ?: [];
$header  = array_shift($blocks);
$byId    = [];

foreach ($blocks as $i => $block) {
    $id = msgidOf($block);

    if ($id !== null) {
        $byId[$id] = $i;
    }
}

// --- check everything before writing anything -------------------------------

$problems = [];
$applied  = [];

foreach ($corrections as $source => $corrected) {
    if (! isset($byId[$source])) {
        $problems[] = sprintf('not in the catalogue: "%s"', clip($source));
        continue;
    }

    if (trim($corrected) === '') {
        $problems[] = sprintf('empty correction for "%s"', clip($source));
        continue;
    }

    // Each plural form is a translation of the same source and carries the
    // same placeholders, so they are compared one at a time. Checking the
    // NUL-joined whole counts every placeholder once per form and rejects a
    // perfectly good correction.
    $expected = placeholders($source);
    $mismatch = null;

    foreach (explode("\x00", $corrected) as $form) {
        if (placeholders($form) !== $expected) {
            $mismatch = placeholders($form);
            break;
        }
    }

    if ($mismatch !== null) {
        $problems[] = sprintf(
            'placeholders changed on "%s" — expects %s, correction has %s',
            clip($source),
            $expected === [] ? 'none' : implode(' ', $expected),
            $mismatch === [] ? 'none' : implode(' ', $mismatch)
        );
        continue;
    }

    $block   = $blocks[$byId[$source]];
    $current = msgstrOf($block);

    if ($current === $corrected) {
        continue;
    }

    $applied[$source] = [$current, $corrected, $byId[$source]];
}

if ($problems !== []) {
    fwrite(STDERR, "Refusing to apply — " . count($problems) . " problem(s):\n");

    foreach ($problems as $problem) {
        fwrite(STDERR, "  - {$problem}\n");
    }

    exit(1);
}

if ($applied === []) {
    printf("All %d corrections already match the catalogue. Nothing to do.\n", count($corrections));
    exit(0);
}

// --- write -------------------------------------------------------------------

foreach ($applied as [, $corrected, $index]) {
    $blocks[$index] = replaceMsgstr($blocks[$index], $corrected);
}

printf("%d correction(s)%s:\n\n", count($applied), $dryRun ? ' (dry run)' : '');

foreach ($applied as $source => [$before, $after]) {
    printf("  %s\n    − %s\n    + %s\n\n", clip($source), clip($before), clip($after));
}

if ($dryRun) {
    print("Nothing written. Drop --dry-run to apply.\n");
    exit(0);
}

file_put_contents($poPath, $header . "\n\n" . implode("\n\n", $blocks) . "\n");

printf("Wrote %s\n\nNow recompile:\n  composer i18n:build\n", str_replace($root . '/', '', $poPath));

exit(0);

// ---------------------------------------------------------------------------

function msgidOf(string $block): ?string
{
    return fieldOf($block, 'msgid');
}

function msgstrOf(string $block): ?string
{
    // Plural forms are NUL-joined, matching how the review sheet shows them.
    if (preg_match_all('/^msgstr\[\d+\] (".*")$/m', $block, $m) >= 1 && $m[1] !== []) {
        return implode("\x00", array_map('unquote', $m[1]));
    }

    return fieldOf($block, 'msgstr');
}

/**
 * The value of a field, including any continuation lines.
 */
function fieldOf(string $block, string $field): ?string
{
    if (preg_match('/^' . $field . ' (".*")((?:\n".*")*)$/m', $block, $m) !== 1) {
        return null;
    }

    $value = unquote($m[1]);

    foreach (explode("\n", trim($m[2])) as $line) {
        if (trim($line) !== '') {
            $value .= unquote($line);
        }
    }

    return $value;
}

function replaceMsgstr(string $block, string $translation): string
{
    if (str_contains($block, 'msgstr[')) {
        $forms = explode("\x00", $translation);
        $block = (string) preg_replace('/^msgstr\[\d+\] ".*"\n?/m', '', $block);
        $block = rtrim($block) . "\n";

        foreach ($forms as $i => $form) {
            $block .= sprintf('msgstr[%d] %s', $i, quote($form)) . "\n";
        }

        return rtrim($block);
    }

    // Drop any continuation lines with the msgstr they belong to, or the old
    // tail survives underneath the new value and gettext concatenates both.
    $block = (string) preg_replace('/^msgstr ".*"(?:\n".*")*$/m', 'msgstr ' . quote($translation), $block);

    return rtrim($block);
}

/**
 * @return list<string>
 */
function placeholders(string $text): array
{
    preg_match_all('/%(?:\d+\$)?[sdf]|%%/', $text, $m);

    $found = $m[0];
    sort($found);

    return $found;
}

function unquote(string $quoted): string
{
    return stripcslashes(substr(trim($quoted), 1, -1));
}

function quote(string $value): string
{
    return '"' . str_replace(['\\', '"', "\n", "\t"], ['\\\\', '\\"', '\\n', '\\t'], $value) . '"';
}

function clip(string $value): string
{
    $value = str_replace("\x00", ' / ', $value);

    return mb_strimwidth($value, 0, 72, '…');
}

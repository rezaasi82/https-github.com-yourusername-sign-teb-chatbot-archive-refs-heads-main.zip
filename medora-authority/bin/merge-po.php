#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Merge a translation map into a .po file, keeping the template's structure.
 *
 * Regenerating the POT after a code change leaves the existing .po behind. The
 * usual answer is `msgmerge`, which means gettext tooling; this does the same
 * job with the PHP that is already here.
 *
 * The template is the source of truth for which strings exist, their comments
 * and their source references. Existing translations are carried across by
 * msgid, and anything the template no longer contains is dropped — a string
 * that no longer exists in the code cannot be shown, and keeping it only makes
 * the file look more finished than it is.
 *
 * Untranslated entries are written empty on purpose. gettext falls back per
 * string, so a partial catalogue is a working catalogue: translated strings
 * appear in Persian, the rest stay English. That is far better than a file
 * padded with guesses.
 *
 * Usage:
 *   php bin/merge-po.php fa_IR [path/to/map.php]
 *
 * The map returns `['English source' => 'ترجمه', ...]`. An entry mapping to an
 * empty string is treated as deliberately untranslated.
 */

const DOMAIN = 'medora-authority';

/**
 * Plural rules by language. Persian is not the English default.
 *
 * Arabic is deliberately absent: it is out of scope for this product, and a
 * rule sitting here would suggest a locale someone is expected to fill in.
 * Adding it later is one line — and its six forms are why it needs the entry
 * rather than the fallback.
 */
const PLURALS = [
    'fa' => 'nplurals=2; plural=(n > 1);',
];

$root   = dirname(__DIR__);
$locale = $argv[1] ?? '';

if ($locale === '') {
    fwrite(STDERR, "Usage: php bin/merge-po.php <locale> [map.php]\n");
    exit(1);
}

$mapPath = $argv[2] ?? $root . '/languages/' . DOMAIN . '-' . $locale . '.map.php';
$potPath = $root . '/languages/' . DOMAIN . '.pot';
$poPath  = $root . '/languages/' . DOMAIN . '-' . $locale . '.po';

if (! is_readable($potPath)) {
    fwrite(STDERR, "No template. Run: php bin/make-pot.php\n");
    exit(1);
}

/** @var array<string, string> $map */
$map = is_readable($mapPath) ? (array) require $mapPath : [];

// Anything already translated in the .po wins over the map, so a reviewer's
// edits to the .po survive a re-merge rather than being overwritten by the
// draft they were correcting.
$existing = is_readable($poPath) ? existingTranslations((string) file_get_contents($poPath)) : [];

$template = (string) file_get_contents($potPath);
$blocks   = preg_split('/\n\n+/', trim($template)) ?: [];
$header   = array_shift($blocks);

$out         = poHeader($header, $locale);
$translated  = 0;
$total       = 0;

foreach ($blocks as $block) {
    $msgid = msgidOf($block);

    if ($msgid === null) {
        continue;
    }

    $total++;

    $translation = $existing[$msgid] ?? $map[$msgid] ?? '';

    if ($translation !== '') {
        $translated++;
    }

    $out .= "\n" . applyTranslation($block, $translation) . "\n";
}

file_put_contents($poPath, $out);

printf(
    "%s: %d of %d translated (%d%%) → %s\n",
    $locale,
    $translated,
    $total,
    $total > 0 ? (int) round($translated / $total * 100) : 0,
    str_replace($root . '/', '', $poPath)
);

$unused = array_diff_key($map, array_flip(array_map('msgidOf', $blocks)));

if ($unused !== []) {
    printf("  %d map entries match no template string (stale after a code change):\n", count($unused));

    foreach (array_slice(array_keys($unused), 0, 10) as $stale) {
        printf("    - %s\n", $stale);
    }
}

exit(0);

// ---------------------------------------------------------------------------

function msgidOf(string $block): ?string
{
    if (preg_match('/^msgid (".*")$/m', $block, $m) !== 1) {
        return null;
    }

    $value = unquote($m[1]);

    // Continuation lines directly after msgid belong to it.
    if (preg_match('/^msgid ""\n((?:".*"\n?)+)/m', $block, $m) === 1) {
        foreach (explode("\n", trim($m[1])) as $line) {
            $value .= unquote(trim($line));
        }
    }

    return $value === '' ? null : $value;
}

function unquote(string $quoted): string
{
    return stripcslashes(substr(trim($quoted), 1, -1));
}

/**
 * Replace the msgstr lines of a block, leaving comments and references intact.
 */
function applyTranslation(string $block, string $translation): string
{
    if (str_contains($block, 'msgid_plural')) {
        // Persian and Arabic take different numbers of plural forms, so an
        // untranslated plural is left with the template's empty slots rather
        // than being given a guessed count.
        if ($translation === '') {
            return $block;
        }

        $forms = explode("\x00", $translation);

        $block = (string) preg_replace('/^msgstr\[\d+\] ".*"$/m', '', $block);
        $block = rtrim($block) . "\n";

        foreach ($forms as $i => $form) {
            $block .= sprintf('msgstr[%d] %s', $i, quote($form)) . "\n";
        }

        return rtrim($block);
    }

    return (string) preg_replace('/^msgstr ".*"$/m', 'msgstr ' . quote($translation), $block);
}

function quote(string $value): string
{
    return '"' . str_replace(['\\', '"', "\n", "\t"], ['\\\\', '\\"', '\\n', '\\t'], $value) . '"';
}

/**
 * @return array<string, string>
 */
function existingTranslations(string $po): array
{
    $found   = [];
    $msgid   = null;
    $msgstr  = null;
    $context = null;

    foreach (explode("\n", $po) as $line) {
        $line = trim($line);

        if (str_starts_with($line, 'msgid ')) {
            if ($msgid !== null && $msgstr !== null && $msgstr !== '') {
                $found[$msgid] = $msgstr;
            }

            $msgid   = unquote(substr($line, 6));
            $msgstr  = null;
            $context = 'msgid';

            continue;
        }

        if (str_starts_with($line, 'msgstr ')) {
            $msgstr  = unquote(substr($line, 7));
            $context = 'msgstr';

            continue;
        }

        if ($line !== '' && $line[0] === '"' && $context !== null) {
            if ($context === 'msgid') {
                $msgid .= unquote($line);
            } else {
                $msgstr .= unquote($line);
            }
        }
    }

    if ($msgid !== null && $msgstr !== null && $msgstr !== '') {
        $found[$msgid] = $msgstr;
    }

    unset($found['']);

    return $found;
}

function poHeader(string $templateHeader, string $locale): string
{
    $language = substr($locale, 0, 2);
    $plural   = PLURALS[$language] ?? 'nplurals=2; plural=(n != 1);';

    $header = preg_replace(
        '/"Plural-Forms:.*"/',
        '"Plural-Forms: ' . $plural . '\\\\n"',
        $templateHeader
    ) ?? $templateHeader;

    $header = (string) preg_replace('/"Language:.*"\n/', '', $header);
    $header = (string) preg_replace(
        '/("X-Domain:)/',
        '"Language: ' . $locale . '\\\\n"' . "\n" . '$1',
        $header
    );

    return $header . "\n";
}

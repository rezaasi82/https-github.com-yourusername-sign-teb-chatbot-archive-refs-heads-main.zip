#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compile translations: languages/*.po → the files WordPress actually loads.
 *
 * A finished `.po` does nothing on its own. WordPress reads binary `.mo` for
 * PHP strings, and for JavaScript it reads a JSON file whose *name* encodes an
 * md5 of the script's path — get that wrong and the dashboard silently stays
 * English while the admin menu around it is translated. This produces both, so
 * a translator needs a checkout and PHP rather than gettext plus WP-CLI.
 *
 * Usage:
 *   php bin/make-l10n.php            compile every languages/*.po
 *   php bin/make-l10n.php fa_IR      compile one locale
 */

const DOMAIN = 'medora-authority';

/**
 * The script path WordPress hashes to find JS translations.
 *
 * `wp_set_script_translations()` resolves to
 * `{domain}-{locale}-{md5(relative script path)}.json`, where the path is the
 * script's src relative to the plugin root. This must stay in step with
 * `AssetManager::enqueueApp()`; if that URL changes, this constant changes with
 * it or every JS string quietly falls back to English.
 */
const SCRIPT_PATH = 'assets/js/app.js';

/** Source-reference prefixes whose strings belong in the JS bundle. */
const JS_PREFIXES = ['src/'];

$root      = dirname(__DIR__);
$languages = $root . '/languages';
$only      = $argv[1] ?? null;

$files = glob($languages . '/' . DOMAIN . '-*.po') ?: [];

if ($files === []) {
    fwrite(STDERR, "No .po files in languages/. Translate languages/" . DOMAIN . ".pot first.\n");
    exit(0);
}

$compiled = 0;
$problems = 0;

foreach ($files as $file) {
    $locale = locale($file);

    if ($only !== null && $locale !== $only) {
        continue;
    }

    $entries = parsePo(file_get_contents($file));
    $header  = $entries['']['msgstr'][0] ?? '';
    unset($entries['']);

    $translated = array_filter(
        $entries,
        static fn (array $entry): bool => implode('', $entry['msgstr']) !== ''
    );

    foreach (placeholderMismatches($translated) as $problem) {
        fwrite(STDERR, "  {$locale}: {$problem}\n");
        $problems++;
    }

    $mo = $languages . '/' . DOMAIN . '-' . $locale . '.mo';
    file_put_contents($mo, buildMo($translated, $header));

    $jsEntries = array_filter($translated, static fn (array $entry): bool => isJs($entry['refs']));
    $json      = sprintf('%s/%s-%s-%s.json', $languages, DOMAIN, $locale, md5(SCRIPT_PATH));

    file_put_contents($json, buildJson($jsEntries, $header, $locale));

    printf(
        "%s: %d/%d translated → %s + %s (%d for JS)\n",
        $locale,
        count($translated),
        count($entries),
        basename($mo),
        basename($json),
        count($jsEntries)
    );

    $compiled++;
}

if ($compiled === 0) {
    fwrite(STDERR, "No .po file found for locale " . (string) $only . ".\n");
    exit(1);
}

exit($problems === 0 ? 0 : 1);

// ---------------------------------------------------------------------------

/**
 * Translations that dropped, added or renumbered a printf placeholder.
 *
 * This is the one translation error that becomes a runtime error rather than
 * cosmetic damage: `sprintf()` throws on a missing argument, so a translator
 * losing a `%s` takes the screen down in that locale only — which is exactly
 * the kind of breakage nobody notices until a customer reports it.
 *
 * @param array<string, array<string, mixed>> $entries
 * @return list<string>
 */
function placeholderMismatches(array $entries): array
{
    $pattern  = '/%(?:\d+\\$)?[sdf]|%%/';
    $problems = [];

    foreach ($entries as $key => $entry) {
        // Context and plural forms are packed into the key; only the singular
        // source carries the placeholders worth comparing.
        $source = explode("\x00", str_contains($key, "\x04") ? explode("\x04", $key)[1] : $key)[0];

        preg_match_all($pattern, $source, $inSource);

        foreach ($entry['msgstr'] as $translation) {
            if (trim($translation) === '') {
                continue;
            }

            preg_match_all($pattern, $translation, $inTranslation);

            $expected = $inSource[0];
            $actual   = $inTranslation[0];
            sort($expected);
            sort($actual);

            if ($expected !== $actual) {
                $problems[] = sprintf(
                    'placeholder mismatch — "%s" expects %s, translation has %s',
                    mb_strimwidth($source, 0, 60, '…'),
                    $expected === [] ? 'none' : implode(' ', $expected),
                    $actual === [] ? 'none' : implode(' ', $actual)
                );
            }
        }
    }

    return $problems;
}

function locale(string $file): string
{
    return (string) preg_replace('/^' . preg_quote(DOMAIN, '/') . '-|\.po$/', '', basename($file));
}

/**
 * @param list<string> $refs
 */
function isJs(array $refs): bool
{
    // A .po stripped of its source references cannot be split by origin. Rather
    // than ship an empty JS catalogue, everything goes in: a PHP-only string
    // present in the JS file is dead weight, whereas a missing one is a
    // visibly untranslated dashboard.
    if ($refs === []) {
        return true;
    }

    foreach ($refs as $ref) {
        foreach (JS_PREFIXES as $prefix) {
            if (str_starts_with($ref, $prefix)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @return array<string, array{context: ?string, msgid: string, plural: ?string, msgstr: list<string>, refs: list<string>, fuzzy: bool}>
 */
function parsePo(string $po): array
{
    $entries = [];
    $current = newEntry();
    $last    = null;

    foreach (explode("\n", $po) as $rawLine) {
        $line = trim($rawLine);

        if ($line === '') {
            $entries = commit($entries, $current);
            $current = newEntry();
            $last    = null;

            continue;
        }

        if (str_starts_with($line, '#:')) {
            foreach (preg_split('/\s+/', trim(substr($line, 2))) ?: [] as $ref) {
                if ($ref !== '') {
                    $current['refs'][] = $ref;
                }
            }

            continue;
        }

        if (str_starts_with($line, '#,')) {
            // Fuzzy entries are a translator's unfinished work. gettext ignores
            // them at runtime and so does this.
            $current['fuzzy'] = str_contains($line, 'fuzzy');

            continue;
        }

        if (str_starts_with($line, '#')) {
            continue;
        }

        if (preg_match('/^(msgctxt|msgid_plural|msgid)\s+(".*")$/', $line, $match) === 1) {
            $key = match ($match[1]) {
                'msgctxt'      => 'context',
                'msgid_plural' => 'plural',
                default        => 'msgid',
            };

            $current[$key] = unpoString($match[2]);
            $last = $key;

            continue;
        }

        if (preg_match('/^msgstr(?:\[(\d+)\])?\s+(".*")$/', $line, $match) === 1) {
            $index = $match[1] === '' || ! isset($match[1]) ? 0 : (int) $match[1];

            $current['msgstr'][$index] = unpoString($match[2]);
            $last = 'msgstr:' . $index;

            continue;
        }

        // A bare quoted line continues whichever field came last.
        if ($line[0] === '"' && $last !== null) {
            $value = unpoString($line);

            if (str_starts_with($last, 'msgstr:')) {
                $index = (int) substr($last, 7);
                $current['msgstr'][$index] .= $value;

                continue;
            }

            $current[$last] .= $value;
        }
    }

    return commit($entries, $current);
}

/**
 * @return array{context: ?string, msgid: ?string, plural: ?string, msgstr: list<string>, refs: list<string>, fuzzy: bool}
 */
function newEntry(): array
{
    return ['context' => null, 'msgid' => null, 'plural' => null, 'msgstr' => [], 'refs' => [], 'fuzzy' => false];
}

/**
 * @param array<string, array<string, mixed>> $entries
 * @param array<string, mixed>                $entry
 * @return array<string, array<string, mixed>>
 */
function commit(array $entries, array $entry): array
{
    if ($entry['msgid'] === null || $entry['fuzzy']) {
        return $entries;
    }

    ksort($entry['msgstr']);
    $entry['msgstr'] = array_values($entry['msgstr']);

    $entries[moKey($entry)] = $entry;

    return $entries;
}

/**
 * The key gettext looks a string up by: context and id joined with EOT, and
 * plural forms joined with NUL.
 *
 * @param array<string, mixed> $entry
 */
function moKey(array $entry): string
{
    $key = (string) $entry['msgid'];

    if ($entry['context'] !== null) {
        $key = $entry['context'] . "\x04" . $key;
    }

    if ($entry['plural'] !== null) {
        $key .= "\x00" . $entry['plural'];
    }

    return $key;
}

function unpoString(string $quoted): string
{
    return stripcslashes(substr(trim($quoted), 1, -1));
}

/**
 * The binary catalogue, in the format documented by the GNU gettext manual.
 *
 * @param array<string, array<string, mixed>> $entries
 */
function buildMo(array $entries, string $header): string
{
    // The header is the entry with an empty msgid, and gettext requires it
    // first — hence the sort below rather than relying on insertion order.
    $table = ['' => $header];

    foreach ($entries as $key => $entry) {
        $table[$key] = implode("\x00", $entry['msgstr']);
    }

    ksort($table, SORT_STRING);

    $count       = count($table);
    $originals   = [];
    $translations = [];

    // Two length/offset tables precede the string data. Their size is fixed by
    // the entry count, so the data offset can be computed before writing.
    $dataOffset = 28 + ($count * 8 * 2);
    $blob       = '';

    foreach ($table as $original => $translation) {
        $originals[]  = [strlen((string) $original), $dataOffset + strlen($blob)];
        $blob        .= $original . "\x00";
    }

    foreach ($table as $translation) {
        $translations[] = [strlen($translation), $dataOffset + strlen($blob)];
        $blob          .= $translation . "\x00";
    }

    $mo = pack(
        'VVVVVVV',
        0x950412de, // little-endian magic
        0,          // format revision
        $count,
        28,                       // offset of the originals table
        28 + ($count * 8),        // offset of the translations table
        0,                        // hash table size — optional, and unused here
        0                         // hash table offset
    );

    foreach ([...$originals, ...$translations] as [$length, $offset]) {
        $mo .= pack('VV', $length, $offset);
    }

    return $mo . $blob;
}

/**
 * The Jed-format catalogue `wp_set_script_translations()` expects.
 *
 * @param array<string, array<string, mixed>> $entries
 */
function buildJson(array $entries, string $header, string $locale): string
{
    $messages = [
        '' => [
            'domain'       => 'messages',
            'lang'         => str_replace('_', '-', $locale),
            'plural-forms' => pluralForms($header),
        ],
    ];

    foreach ($entries as $key => $entry) {
        $messages[$key] = $entry['msgstr'];
    }

    return (string) json_encode(
        [
            'translation-revision-date' => gmdate('Y-m-d H:i:sO'),
            'generator'                 => 'bin/make-l10n.php',
            'domain'                    => 'messages',
            'locale_data'               => ['messages' => $messages],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
}

function pluralForms(string $header): string
{
    if (preg_match('/^Plural-Forms:\s*(.+)$/mi', $header, $match) === 1) {
        return trim($match[1]);
    }

    return 'nplurals=2; plural=(n != 1);';
}

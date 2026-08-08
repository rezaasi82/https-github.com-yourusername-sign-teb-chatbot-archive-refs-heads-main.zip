#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build a review sheet: languages/*.po → one self-contained HTML page.
 *
 * The mechanical translation errors are already checked elsewhere — a
 * placeholder that went missing fails `make-l10n.php`, and a stale template
 * fails `make-pot.php --check`. What no tool can check is whether the Persian
 * reads like a person wrote it, and that is the whole reason the catalogue is
 * marked unreviewed.
 *
 * So this optimises for the only thing that matters: how fast a native speaker
 * can get through 741 strings. Strings are grouped by the screen they appear
 * on, because register is a property of a screen rather than of a string —
 * "Close" on a dialog and "Close" in a report want different words, and you can
 * only see that when they sit next to their neighbours. Progress and edits live
 * in the browser, so the work survives closing the tab and can be done over
 * several sittings.
 *
 * It writes no code. Corrections come back as a small PHP file that
 * `bin/apply-review.php` merges into both the `.po` and the map.
 *
 * Usage:
 *   php bin/review-sheet.php            every locale with a .po
 *   php bin/review-sheet.php fa_IR      one locale
 */

const DOMAIN = 'medora-authority';

/**
 * Where a string lives, from its source reference.
 *
 * The order matters: the first pattern that matches wins, so the specific
 * entries come before the directory-wide fallbacks.
 */
const AREAS = [
    'src/pages/Overview'      => 'صفحهٔ نمای کلی',
    'src/pages/Entities'      => 'صفحهٔ موجودیت‌ها',
    'src/pages/Graph'         => 'صفحهٔ گراف دانش',
    'src/pages/Content'       => 'صفحهٔ محتوا',
    'src/pages/Crawlers'      => 'صفحهٔ خزنده‌ها',
    'src/pages/Analytics'     => 'صفحهٔ تحلیل',
    'src/pages/Audit'         => 'صفحهٔ گزارش رویدادها',
    'src/pages/Settings'      => 'صفحهٔ تنظیمات',
    'src/pages/Wizard'        => 'راه‌اندازی گام‌به‌گام',
    'src/components'          => 'اجزای مشترک داشبورد',
    'src/app'                 => 'چارچوب داشبورد',
    'src/api'                 => 'پیام‌های خطای داشبورد',
    'src/utils'               => 'قالب‌بندی اعداد و تاریخ',
    'src/editor'              => 'ویرایشگر نوشته',
    'includes/Admin/SetupWizard' => 'راه‌اندازی گام‌به‌گام (سمت سرور)',
    'includes/Admin'          => 'منو و صفحه‌های مدیریت',
    'includes/Rest'           => 'پیام‌های API',
    'includes/Score'          => 'امتیاز اعتبار',
    'includes/Content'        => 'بهینه‌سازی محتوا',
    'includes/Entity'         => 'موجودیت‌ها',
    'includes/Graph'          => 'گراف دانش',
    'includes/Crawler'        => 'خزنده‌ها',
    'includes/Citation'       => 'استنادها',
    'includes/License'        => 'لایسنس',
    'includes/Geo'            => 'سازگاری با مدل‌های زبانی',
    'includes/Llm'            => 'نویسندهٔ هوش مصنوعی',
    'includes/Security'       => 'امنیت',
    'includes/Analytics'      => 'تحلیل',
    'includes'                => 'سایر بخش‌های سرور',
];

/**
 * The terminology table from the map header, as pairs the sheet can check.
 *
 * A reviewer changing "موجودیت" to something else is making a decision for the
 * whole product, not for one string, so the sheet says so at the point of edit
 * rather than leaving it to be noticed in QA.
 */
const GLOSSARY = [
    'authority'       => 'اعتبار',
    'score'           => 'امتیاز',
    'entity'          => 'موجودیت',
    'knowledge graph' => 'گراف دانش',
    'crawler'         => 'خزنده',
    'passage'         => 'بند',
    'citation'        => 'استناد',
    'deduction'       => 'کسر امتیاز',
    'salience'        => 'برجستگی',
    'coverage'        => 'پوشش',
    'readiness'       => 'آمادگی',
    'internal link'   => 'پیوند داخلی',
    'brief'           => 'راهنمای نگارش',
];

$root      = dirname(__DIR__);
$languages = $root . '/languages';
$only      = $argv[1] ?? null;

$files = glob($languages . '/' . DOMAIN . '-*.po') ?: [];

if ($files === []) {
    fwrite(STDERR, "No .po files in languages/.\n");
    exit(1);
}

$written = 0;

foreach ($files as $file) {
    $locale = (string) preg_replace('/^' . preg_quote(DOMAIN, '/') . '-|\.po$/', '', basename($file));

    if ($only !== null && $locale !== $only) {
        continue;
    }

    $entries = entries((string) file_get_contents($file));
    $out     = $root . '/dist/review-' . $locale . '.html';

    if (! is_dir($root . '/dist') && ! mkdir($root . '/dist', 0o755, true) && ! is_dir($root . '/dist')) {
        fwrite(STDERR, "Cannot create dist/\n");
        exit(1);
    }

    file_put_contents($out, page($entries, $locale));

    printf(
        "%s: %d strings in %d areas → %s\n",
        $locale,
        count($entries),
        count(array_unique(array_column($entries, 'area'))),
        str_replace($root . '/', '', $out)
    );

    $written++;
}

if ($written === 0) {
    fwrite(STDERR, "No .po file for locale " . (string) $only . ".\n");
    exit(1);
}

exit(0);

// ---------------------------------------------------------------------------

/**
 * @return list<array{id: string, source: string, translation: string, note: string, refs: list<string>, area: string, flags: list<string>}>
 */
function entries(string $po): array
{
    $entries = [];
    $current = ['source' => null, 'translation' => '', 'note' => '', 'refs' => []];
    $last    = null;

    foreach (explode("\n", $po) as $raw) {
        $line = trim($raw);

        if ($line === '') {
            $entries = commit($entries, $current);
            $current = ['source' => null, 'translation' => '', 'note' => '', 'refs' => []];
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

        if (str_starts_with($line, '#.')) {
            $current['note'] = trim(substr($line, 2));

            continue;
        }

        if (str_starts_with($line, '#')) {
            continue;
        }

        if (preg_match('/^msgid\s+(".*")$/', $line, $m) === 1) {
            $current['source'] = unquote($m[1]);
            $last = 'source';

            continue;
        }

        if (preg_match('/^msgstr(?:\[\d+\])?\s+(".*")$/', $line, $m) === 1) {
            // Plural forms are joined for display; a reviewer correcting one
            // form is correcting the entry, and apply-review keeps the split.
            $current['translation'] .= ($current['translation'] === '' ? '' : "\x00") . unquote($m[1]);
            $last = 'translation';

            continue;
        }

        if ($line[0] === '"' && $last !== null) {
            $current[$last] .= unquote($line);
        }
    }

    return commit($entries, $current);
}

/**
 * @param list<array<string, mixed>> $entries
 * @param array<string, mixed>       $entry
 * @return list<array<string, mixed>>
 */
function commit(array $entries, array $entry): array
{
    if ($entry['source'] === null || $entry['source'] === '') {
        return $entries;
    }

    $entry['area']  = areaOf($entry['refs']);
    $entry['flags'] = flagsFor((string) $entry['source'], (string) $entry['translation']);
    $entry['id']    = substr(hash('sha256', (string) $entry['source']), 0, 12);

    $entries[] = $entry;

    return $entries;
}

/**
 * @param list<string> $refs
 */
function areaOf(array $refs): string
{
    foreach ($refs as $ref) {
        foreach (AREAS as $prefix => $label) {
            if (str_starts_with($ref, $prefix)) {
                return $label;
            }
        }
    }

    return 'بدون مرجع';
}

/**
 * Everything a machine can say about a pair, so the reviewer's attention goes
 * to what only a person can judge.
 *
 * These are prompts, not verdicts. `needs-context` in particular fires on every
 * string with a placeholder — not because it is wrong, but because word order
 * around a substituted value is where a fluent-looking translation most often
 * turns out to read badly once the value is in it.
 *
 * @return list<string>
 */
function flagsFor(string $source, string $translation): array
{
    $flags = [];

    if (trim($translation) === '') {
        return ['untranslated'];
    }

    if ($source === $translation) {
        $flags[] = 'identical';
    }

    // Folded in Text::normalize() at runtime, but a catalogue written with
    // Arabic letterforms still looks wrong to a reader.
    if (preg_match('/[يكة]/u', $translation) === 1) {
        $flags[] = 'arabic-letters';
    }

    if (preg_match('/[٠-٩]/u', $translation) === 1) {
        $flags[] = 'arabic-digits';
    }

    if (preg_match('/%(?:\d+\$)?[sdf]/', $source) === 1) {
        $flags[] = 'has-placeholder';
    }

    // A long English sentence rendered in a third of the characters usually
    // means a clause was dropped rather than that Persian is terser.
    $sourceLength = mb_strlen($source);

    if ($sourceLength > 40 && mb_strlen($translation) < $sourceLength * 0.45) {
        $flags[] = 'suspiciously-short';
    }

    foreach (GLOSSARY as $english => $persian) {
        // The product name is a proper noun, so "Medora Authority" does not owe
        // the reader "اعتبار" — it is transliterated, deliberately.
        $subject = str_ireplace('Medora Authority', '', $source);

        if (stripos($subject, $english) !== false && ! usesTerm($translation, $persian)) {
            $flags[] = 'glossary:' . $english;
        }
    }

    return $flags;
}

/**
 * Whether a translation uses a glossary term, allowing for Persian inflection.
 *
 * A literal substring test is useless here: the term is "پیوند داخلی" and the
 * interface says "پیوندهای داخلی" and "پیوندسازی داخلی", both correct. Matching
 * each word of the term as a prefix of some word in the translation covers the
 * plural and compound forms without accepting an unrelated word.
 *
 * Getting this wrong in the lenient direction costs nothing; getting it wrong
 * in the strict direction produces a flag on every correct string, which is how
 * a check stops being read.
 */
function usesTerm(string $translation, string $term): bool
{
    $strip = static fn (string $text): string => str_replace("\u{200c}", '', $text);
    $words = preg_split('/\s+/u', $strip($translation)) ?: [];

    foreach (preg_split('/\s+/u', $strip($term)) ?: [] as $part) {
        if ($part === '') {
            continue;
        }

        $found = false;

        foreach ($words as $word) {
            if (mb_strpos($word, $part) === 0) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return false;
        }
    }

    return true;
}

function unquote(string $quoted): string
{
    return stripcslashes(substr(trim($quoted), 1, -1));
}

/**
 * @param list<array<string, mixed>> $entries
 */
function page(array $entries, string $locale): string
{
    $template = dirname(__FILE__) . '/templates/review-sheet.html';

    if (! is_readable($template)) {
        fwrite(STDERR, "Missing {$template}\n");
        exit(1);
    }

    $data = json_encode(
        ['locale' => $locale, 'entries' => $entries, 'glossary' => GLOSSARY],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    // The payload is written into a `application/json` script block, where the
    // only sequence that can break out is a literal `</script`.
    $data = str_replace('</', '<\/', (string) $data);

    return str_replace(
        ['{{LOCALE}}', '{{DATA}}', '{{DOMAIN}}'],
        [$locale, $data, DOMAIN],
        (string) file_get_contents($template)
    );
}

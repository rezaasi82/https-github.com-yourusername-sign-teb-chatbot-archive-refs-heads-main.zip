#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build languages/medora-authority.pot from the PHP and TypeScript sources.
 *
 * `wp i18n make-pot` is the usual tool and produces the same file. This exists
 * because it needs neither WP-CLI nor Composer nor node_modules: a translator
 * who has cloned the repo can regenerate the template with the PHP that is
 * already installed, and CI can check the template is current without
 * provisioning a WordPress toolchain to do it.
 *
 * PHP is read through `token_get_all`, so concatenated strings, calls inside
 * comments and method calls that merely share a name with a gettext function
 * are all handled exactly. TypeScript has no tokenizer here and is scanned by
 * pattern; the scanner reports anything it cannot resolve to a literal instead
 * of skipping it, because a string that silently fails to extract is a string
 * that silently ships untranslated.
 *
 * Usage:
 *   php bin/make-pot.php [--check]
 *
 *   --check  Exit non-zero if the template on disk is out of date. Writes
 *            nothing. This is what CI runs.
 */

const DOMAIN = 'medora-authority';

/**
 * Gettext functions, mapped to the argument positions that carry text.
 *
 * `singular` and `plural` are 1-indexed argument positions; `context` likewise.
 * `domain` is the position that must hold this plugin's text domain — calls
 * carrying a different domain belong to another plugin and are skipped.
 */
const FUNCTIONS = [
    '__'            => ['singular' => 1, 'domain' => 2],
    '_e'            => ['singular' => 1, 'domain' => 2],
    'esc_html__'    => ['singular' => 1, 'domain' => 2],
    'esc_html_e'    => ['singular' => 1, 'domain' => 2],
    'esc_attr__'    => ['singular' => 1, 'domain' => 2],
    'esc_attr_e'    => ['singular' => 1, 'domain' => 2],
    '_x'            => ['singular' => 1, 'context' => 2, 'domain' => 3],
    '_ex'           => ['singular' => 1, 'context' => 2, 'domain' => 3],
    'esc_html_x'    => ['singular' => 1, 'context' => 2, 'domain' => 3],
    'esc_attr_x'    => ['singular' => 1, 'context' => 2, 'domain' => 3],
    '_n'            => ['singular' => 1, 'plural' => 2, 'domain' => 4],
    '_n_noop'       => ['singular' => 1, 'plural' => 2, 'domain' => 3],
    '_nx'           => ['singular' => 1, 'plural' => 2, 'context' => 4, 'domain' => 5],
    '_nx_noop'      => ['singular' => 1, 'plural' => 2, 'context' => 3, 'domain' => 4],
];

const PHP_PATHS = ['includes', 'templates', 'medora-authority.php', 'uninstall.php'];
const JS_PATHS  = ['src'];

$root  = dirname(__DIR__);
$check = in_array('--check', $argv, true);

/** @var array<string, array{
 *     context: ?string, singular: string, plural: ?string,
 *     comments: list<string>, refs: list<string>
 * }> $entries */
$entries  = [];
$problems = [];

foreach (files($root, PHP_PATHS, ['php']) as $file) {
    foreach (extractPhp(file_get_contents($file), relative($root, $file), $problems) as $entry) {
        addEntry($entries, $entry);
    }
}

foreach (files($root, JS_PATHS, ['ts', 'tsx', 'js', 'jsx']) as $file) {
    foreach (extractJs(file_get_contents($file), relative($root, $file), $problems) as $entry) {
        addEntry($entries, $entry);
    }
}

// Sorted by first source reference, so the template's diff between two runs
// reflects real string changes rather than filesystem iteration order.
uasort($entries, static fn (array $a, array $b): int => [$a['refs'][0], $a['singular']] <=> [$b['refs'][0], $b['singular']]);

$pot    = render($entries);
$target = $root . '/languages/' . DOMAIN . '.pot';

foreach ($problems as $problem) {
    fwrite(STDERR, "  warning: {$problem}\n");
}

if ($check) {
    $current = is_readable($target) ? file_get_contents($target) : '';

    if (stripHeader($current) !== stripHeader($pot)) {
        fwrite(STDERR, "languages/" . DOMAIN . ".pot is out of date. Run: php bin/make-pot.php\n");
        exit(1);
    }

    printf("POT is current: %d strings.\n", count($entries));
    exit($problems === [] ? 0 : 1);
}

file_put_contents($target, $pot);
printf("Wrote %s: %d strings from %d references.\n", relative($root, $target), count($entries), array_sum(array_map(
    static fn (array $entry): int => count($entry['refs']),
    $entries
)));

exit($problems === [] ? 0 : 1);

// ---------------------------------------------------------------------------

/**
 * @param list<string> $paths
 * @param list<string> $extensions
 * @return list<string>
 */
function files(string $root, array $paths, array $extensions): array
{
    $found = [];

    foreach ($paths as $path) {
        $full = $root . '/' . $path;

        if (is_file($full)) {
            $found[] = $full;
            continue;
        }

        if (! is_dir($full)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $found[] = $file->getPathname();
            }
        }
    }

    sort($found);

    return $found;
}

function relative(string $root, string $path): string
{
    return ltrim(str_replace($root, '', $path), '/');
}

/**
 * @param array<string, array<string, mixed>> $entries
 * @param array<string, mixed>                $entry
 */
function addEntry(array &$entries, array $entry): void
{
    $key = ($entry['context'] ?? '') . "\4" . $entry['singular'] . "\4" . ($entry['plural'] ?? '');

    if (! isset($entries[$key])) {
        $entries[$key] = $entry;

        return;
    }

    // The same string in two files is one translation with two references.
    $entries[$key]['refs'] = array_values(array_unique([...$entries[$key]['refs'], ...$entry['refs']]));
    $entries[$key]['comments'] = array_values(array_unique([...$entries[$key]['comments'], ...$entry['comments']]));
}

/**
 * @param list<string> $problems
 * @return list<array<string, mixed>>
 */
function extractPhp(string $code, string $file, array &$problems): array
{
    $tokens  = token_get_all($code);
    $entries = [];
    $comment = null;

    foreach ($tokens as $index => $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $text = trim(preg_replace('#^(/\*+|//|\#|\*+/)|(\*+/)$#', '', trim($token[1])) ?? '');
            $text = trim(preg_replace('/^\s*\*\s?/m', '', $text) ?? '');

            // Only translator notes travel into the template; every other
            // comment in the file is for the reader of the code.
            $comment = stripos($text, 'translators:') === 0 ? $text : null;

            continue;
        }

        if (is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }

        if (! is_array($token) || $token[0] !== T_STRING || ! isset(FUNCTIONS[$token[1]])) {
            $comment = null;

            continue;
        }

        // `$object->__()` and `Klass::__()` are not gettext calls.
        $previous = previousSignificant($tokens, $index);

        if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR], true)) {
            $comment = null;

            continue;
        }

        $args = phpArguments($tokens, $index);

        if ($args === null) {
            $comment = null;

            continue;
        }

        $entry = buildEntry(FUNCTIONS[$token[1]], $args, $token[1], $file, $token[2], $comment, $problems);

        if ($entry !== null) {
            $entries[] = $entry;
        }

        $comment = null;
    }

    return $entries;
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 * @return array{0: int, 1: string, 2: int}|string|null
 */
function previousSignificant(array $tokens, int $index)
{
    for ($i = $index - 1; $i >= 0; $i--) {
        if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $tokens[$i];
    }

    return null;
}

/**
 * Argument list of the call starting at `$index`, one entry per argument.
 *
 * A `null` entry means the argument was not a literal string — a variable, a
 * constant, a function call. Those cannot be extracted, and the caller reports
 * them rather than dropping them.
 *
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 * @return list<string|null>|null
 */
function phpArguments(array $tokens, int $index): ?array
{
    $i = $index + 1;

    while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
        $i++;
    }

    if (($tokens[$i] ?? null) !== '(') {
        return null;
    }

    $depth     = 0;
    $args      = [];
    $current   = [];
    $literal   = true;
    $pieces    = [];

    for (; isset($tokens[$i]); $i++) {
        $token = $tokens[$i];

        if ($token === '(') {
            $depth++;

            if ($depth === 1) {
                continue;
            }
        }

        if ($token === ')') {
            $depth--;

            if ($depth === 0) {
                $args[] = finishArgument($current, $literal, $pieces);

                return $args;
            }
        }

        if ($depth === 1 && $token === ',') {
            $args[]  = finishArgument($current, $literal, $pieces);
            $current = [];
            $literal = true;
            $pieces  = [];

            continue;
        }

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $current[] = $token;

        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $pieces[] = unquote($token[1]);

            continue;
        }

        // A `.` between literals is concatenation and stays extractable;
        // anything else in the argument means it is not a constant string.
        if ($token !== '.') {
            $literal = false;
        }
    }

    return null;
}

/**
 * @param list<mixed>  $current
 * @param list<string> $pieces
 */
function finishArgument(array $current, bool $literal, array $pieces): ?string
{
    if ($current === [] || ! $literal || $pieces === []) {
        return null;
    }

    return implode('', $pieces);
}

function unquote(string $token): string
{
    $quote = $token[0];
    $inner = substr($token, 1, -1);

    if ($quote === "'") {
        return str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
    }

    return stripcslashes($inner);
}

/**
 * TypeScript and JavaScript, scanned by pattern.
 *
 * @param list<string> $problems
 * @return list<array<string, mixed>>
 */
function extractJs(string $code, string $file, array &$problems): array
{
    $entries = [];
    $names   = implode('|', ['__', '_x', '_n', '_nx']);

    // Only the call *opening* is matched by pattern. The closing parenthesis is
    // then found by scanning with string awareness, because a regex cannot do
    // it: `__( '%d thing(s) left', 'medora-authority' )` closes at the paren
    // inside the message, and a non-greedy match there silently truncates the
    // call to one argument — which looks exactly like a missing text domain.
    $pattern = '/(?<![\w$.])(' . $names . ')\s*\(/';

    if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE) === false) {
        return [];
    }

    foreach ($matches[1] as $i => $nameMatch) {
        $name   = $nameMatch[0];
        $line   = substr_count(substr($code, 0, (int) $nameMatch[1]), "\n") + 1;
        $open   = (int) $matches[0][$i][1] + strlen($matches[0][$i][0]) - 1;
        $inner  = jsCallBody($code, $open);

        if ($inner === null) {
            $problems[] = sprintf('%s:%d: %s() call is not closed.', $file, $line, $name);

            continue;
        }

        $args = jsArguments($inner);

        if ($args === null) {
            $problems[] = sprintf('%s:%d: %s() arguments could not be read as literals.', $file, $line, $name);

            continue;
        }

        $entry = buildEntry(FUNCTIONS[$name], $args, $name, $file, $line, jsComment($code, (int) $nameMatch[1]), $problems);

        if ($entry !== null) {
            $entries[] = $entry;
        }
    }

    return $entries;
}

/**
 * The text between a call's parentheses, given the offset of the opening one.
 *
 * Tracks quoting so parentheses inside message strings do not close the call,
 * and template literals so a backtick argument is reported rather than
 * mis-parsed.
 */
function jsCallBody(string $code, int $open): ?string
{
    $length = strlen($code);
    $depth  = 0;

    for ($i = $open; $i < $length; $i++) {
        $char = $code[$i];

        if ($char === '"' || $char === "'" || $char === '`') {
            $i = skipJsString($code, $i);

            if ($i === -1) {
                return null;
            }

            continue;
        }

        if ($char === '(') {
            $depth++;

            continue;
        }

        if ($char === ')') {
            $depth--;

            if ($depth === 0) {
                return substr($code, $open + 1, $i - $open - 1);
            }
        }
    }

    return null;
}

/** Offset of the closing quote of the string starting at `$start`, or -1. */
function skipJsString(string $code, int $start): int
{
    $quote  = $code[$start];
    $length = strlen($code);

    for ($i = $start + 1; $i < $length; $i++) {
        if ($code[$i] === '\\') {
            $i++;

            continue;
        }

        if ($code[$i] === $quote) {
            return $i;
        }
    }

    return -1;
}

/**
 * @return list<string|null>|null
 */
function jsArguments(string $inner): ?array
{
    $args    = [];
    $length  = strlen($inner);
    $current = '';
    $depth   = 0;

    for ($i = 0; $i < $length; $i++) {
        $char = $inner[$i];

        if ($char === '(' || $char === '[' || $char === '{') {
            $depth++;
        } elseif ($char === ')' || $char === ']' || $char === '}') {
            $depth--;
        }

        if ($char === ',' && $depth === 0) {
            $args[]  = jsLiteral($current);
            $current = '';

            continue;
        }

        if ($char === '"' || $char === "'") {
            // Consume the whole string so a comma inside it does not split the
            // argument list.
            $end = $i + 1;

            while ($end < $length && ! ($inner[$end] === $char && $inner[$end - 1] !== '\\')) {
                $end++;
            }

            $current .= substr($inner, $i, $end - $i + 1);
            $i = $end;

            continue;
        }

        $current .= $char;
    }

    $args[] = jsLiteral($current);

    return $args;
}

function jsLiteral(string $raw): ?string
{
    $raw = trim($raw);

    // Concatenated literals, the one non-atomic form worth supporting.
    $parts = preg_split('/\s*\+\s*/', $raw) ?: [];
    $out   = '';

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part === '' || ($part[0] !== '"' && $part[0] !== "'")) {
            return null;
        }

        $out .= stripcslashes(substr($part, 1, -1));
    }

    return $out;
}

/**
 * The `/* translators: … *&#47;` comment immediately preceding an offset.
 */
function jsComment(string $code, int $offset): ?string
{
    $before = substr($code, max(0, $offset - 400), min(400, $offset));

    if (preg_match('#/\*\s*(translators:.*?)\*/\s*$#is', $before, $match) !== 1) {
        return null;
    }

    return trim(preg_replace('/^\s*\*\s?/m', '', trim($match[1])) ?? '');
}

/**
 * @param array<string, int>  $spec
 * @param list<string|null>   $args
 * @param list<string>        $problems
 * @return array<string, mixed>|null
 */
function buildEntry(array $spec, array $args, string $name, string $file, int $line, ?string $comment, array &$problems): ?array
{
    $domain = $args[$spec['domain'] - 1] ?? null;

    // A missing or foreign domain is not this template's business. A *wrong*
    // domain, though, is a bug worth surfacing: the string will never be
    // translated and nothing else in the toolchain will say so.
    if ($domain !== DOMAIN) {
        if ($domain === null && count($args) >= $spec['domain']) {
            $problems[] = sprintf('%s:%d: %s() text domain is not a literal.', $file, $line, $name);
        } elseif (is_string($domain)) {
            $problems[] = sprintf('%s:%d: %s() uses text domain "%s".', $file, $line, $name, $domain);
        } else {
            $problems[] = sprintf('%s:%d: %s() is missing its text domain.', $file, $line, $name);
        }

        return null;
    }

    $singular = $args[$spec['singular'] - 1] ?? null;

    if ($singular === null) {
        $problems[] = sprintf('%s:%d: %s() text is not a literal string.', $file, $line, $name);

        return null;
    }

    $entry = [
        'context'  => isset($spec['context']) ? ($args[$spec['context'] - 1] ?? null) : null,
        'singular' => $singular,
        'plural'   => isset($spec['plural']) ? ($args[$spec['plural'] - 1] ?? null) : null,
        'comments' => $comment !== null ? [$comment] : [],
        'refs'     => [$file . ':' . $line],
    ];

    if (isset($spec['plural']) && $entry['plural'] === null) {
        $problems[] = sprintf('%s:%d: %s() plural form is not a literal string.', $file, $line, $name);

        return null;
    }

    // A placeholder without a translator note leaves the translator guessing
    // what %1$s holds, which is how "%1$s of %2$s" gets reordered wrongly.
    if ($entry['comments'] === [] && preg_match('/%[\d]+\$|%s.*%s/', $singular) === 1) {
        $problems[] = sprintf('%s:%d: %s() has multiple placeholders and no translators comment.', $file, $line, $name);
    }

    return $entry;
}

/**
 * @param array<string, array<string, mixed>> $entries
 */
function render(array $entries): string
{
    $version = potVersion();
    $out     = <<<POT
        # Copyright (C) 2026 Medora
        # This file is distributed under the GPL v2 or later.
        #
        # Regenerate with:
        #   php bin/make-pot.php
        #
        # Target locales: fa_IR then en_US. Arabic is deliberately out of scope.
        # The dashboard is RTL-ready by construction, so an RTL locale needs no
        # separate stylesheet build if one is added later.
        msgid ""
        msgstr ""
        "Project-Id-Version: Medora Authority {$version}\\n"
        "Report-Msgid-Bugs-To: https://medora.ai/support\\n"
        "Last-Translator: \\n"
        "Language-Team: \\n"
        "MIME-Version: 1.0\\n"
        "Content-Type: text/plain; charset=UTF-8\\n"
        "Content-Transfer-Encoding: 8bit\\n"
        "X-Generator: bin/make-pot.php\\n"
        "X-Domain: medora-authority\\n"
        "Plural-Forms: nplurals=2; plural=(n != 1);\\n"

        POT;

    foreach ($entries as $entry) {
        $out .= "\n";

        foreach ($entry['comments'] as $comment) {
            foreach (explode("\n", $comment) as $commentLine) {
                $out .= '#. ' . trim($commentLine) . "\n";
            }
        }

        foreach ($entry['refs'] as $ref) {
            $out .= '#: ' . $ref . "\n";
        }

        if ($entry['context'] !== null) {
            $out .= 'msgctxt ' . poString($entry['context']) . "\n";
        }

        $out .= 'msgid ' . poString($entry['singular']) . "\n";

        if ($entry['plural'] !== null) {
            $out .= 'msgid_plural ' . poString($entry['plural']) . "\n";
            $out .= "msgstr[0] \"\"\n";
            $out .= "msgstr[1] \"\"\n";

            continue;
        }

        $out .= "msgstr \"\"\n";
    }

    return $out;
}

function potVersion(): string
{
    $header = (string) file_get_contents(dirname(__DIR__) . '/medora-authority.php');

    return preg_match('/^\s*\*\s*Version:\s*(\S+)/mi', $header, $match) === 1 ? $match[1] : '0.0.0';
}

function poString(string $value): string
{
    $escaped = str_replace(
        ['\\', '"', "\t", "\r"],
        ['\\\\', '\\"', '\\t', '\\r'],
        $value
    );

    if (! str_contains($escaped, "\n")) {
        return '"' . $escaped . '"';
    }

    // Multi-line strings use the empty-first-line form so the line breaks stay
    // visible to the translator instead of collapsing into one long line.
    $lines = explode("\n", $escaped);
    $out   = "\"\"\n";

    foreach ($lines as $i => $line) {
        $out .= '"' . $line . ($i === count($lines) - 1 ? '' : '\\n') . "\"\n";
    }

    return rtrim($out);
}

/**
 * Drop the header for comparison: the version line legitimately changes on
 * every release without any string having changed.
 */
function stripHeader(string $pot): string
{
    $marker = "\n\n";
    $at     = strpos($pot, $marker);

    return $at === false ? $pot : substr($pot, $at);
}

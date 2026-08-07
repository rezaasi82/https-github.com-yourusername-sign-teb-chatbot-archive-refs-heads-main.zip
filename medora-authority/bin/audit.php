#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Static audit of the things a linter cannot see.
 *
 * PHPCS catches style and PHPStan catches types. Neither catches the failure
 * mode that has produced most of this codebase's real bugs: something declared
 * and never consumed. A setting nobody reads, a feature flag nothing gates, an
 * endpoint no screen calls, a hook in no document — each one is silent, passes
 * every other check, and is usually a feature that does not exist.
 *
 * These checks began as throwaway scripts. They found a locale-dependent facet
 * identity, a white-label colour that never applied, four experience-mode flags
 * with no consumer, an audit log nobody could read and an entity deletion that
 * undid itself. That is a good enough record to be worth running on every
 * commit rather than when someone remembers.
 *
 * Needs no WP-CLI, no Composer, no database.
 *
 * Usage:
 *   php bin/audit.php           report, exit non-zero on findings
 *   php bin/audit.php --quiet   only print findings
 */

$root    = dirname(__DIR__);
$quiet   = in_array('--quiet', $argv, true);
$failed  = 0;

/**
 * Known-acceptable cases.
 *
 * Every entry needs a reason. An allowlist without one becomes a place to hide
 * findings, which is worse than not checking at all.
 */
const ALLOWED = [
    // Consumed by the PHP admin menu rather than by the dashboard bundle.
    'flag:audit_log'    => 'gated in AdminMenu, not in src/',
    // Public knowledge API, for external consumers rather than our own UI.
    'route:citations'   => 'public API',
    'route:prompt'      => 'public API',
    'route:authors'     => 'public API',
];

// ---------------------------------------------------------------------------

/** @return array<string, string> */
function files(string $root, array $dirs, array $extensions): array
{
    $found = [];

    foreach ($dirs as $dir) {
        $path = $root . '/' . $dir;

        if (is_file($path)) {
            $found[$dir] = (string) file_get_contents($path);
            continue;
        }

        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $found[str_replace($root . '/', '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
            }
        }
    }

    ksort($found);

    return $found;
}

/**
 * @param list<string> $findings
 */
function check(string $title, array $findings, bool $quiet): int
{
    $findings = array_values(array_filter(
        $findings,
        static fn (string $f): bool => ! isset(ALLOWED[explode('  ', $f)[0]])
    ));

    if ($findings === []) {
        if (! $quiet) {
            printf("  ok    %s\n", $title);
        }

        return 0;
    }

    printf("  FAIL  %s (%d)\n", $title, count($findings));

    foreach ($findings as $finding) {
        printf("          %s\n", $finding);
    }

    return 1;
}

$php = files($root, ['includes', 'templates', 'uninstall.php', 'medora-authority.php'], ['php']);
$ts  = files($root, ['src'], ['ts', 'tsx']);
$code = implode("\n", $php);
$app  = implode("\n", $ts);

$docs = '';
foreach (array_merge(glob($root . '/docs/*.md') ?: [], [$root . '/CHANGELOG.md']) as $file) {
    $docs .= (string) file_get_contents($file);
}

if (! $quiet) {
    printf("Auditing %d PHP files and %d dashboard files.\n\n", count($php), count($ts));
}

// --- 1. Settings nothing reads ---------------------------------------------
$defaults = substr($php['includes/Core/Options.php'], strpos($php['includes/Core/Options.php'], 'public static function defaults()'));
preg_match_all("/'([a-z_0-9]+)'\s*=>/", $defaults, $m);

$deadOptions = [];

foreach (array_unique($m[1]) as $key) {
    $reads = 0;

    foreach ($php + $ts as $path => $contents) {
        if ($path === 'includes/Core/Options.php') {
            continue;
        }

        $reads += substr_count($contents, "'{$key}'") + substr_count($contents, "\"{$key}\"");
    }

    if ($reads === 0) {
        $deadOptions[] = "option:{$key}  declared in Options::defaults() and read nowhere";
    }
}

$failed += check('every setting has a consumer', $deadOptions, $quiet);

// --- 2. Experience flags nothing gates --------------------------------------
$experience = $php['includes/Admin/ExperienceMode.php'] ?? '';
preg_match_all("/'([a-z_]+)'\s*=>\s*self::(BEGINNER|PROFESSIONAL|AGENCY|ENTERPRISE)/", $experience, $m);

$deadFlags = [];

// Searched everywhere except the file that declares them: counting a
// declaration as its own use is what makes a check like this pass forever.
// This one did exactly that until a deliberately broken flag failed to trip it.
$elsewhere = $app;

foreach ($php as $path => $contents) {
    if ($path !== 'includes/Admin/ExperienceMode.php') {
        $elsewhere .= $contents;
    }
}

foreach ($m[1] as $flag) {
    // Tab ids are consumed dynamically (`TABS.filter(([id]) => shows(id))`),
    // so a literal `shows('x')` is not the only valid use.
    $used = str_contains($elsewhere, "'{$flag}'") || str_contains($elsewhere, "\"{$flag}\"");

    if (! $used) {
        $deadFlags[] = "flag:{$flag}  declared in ExperienceMode and gates nothing";
    }
}

$failed += check('every experience flag gates something', $deadFlags, $quiet);

// --- 3. Hooks fired but undocumented ----------------------------------------
preg_match_all("/(?:do_action|apply_filters)\(\s*['\"](medora_[a-z0-9_]+)/", $code, $m);

$undocumented = [];

foreach (array_unique($m[1]) as $hook) {
    if (! str_contains($docs, $hook)) {
        $undocumented[] = "hook:{$hook}  fired in code, in no document";
    }
}

$failed += check('every hook is documented', $undocumented, $quiet);

// --- 4. Superglobals read without sanitising --------------------------------
$raw = [];

foreach ($php as $path => $contents) {
    foreach (explode("\n", $contents) as $n => $line) {
        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[/', $line) !== 1) {
            continue;
        }

        if (preg_match('/(sanitize_|wp_unslash|absint|isset|empty|array_key_exists)/', $line) === 1) {
            continue;
        }

        $raw[] = sprintf('super:%s:%d  %s', $path, $n + 1, trim($line));
    }
}

$failed += check('superglobals are unslashed and sanitised', $raw, $quiet);

// --- 5. Output escaping ------------------------------------------------------
$unescaped = [];

foreach ($php as $path => $contents) {
    foreach (explode("\n", $contents) as $n => $line) {
        if (preg_match('/\b(echo|print)\s+.*\$/', $line) !== 1) {
            continue;
        }

        if (preg_match('/(esc_html|esc_attr|esc_url|esc_js|esc_textarea|wp_json_encode|wp_kses|phpcs:ignore|sprintf\(|implode\()/', $line) === 1) {
            continue;
        }

        $unescaped[] = sprintf('echo:%s:%d  %s', $path, $n + 1, trim($line));
    }
}

$failed += check('output is escaped', $unescaped, $quiet);

// --- 6. REST args without sanitising ----------------------------------------
$args = [];

foreach ($php as $path => $contents) {
    if (! str_contains($contents, 'register_rest_route')) {
        continue;
    }

    preg_match_all("/'([a-z_]+)'\s*=>\s*\[\s*'type'\s*=>\s*'(string|integer|number)'([^\]]*)\]/", $contents, $m, PREG_SET_ORDER);

    foreach ($m as $arg) {
        // `items` is the JSON-schema keyword for array members, not an argument.
        if ($arg[1] === 'items') {
            continue;
        }

        if (preg_match('/(sanitize_callback|validate_callback|enum)/', $arg[3]) === 1) {
            continue;
        }

        $args[] = sprintf('arg:%s  %s (%s) has no sanitize_callback', $path, $arg[1], $arg[2]);
    }
}

$failed += check('REST arguments declare sanitising', $args, $quiet);

// --- 7. Uninstall completeness ----------------------------------------------
$uninstall = $php['uninstall.php'];
$leaked    = [];

foreach ($php as $path => $contents) {
    if ($path === 'uninstall.php') {
        continue;
    }

    foreach ([
        "/update_post_meta\([^,]+,\s*'([a-z_]+)'/" => 'post meta',
        "/(?:add_option|update_option)\(\s*'(medora_[a-z_]+)'/" => 'option',
    ] as $pattern => $kind) {
        preg_match_all($pattern, $contents, $m);

        foreach (array_unique($m[1]) as $key) {
            if (! str_contains($uninstall, "'{$key}'")) {
                $leaked[] = "uninstall:{$key}  {$kind} created in {$path}, never removed";
            }
        }
    }
}

$failed += check('uninstall removes everything the plugin creates', array_unique($leaked), $quiet);

// --- 8. Dashboard API methods with no caller --------------------------------
$client   = $ts['src/api/client.ts'] ?? '';
$callers  = '';

foreach ($ts as $path => $contents) {
    if ($path !== 'src/api/client.ts') {
        $callers .= $contents;
    }
}

preg_match_all('/^\t([a-zA-Z]+):\s*\(/m', $client, $m);

$uncalled = [];

foreach ($m[1] as $method) {
    if (! str_contains($callers, "api.{$method}(")) {
        $uncalled[] = "api:{$method}  exposed in the client and called by nothing";
    }
}

$failed += check('every dashboard API method has a caller', $uncalled, $quiet);

// ---------------------------------------------------------------------------

if ($failed > 0) {
    printf("\n%d audit(s) failed.\n", $failed);
    exit(1);
}

if (! $quiet) {
    print("\nAll audits passed.\n");
}

exit(0);

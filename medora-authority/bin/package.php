#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build the installable ZIP.
 *
 * A checkout is not a plugin. The dashboard bundle, the compiled stylesheets
 * and the binary catalogues are all build outputs, all correctly gitignored,
 * and all required at runtime — so anyone who zips the repository gets a
 * plugin that activates, shows a blank dashboard and speaks English. This
 * assembles the artifact that actually installs.
 *
 * Two properties matter more than the zipping:
 *
 * 1. The file list is an allowlist. A denylist ships whatever nobody thought
 *    to exclude — an .env, a vendor tree with dev packages, a scratch dump.
 *    Anything not named here is not in the release.
 * 2. It refuses to package a build that is older than its sources, or a
 *    version that disagrees with itself across the four places it is written.
 *    A stale bundle is invisible: the plugin works, just not as the code says.
 *
 * Usage:
 *   php bin/package.php              build dist/medora-authority-<version>.zip
 *   php bin/package.php --check      verify readiness, write nothing
 */

const SLUG = 'medora-authority';

/**
 * What ships, relative to the plugin root.
 *
 * Directories are taken whole, filtered by extension. Everything else — src/,
 * tests/, bin/, docs/, node_modules/, vendor/, every dotfile and every tooling
 * config — is absent because it is not listed, not because a rule excluded it.
 */
const SHIP = [
    'files' => [
        'medora-authority.php',
        'uninstall.php',
        'readme.txt',
        'CHANGELOG.md',
    ],
    'dirs' => [
        'includes'  => ['php'],
        'templates' => ['php'],
        // Sources (.po) and the working translation map stay out: the runtime
        // reads the compiled catalogues, and the .pot is for translators who
        // will have the repository anyway.
        'languages' => ['mo', 'json', 'pot'],
        'assets'    => ['js', 'css', 'php', 'svg', 'png'],
    ],
];

/**
 * Build outputs that must exist, and the source tree each is built from.
 *
 * The value is what to rebuild with when the check fails, so the error names
 * the fix rather than the symptom.
 */
const BUILT = [
    'assets/js/app.js'         => ['src', 'npm run build'],
    'assets/js/app.asset.php'  => ['src', 'npm run build'],
    'assets/css/app.css'       => ['src', 'npm run build'],
    'assets/css/app-rtl.css'   => ['src', 'npm run build'],
    'assets/js/editor.js'      => ['src', 'npm run build'],
    'assets/css/editor.css'    => ['src', 'npm run build'],
];

$root  = dirname(__DIR__);
$check = in_array('--check', $argv, true);

$version  = version($root);
$problems = array_merge(versionProblems($root, $version), buildProblems($root));

if ($problems !== []) {
    fwrite(STDERR, "Not ready to package:\n");

    foreach ($problems as $problem) {
        fwrite(STDERR, "  - {$problem}\n");
    }

    exit(1);
}

$manifest = manifest($root);

printf("Version %s, %d files, %s.\n", $version, count($manifest), size(bytes($root, $manifest)));

if ($check) {
    print("Ready to package.\n");
    exit(0);
}

$dist = $root . '/dist';

if (! is_dir($dist) && ! mkdir($dist, 0o755, true) && ! is_dir($dist)) {
    fwrite(STDERR, "Cannot create {$dist}\n");
    exit(1);
}

$path = sprintf('%s/%s-%s.zip', $dist, SLUG, $version);

if (file_exists($path) && ! unlink($path)) {
    fwrite(STDERR, "Cannot replace {$path}\n");
    exit(1);
}

$zip = new ZipArchive();

if ($zip->open($path, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot write {$path}\n");
    exit(1);
}

// WordPress installs whatever top-level directory the archive contains, so
// every entry is prefixed with the slug. Without it the plugin unpacks into
// wp-content/plugins/ itself and overwrites its neighbours.
foreach ($manifest as $relative) {
    $zip->addFile($root . '/' . $relative, SLUG . '/' . $relative);
}

$zip->close();

printf(
    "\n%s\n  %s\n",
    'Wrote ' . size((int) filesize($path)) . ' to:',
    str_replace($root . '/', '', $path)
);

print("\nInstall it with Plugins → Add New → Upload Plugin.\n");

exit(0);

// ---------------------------------------------------------------------------

function version(string $root): string
{
    $header = (string) file_get_contents($root . '/' . SLUG . '.php');

    if (preg_match('/^\s*\*\s*Version:\s*(\S+)$/mi', $header, $m) !== 1) {
        fwrite(STDERR, "No Version header in " . SLUG . ".php\n");
        exit(1);
    }

    return $m[1];
}

/**
 * The same version is written in four places. They disagree eventually, and
 * the one that matters — the header WordPress reads for update checks — is not
 * the one anyone looks at.
 *
 * @return list<string>
 */
function versionProblems(string $root, string $version): array
{
    $sources = [
        'MEDORA_VERSION'      => ['/' . SLUG . '.php', "/const MEDORA_VERSION\s*=\s*'([^']+)'/"],
        'package.json'        => ['/package.json', '/"version":\s*"([^"]+)"/'],
        'readme.txt Stable tag' => ['/readme.txt', '/^Stable tag:\s*(\S+)$/mi'],
    ];

    $problems = [];

    foreach ($sources as $label => [$file, $pattern]) {
        $contents = (string) file_get_contents($root . $file);

        if (preg_match($pattern, $contents, $m) !== 1) {
            $problems[] = "{$label}: no version found";
            continue;
        }

        if ($m[1] !== $version) {
            $problems[] = "{$label} is {$m[1]}, plugin header says {$version}";
        }
    }

    $changelog = (string) file_get_contents($root . '/CHANGELOG.md');

    if (! str_contains($changelog, '[' . $version . ']') && ! str_contains($changelog, '## ' . $version)) {
        $problems[] = "CHANGELOG.md has no entry for {$version}";
    }

    return $problems;
}

/**
 * @return list<string>
 */
function buildProblems(string $root): array
{
    $problems = [];

    foreach (BUILT as $artifact => [$sourceDir, $command]) {
        $path = $root . '/' . $artifact;

        if (! is_file($path)) {
            $problems[] = "{$artifact} is missing — run: {$command}";
            continue;
        }

        $newest = newestMtime($root . '/' . $sourceDir);

        if ($newest > filemtime($path)) {
            $problems[] = sprintf(
                '%s is older than %s/ — run: %s',
                $artifact,
                $sourceDir,
                $command
            );
        }
    }

    // The catalogues are built from the .po files by a different command, so
    // they get their own comparison rather than being lumped in above.
    foreach (glob($root . '/languages/*.po') ?: [] as $po) {
        $locale = (string) preg_replace('/^' . SLUG . '-|\.po$/', '', basename($po));
        $mo     = $root . '/languages/' . SLUG . '-' . $locale . '.mo';

        if (! is_file($mo)) {
            $problems[] = "languages/" . SLUG . "-{$locale}.mo is missing — run: composer i18n:build";
            continue;
        }

        if (filemtime($po) > filemtime($mo)) {
            $problems[] = "languages/" . SLUG . "-{$locale}.mo is older than its .po — run: composer i18n:build";
        }
    }

    return $problems;
}

function newestMtime(string $dir): int
{
    if (! is_dir($dir)) {
        return 0;
    }

    $newest   = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $newest = max($newest, (int) $file->getMTime());
        }
    }

    return $newest;
}

/**
 * @return list<string>
 */
function manifest(string $root): array
{
    $files = [];

    foreach (SHIP['files'] as $file) {
        if (is_file($root . '/' . $file)) {
            $files[] = $file;
        }
    }

    foreach (SHIP['dirs'] as $dir => $extensions) {
        if (! is_dir($root . '/' . $dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $relative = str_replace($root . '/', '', $file->getPathname());

            // The working translation map is a build input, and a large one.
            if (str_ends_with($relative, '.map.php')) {
                continue;
            }

            $files[] = $relative;
        }
    }

    sort($files);

    return $files;
}

/**
 * @param list<string> $manifest
 */
function bytes(string $root, array $manifest): int
{
    $total = 0;

    foreach ($manifest as $file) {
        $total += (int) filesize($root . '/' . $file);
    }

    return $total;
}

function size(int $bytes): string
{
    return $bytes >= 1024 * 1024
        ? sprintf('%.1f MB', $bytes / 1024 / 1024)
        : sprintf('%.0f KB', $bytes / 1024);
}

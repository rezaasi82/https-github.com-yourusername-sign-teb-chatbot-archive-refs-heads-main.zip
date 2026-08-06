<?php
/**
 * Release builder: produces signteb-login-release.zip with the PHP
 * logic compressed and encoded so the shipped source is not readable
 * or reusable as-is. Run from anywhere:
 *
 *     php signteb-login/bin/build.php
 *
 * The readable source stays in the internal repository only; the zip
 * this script emits is what gets installed on client sites.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$plugin_dir = dirname(__DIR__);
$output     = dirname($plugin_dir) . '/signteb-login-release.zip';

$encoded_files = [
    'includes/class-signteb-login.php',
    'includes/class-signteb-login-settings.php',
    'includes/class-signteb-login-slug.php',
];

$plain_files = [
    'signteb-login.php',
    'index.php',
    'assets/index.php',
    'assets/css/index.php',
    'assets/css/login.css',
    'includes/index.php',
];

function encode_php(string $path): string
{
    $code = php_strip_whitespace($path);
    $code = preg_replace('/^<\?php\s*/', '', $code);

    return "<?php if(!defined('ABSPATH'))exit;eval(gzinflate(base64_decode('"
        . base64_encode(gzdeflate($code, 9))
        . "')));\n";
}

$zip = new ZipArchive();

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "cannot create {$output}\n");
    exit(1);
}

foreach ($plain_files as $file) {
    $zip->addFile($plugin_dir . '/' . $file, 'signteb-login/' . $file);
}

foreach ($encoded_files as $file) {
    $zip->addFromString('signteb-login/' . $file, encode_php($plugin_dir . '/' . $file));
}

$zip->close();

echo "release: {$output}\n";

<?php

namespace SignTeb\VideoHub\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Cache-busting version strings for enqueued assets.
 *
 * Using the plugin version alone is a trap: edit a CSS or JS file without
 * bumping it and the URL stays byte-identical, so browsers and page caches
 * keep serving the old copy and the change silently never ships. Appending
 * the file's modification time makes a changed file always produce a new URL,
 * whether or not anyone remembered to bump the version.
 */
class Asset
{
    /**
     * @param string $relative_path Path under the plugin root, e.g. 'assets/js/admin.js'.
     */
    public static function version(string $relative_path): string
    {
        $file = STVH_DIR . ltrim($relative_path, '/');

        if (! file_exists($file)) {
            return STVH_VERSION;
        }

        $mtime = filemtime($file);

        return $mtime === false
            ? STVH_VERSION
            : STVH_VERSION . '.' . $mtime;
    }

    /**
     * Public URL for an asset under the plugin root.
     */
    public static function url(string $relative_path): string
    {
        return STVH_URL . ltrim($relative_path, '/');
    }
}

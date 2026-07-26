<?php
/**
 * Protects JSON responses from stray PHP notices.
 *
 * @package Pazira
 */

namespace Pazira\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Some shared hosts emit PHP notices/warnings that corrupt a JSON body. Arm the
 * buffer before producing output; the shutdown handler discards any leaked bytes
 * so the response stays valid JSON.
 */
class JsonGuard
{
    private static bool $armed = false;

    public static function arm(): void
    {
        if (self::$armed) {
            return;
        }
        self::$armed = true;

        if (! headers_sent()) {
            ob_start();
        }

        register_shutdown_function(static function (): void {
            if (ob_get_level() > 0) {
                @ob_end_clean();
            }
        });
    }
}

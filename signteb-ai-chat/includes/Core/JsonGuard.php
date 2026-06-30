<?php

namespace STMC_Chat\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Protects JSON responses from stray PHP notices/warnings that some Iranian
 * shared hosts emit (per the mother project's hard-won lesson). Start the
 * buffer before producing output; the shutdown handler discards any leaked
 * bytes so the JSON body stays valid.
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
            // Drop anything the request accidentally printed before the JSON.
            if (ob_get_level() > 0) {
                @ob_end_clean();
            }
        });
    }
}

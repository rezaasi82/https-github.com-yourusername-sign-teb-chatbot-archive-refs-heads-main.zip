<?php
/**
 * SWC_Json_Guard — protects JSON responses from stray PHP notices.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Some cheap shared hosts emit notices/warnings that corrupt a JSON body. Arm
 * the buffer before producing output; the shutdown handler discards any leaked
 * bytes so the JSON stays valid (a hard-won lesson from earlier SignTeb work).
 */
class SWC_Json_Guard
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

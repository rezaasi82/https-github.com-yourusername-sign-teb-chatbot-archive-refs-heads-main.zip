<?php

namespace SignTeb\VideoHub\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ring-buffer log kept in a single option (last 50 entries).
 *
 * The dashboard's "آخرین خطاها" panel reads this. Deliberately option-based
 * rather than a table: errors are few, bounded, and must survive on hosts
 * where WP_DEBUG_LOG is off.
 */
class Logger
{
    private const OPTION = 'stvh_log';
    private const LIMIT  = 50;

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::write('error', $channel, $message, $context);
    }

    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::write('warning', $channel, $message, $context);
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::write('info', $channel, $message, $context);
    }

    private static function write(string $level, string $channel, string $message, array $context): void
    {
        $entries = self::all();
        array_unshift($entries, [
            'level'   => $level,
            'channel' => $channel,
            'message' => mb_substr($message, 0, 500),
            'context' => array_map(
                static fn($v): string => mb_substr(is_scalar($v) ? (string) $v : wp_json_encode($v), 0, 200),
                $context
            ),
            'time'    => current_time('mysql'),
        ]);

        update_option(self::OPTION, array_slice($entries, 0, self::LIMIT), false);

        if (defined('WP_DEBUG') && WP_DEBUG && $level === 'error') {
            error_log(sprintf('[SignTeb Video Hub][%s] %s', $channel, $message));
        }
    }

    /**
     * @return array<int,array{level:string,channel:string,message:string,context:array,time:string}>
     */
    public static function all(): array
    {
        $entries = get_option(self::OPTION, []);
        return is_array($entries) ? $entries : [];
    }

    /**
     * @return array<int,array{level:string,channel:string,message:string,context:array,time:string}>
     */
    public static function recent(int $limit = 10, string $level = ''): array
    {
        $entries = self::all();
        if ($level !== '') {
            $entries = array_values(array_filter($entries, static fn(array $e): bool => ($e['level'] ?? '') === $level));
        }
        return array_slice($entries, 0, $limit);
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }
}

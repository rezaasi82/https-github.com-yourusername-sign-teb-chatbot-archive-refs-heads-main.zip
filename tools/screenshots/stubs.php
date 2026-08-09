<?php
namespace Pezhkam\Core;

/** Request bag stand-in. */
class Input
{
    public static array $bag = [];
    public static function get_key(string $k, $d = '') { return self::$bag[$k] ?? $d; }
    public static function post_key(string $k, $d = '') { return $d; }
    public static function request_uri(): string { return '/'; }
    public static function has_get(string $k): bool { return isset(self::$bag[$k]); }
}

/** Settings double: believable demo values for the screenshots. */
class Settings
{
    public array $data = [];
    public function get($k, $d = null) { return $this->data[$k] ?? $d; }
    public function is_enabled(): bool { return true; }
    public function is_float_enabled(): bool { return true; }
    public function is_shortcode_enabled(): bool { return true; }
    public function active_provider(): string { return 'anthropic'; }
    public function has_api_key($p = null): bool { return true; }
}

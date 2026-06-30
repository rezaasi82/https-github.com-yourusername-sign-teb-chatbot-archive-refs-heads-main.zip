<?php

namespace STMC_Chat\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thin, typed accessor over the stmc_chat_settings option array.
 * The API key is stored separately, encrypted (see Encryption + get_api_key()).
 */
class Settings
{
    private const OPTION = 'stmc_chat_settings';

    private array $data;

    public function __construct()
    {
        $stored     = get_option(self::OPTION, []);
        $this->data = is_array($stored) ? $stored : [];
    }

    public function get(string $key, mixed $default = ''): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function is_enabled(): bool
    {
        return (bool) $this->get('enabled', 1);
    }

    /**
     * Decrypted API key for the active provider, or '' if unset.
     */
    public function get_api_key(): string
    {
        $encrypted = get_option('stmc_chat_api_key_enc', '');
        if (! is_string($encrypted) || $encrypted === '') {
            return '';
        }
        return Encryption::decrypt($encrypted);
    }

    public static function save_api_key(string $plain): void
    {
        $plain = trim($plain);
        if ($plain === '') {
            delete_option('stmc_chat_api_key_enc');
            return;
        }
        update_option('stmc_chat_api_key_enc', Encryption::encrypt($plain), false);
    }

    public function has_api_key(): bool
    {
        return $this->get_api_key() !== '';
    }
}

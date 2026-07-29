<?php
/**
 * Typed accessor over the clx_settings option array.
 *
 * Provider API keys are stored in their own options, encrypted (see
 * Encryption), one per provider so the admin can configure both Anthropic
 * and OpenAI and switch between them without re-entering keys.
 *
 * @package Clinovix
 */

namespace Clinovix\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Settings
{
    public const OPTION = 'clx_settings';

    /** @var array<string,string> provider => option name holding the encrypted key */
    private const KEY_OPTIONS = [
        'anthropic' => 'clx_api_key_anthropic_enc',
        'openai'    => 'clx_api_key_openai_enc',
        'gapgpt'    => 'clx_api_key_gapgpt_enc',
    ];

    private array $data;

    public function __construct()
    {
        $stored     = get_option(self::OPTION, []);
        $this->data = is_array($stored) ? $stored : [];
    }

    public function get(string $key, mixed $default = ''): mixed
    {
        $value = $this->data[$key] ?? $default;
        return $value === '' ? $default : $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * True when the chat engine should run at all, i.e. when at least one of
     * the two display modes is on.
     */
    public function is_enabled(): bool
    {
        return $this->is_float_enabled() || $this->is_shortcode_enabled();
    }

    /** Floating launcher on every page of the site. */
    public function is_float_enabled(): bool
    {
        return (bool) ($this->data['float_enabled'] ?? $this->data['enabled'] ?? 1);
    }

    /** The [clinovix_chat] shortcode / block embed. */
    public function is_shortcode_enabled(): bool
    {
        return (bool) ($this->data['shortcode_enabled'] ?? $this->data['enabled'] ?? 1);
    }

    public function active_provider(): string
    {
        $provider = (string) ($this->data['provider'] ?? 'anthropic');
        return isset(self::KEY_OPTIONS[$provider]) ? $provider : 'anthropic';
    }

    /**
     * Model id configured for the active provider, with sane defaults.
     */
    public function active_model(): string
    {
        return (new \Clinovix\Ai\ProviderFactory($this))->model_for($this->active_provider());
    }

    /**
     * Decrypted API key for a provider (defaults to the active one).
     */
    public function get_api_key(?string $provider = null): string
    {
        $provider = $provider ?? $this->active_provider();
        $option   = self::KEY_OPTIONS[$provider] ?? '';
        if ($option === '') {
            return '';
        }
        $encrypted = get_option($option, '');
        return is_string($encrypted) ? \Clinovix\Core\Encryption::decrypt($encrypted) : '';
    }

    public function has_api_key(?string $provider = null): bool
    {
        return $this->get_api_key($provider) !== '';
    }

    public static function save_api_key(string $provider, string $plain): void
    {
        $option = self::KEY_OPTIONS[$provider] ?? '';
        if ($option === '') {
            return;
        }
        $plain = trim($plain);
        if ($plain === '') {
            delete_option($option);
            return;
        }
        update_option($option, \Clinovix\Core\Encryption::encrypt($plain), false);
    }
}

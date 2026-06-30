<?php
/**
 * SWC_Settings — typed accessor over the swc_settings option array.
 *
 * Provider API keys are stored in their own options, encrypted (see
 * SWC_Encryption), one per provider so the admin can configure both Anthropic
 * and OpenAI and switch between them without re-entering keys.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Settings
{
    public const OPTION = 'swc_settings';

    /** @var array<string,string> provider => option name holding the encrypted key */
    private const KEY_OPTIONS = [
        'anthropic' => 'swc_api_key_anthropic_enc',
        'openai'    => 'swc_api_key_openai_enc',
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

    public function is_enabled(): bool
    {
        return (bool) ($this->data['enabled'] ?? 1);
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
        $provider = $this->active_provider();
        $model    = trim((string) ($this->data[ 'model_' . $provider ] ?? ''));
        if ($model !== '') {
            return $model;
        }
        return $provider === 'openai' ? 'gpt-4o-mini' : 'claude-haiku-4-5-20251001';
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
        return is_string($encrypted) ? SWC_Encryption::decrypt($encrypted) : '';
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
        update_option($option, SWC_Encryption::encrypt($plain), false);
    }
}

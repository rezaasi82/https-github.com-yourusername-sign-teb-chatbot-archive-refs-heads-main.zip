<?php
/**
 * Builds AI providers from the stored settings.
 *
 * The single place that knows which concrete provider class maps to each id,
 * so the rest of the plugin depends only on the AiProviderInterface
 * abstraction and never needs to change when a provider is added.
 *
 * @package Medora
 */

namespace Medora\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderFactory
{
    /** Default model per provider when the admin hasn't chosen one. */
    private const DEFAULT_MODELS = [
        'anthropic' => 'claude-haiku-4-5-20251001',
        'openai'    => 'gpt-4o-mini',
        'gapgpt'    => 'gpt-4o-mini',
    ];

    /** Order in which a fallback provider is tried when the primary fails. */
    private const FALLBACK_ORDER = ['gapgpt', 'anthropic', 'openai'];

    private \Medora\Core\Settings $settings;

    public function __construct(\Medora\Core\Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Create a provider by id, or null when its API key is not configured.
     */
    public function create(string $id): ?\Medora\Ai\AiProviderInterface
    {
        $key = $this->settings->get_api_key($id);
        if ($key === '') {
            return null;
        }
        switch ($id) {
            case 'openai':
                return new \Medora\Ai\ProviderOpenai($key);
            case 'gapgpt':
                return new \Medora\Ai\ProviderGapgpt($key);
            case 'anthropic':
                return new \Medora\Ai\ProviderAnthropic($key);
            default:
                return null;
        }
    }

    public function create_active(): ?\Medora\Ai\AiProviderInterface
    {
        return $this->create($this->settings->active_provider());
    }

    /**
     * First configured provider other than the one that just failed.
     */
    public function create_fallback(string $primary_id): ?\Medora\Ai\AiProviderInterface
    {
        foreach (self::FALLBACK_ORDER as $id) {
            if ($id === $primary_id) {
                continue;
            }
            $provider = $this->create($id);
            if ($provider !== null) {
                return $provider;
            }
        }
        return null;
    }

    public function default_model(string $id): string
    {
        return self::DEFAULT_MODELS[$id] ?? self::DEFAULT_MODELS['openai'];
    }

    /**
     * Configured model for a provider, falling back to its default.
     */
    public function model_for(string $id): string
    {
        $model = trim((string) $this->settings->get('model_' . $id, ''));
        return $model !== '' ? $model : $this->default_model($id);
    }
}

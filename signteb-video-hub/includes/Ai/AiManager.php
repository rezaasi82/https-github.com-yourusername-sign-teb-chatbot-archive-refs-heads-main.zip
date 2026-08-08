<?php

namespace SignTeb\VideoHub\Ai;

use SignTeb\VideoHub\Ai\Providers\AnthropicProvider;
use SignTeb\VideoHub\Ai\Providers\GapGptProvider;
use SignTeb\VideoHub\Ai\Providers\GeminiProvider;
use SignTeb\VideoHub\Ai\Providers\OpenAiProvider;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds the configured provider and carries the shared medical guard-rails
 * that every generator inherits.
 */
class AiManager
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function is_enabled(): bool
    {
        return $this->settings->bool('ai_enabled') && $this->settings->has_secret('ai_api_key');
    }

    public function provider(): ?AiProviderInterface
    {
        $key = $this->settings->secret('ai_api_key');
        if ($key === '') {
            return null;
        }

        $base = $this->settings->str('ai_base_url');

        $provider = match ($this->settings->str('ai_provider')) {
            'gapgpt' => new GapGptProvider($key, $base),
            'gemini' => new GeminiProvider($key, $base),
            'openai' => new OpenAiProvider($key, $base),
            default  => new AnthropicProvider($key, $base),
        };

        /**
         * Swap in a custom AI provider.
         *
         * @param AiProviderInterface $provider
         */
        $filtered = apply_filters('stvh_ai_provider', $provider, $this->settings);

        return $filtered instanceof AiProviderInterface ? $filtered : $provider;
    }

    /**
     * One-shot prompt helper used by all generators.
     *
     * @param array{max_tokens?:int,temperature?:float} $options
     *
     * @return array{ok:bool,content?:string,error?:string}
     */
    public function ask(string $system, string $prompt, array $options = []): array
    {
        if (! $this->is_enabled()) {
            return ['ok' => false, 'error' => 'هوش مصنوعی غیرفعال است یا کلید API ندارد.'];
        }

        $provider = $this->provider();
        if ($provider === null) {
            return ['ok' => false, 'error' => 'سرویس‌دهنده هوش مصنوعی در دسترس نیست.'];
        }

        $model = $this->settings->str('ai_model');

        return $provider->complete(
            $system,
            [['role' => 'user', 'content' => $prompt]],
            array_merge(
                ['model' => $model !== '' ? $model : $provider->default_model()],
                $options
            )
        );
    }

    /**
     * Shared system prompt. Medical content must stay educational and never
     * read as a diagnosis, so the constraint lives here rather than being
     * re-written by each generator.
     */
    public function system_prompt(string $role): string
    {
        $clinic    = $this->settings->str('clinic_name');
        $physician = $this->settings->str('physician_name');
        $specialty = $this->settings->str('physician_specialty');

        $context = 'تو دستیار تولید محتوای یک وب‌سایت پزشکی فارسی‌زبان هستی.';
        if ($physician !== '') {
            $context .= sprintf(' پزشک مسئول: %s%s.', $physician, $specialty !== '' ? '، ' . $specialty : '');
        }
        if ($clinic !== '') {
            $context .= sprintf(' نام مرکز: %s.', $clinic);
        }

        $rules = implode("\n", [
            '- فقط فارسی روان و حرفه‌ای بنویس.',
            '- لحن آموزشی و قابل‌فهم برای بیمار باشد، نه گزارش تخصصی.',
            '- هرگز تشخیص قطعی، دوز دارو یا دستور درمان نده.',
            '- ادعای درمان قطعی نکن و آمار بی‌منبع نساز.',
            '- در پایان توصیه به مراجعه حضوری به پزشک را در نظر داشته باش.',
            '- دقیقاً در قالب خواسته‌شده پاسخ بده و متن اضافه ننویس.',
        ]);

        /**
         * Adjust the shared AI system prompt.
         *
         * @param string $prompt
         * @param string $role   Generator id: summary | links | article.
         */
        return apply_filters('stvh_ai_system_prompt', $context . "\n\n" . $rules, $role);
    }

    /**
     * Credentials probe for the settings screen.
     *
     * @return array{ok:bool,message:string}
     */
    public function test_connection(): array
    {
        if (! $this->settings->has_secret('ai_api_key')) {
            return ['ok' => false, 'message' => 'کلید API هوش مصنوعی وارد نشده است.'];
        }

        $provider = $this->provider();
        if ($provider === null) {
            return ['ok' => false, 'message' => 'سرویس‌دهنده در دسترس نیست.'];
        }

        $model  = $this->settings->str('ai_model');
        $result = $provider->complete(
            'به فارسی و تنها با یک کلمه پاسخ بده.',
            [['role' => 'user', 'content' => 'بنویس: سالم']],
            ['max_tokens' => 16, 'model' => $model !== '' ? $model : $provider->default_model()]
        );

        return $result['ok']
            ? ['ok' => true, 'message' => 'اتصال به سرویس هوش مصنوعی برقرار است.']
            : ['ok' => false, 'message' => (string) ($result['error'] ?? 'اتصال برقرار نشد.')];
    }
}

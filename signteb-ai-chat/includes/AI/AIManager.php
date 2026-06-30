<?php

namespace STMC_Chat\AI;

use STMC_Chat\AI\Providers\AnthropicProvider;
use STMC_Chat\AI\Providers\OpenAIProvider;
use STMC_Chat\Core\Settings;
use STMC_Chat\Database\ConversationRepository;
use STMC_Chat\Database\MessageRepository;
use STMC_Chat\Integration\MedicalCoreBridge;
use STMC_Chat\License\LicenseManager;
use STMC_Chat\RateLimit\RateLimiter;
use STMC_Chat\Safety\MedicalSafetyFilter;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The conversation engine. Coordinates rate limiting, the safety layer, the
 * dynamic system prompt, the swappable AI provider, persistence, and CTA/lead
 * detection. This is the single entry point used by both the REST controller
 * and the admin-ajax handler.
 */
class AIManager
{
    private Settings $settings;
    private MedicalCoreBridge $bridge;
    private MedicalSafetyFilter $safety;
    private SystemPromptBuilder $prompt;
    private LanguageDetector $language;
    private CtaDetector $cta;
    private ConversationRepository $conversations;
    private MessageRepository $messages;

    public function __construct()
    {
        $this->settings      = new Settings();
        $this->bridge        = new MedicalCoreBridge($this->settings);
        $this->safety        = new MedicalSafetyFilter($this->settings);
        $this->prompt        = new SystemPromptBuilder($this->settings, $this->bridge);
        $this->language      = new LanguageDetector();
        $this->cta           = new CtaDetector();
        $this->conversations = new ConversationRepository();
        $this->messages      = new MessageRepository();
    }

    /**
     * Handle one inbound message and produce a structured reply.
     *
     * @param array{session_id:string,message:string,ip:string,page_url:string,user_id:?int} $req
     *
     * @return array{ok:bool,reply?:string,cta?:string,cta_card?:array,error?:string,code?:string}
     */
    public function handle(array $req): array
    {
        if (! $this->settings->is_enabled()) {
            return ['ok' => false, 'code' => 'disabled', 'error' => 'چت‌بات غیرفعال است.'];
        }

        if (! (new LicenseManager())->is_valid()) {
            return ['ok' => false, 'code' => 'license', 'error' => 'لایسنس معتبر نیست.'];
        }

        $message = trim((string) $req['message']);
        if ($message === '') {
            return ['ok' => false, 'code' => 'empty', 'error' => 'پیام خالی است.'];
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        // --- Rate limit (per IP+session) ---
        $limiter = new RateLimiter((int) $this->settings->get('rate_limit_per_min', 8));
        if (! $limiter->allow($req['ip'] . '|' . $req['session_id'])) {
            return ['ok' => false, 'code' => 'rate_limited', 'error' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.'];
        }

        $lang = $this->language->resolve((string) $this->settings->get('language', 'auto'), $message);

        $conversation_id = $this->conversations->find_or_create($req['session_id'], [
            'ip'       => $req['ip'],
            'user_id'  => $req['user_id'],
            'language' => $lang,
            'page_url' => $req['page_url'],
        ]);

        // Persist the user turn early so history is complete even on failure.
        $this->messages->add($conversation_id, 'user', $message);
        $this->conversations->touch($conversation_id);

        // --- Safety: emergency short-circuit (no AI call) ---
        $screen = $this->safety->screen_input($message);
        if ($screen['emergency']) {
            $reply = $screen['reply'];
            $this->messages->add($conversation_id, 'assistant', $reply, true);
            return ['ok' => true, 'reply' => $reply, 'cta' => '', 'emergency' => true];
        }

        // --- Build provider + call ---
        $provider = $this->make_provider();
        if ($provider === null) {
            return $this->graceful_fallback($conversation_id, 'no_provider');
        }

        $system  = $this->prompt->build($lang);
        $history = $this->messages->history($conversation_id, 12);

        $result = $provider->complete($system, $history, [
            'model'      => (string) $this->settings->get('model', 'claude-opus-4-8'),
            'max_tokens' => 1024,
        ]);

        if (empty($result['ok'])) {
            // Try OpenAI fallback if configured and primary failed.
            $fallback = $this->make_fallback_provider($provider->id());
            if ($fallback !== null) {
                $result = $fallback->complete($system, $history, ['max_tokens' => 1024]);
            }
        }

        if (empty($result['ok'])) {
            return $this->graceful_fallback($conversation_id, $result['error'] ?? 'api_error');
        }

        // --- Safety: post-process reply ---
        $reply = $this->safety->filter_output((string) $result['content'], $message);

        // --- CTA / lead detection ---
        $cta = $this->cta->detect($message, $reply);
        if ($cta !== '') {
            $this->conversations->mark_lead($conversation_id, $cta);
            do_action('stmc_chat_lead_detected', $conversation_id, $cta);
        }

        $this->messages->add($conversation_id, 'assistant', $reply, false, $result['tokens'] ?? null);

        return [
            'ok'       => true,
            'reply'    => $reply,
            'cta'      => $cta,
            'cta_card' => $cta !== '' ? $this->cta_card($cta) : null,
        ];
    }

    private function make_provider(): ?\STMC_Chat\AI\AIProviderInterface
    {
        $key = $this->settings->get_api_key();
        if ($key === '') {
            return null;
        }
        return $this->settings->get('provider', 'anthropic') === 'openai'
            ? new OpenAIProvider($key)
            : new AnthropicProvider($key);
    }

    private function make_fallback_provider(string $primary_id): ?\STMC_Chat\AI\AIProviderInterface
    {
        $fallback_key = get_option('stmc_chat_fallback_key_enc', '');
        if (! is_string($fallback_key) || $fallback_key === '') {
            return null;
        }
        $key = \STMC_Chat\Core\Encryption::decrypt($fallback_key);
        if ($key === '') {
            return null;
        }
        return $primary_id === 'anthropic' ? new OpenAIProvider($key) : new AnthropicProvider($key);
    }

    private function graceful_fallback(int $conversation_id, string $reason): array
    {
        $nap   = $this->bridge->nap();
        $phone = $nap['phone'] !== '' ? " یا با شماره {$nap['phone']} تماس بگیرید" : '';
        $reply = "پوزش می‌خواهم، در حال حاضر امکان پاسخ‌گویی هوشمند نیست. لطفاً چند لحظه دیگر دوباره تلاش کنید{$phone}.";

        $this->messages->add($conversation_id, 'assistant', $reply, false);

        return ['ok' => true, 'reply' => $reply, 'cta' => 'contact', 'cta_card' => $this->cta_card('contact'), 'soft_error' => $reason];
    }

    /**
     * @return array{type:string,booking_url:string,whatsapp:string,phone:string}
     */
    private function cta_card(string $type): array
    {
        $nap = $this->bridge->nap();
        return [
            'type'        => $type,
            'booking_url' => $nap['booking_url'],
            'whatsapp'    => $nap['whatsapp'],
            'phone'       => $nap['phone'],
        ];
    }
}

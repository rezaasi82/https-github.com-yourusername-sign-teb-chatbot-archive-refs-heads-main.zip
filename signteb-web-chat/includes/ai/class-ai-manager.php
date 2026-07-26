<?php
/**
 * The conversation engine.
 *
 * The single entry point used by both the REST controller and the admin-ajax
 * handler. Coordinates rate limiting, the safety
 * layer, the dynamic system prompt, the swappable AI provider, persistence,
 * and CTA/lead detection. Every failure path returns a structured fallback
 * rather than an error.
 *
 * @package Medora
 */

namespace Medora\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class AiManager
{
    private \Medora\Core\Settings $settings;
    private \Medora\Safety\MedicalSafetyFilter $safety;
    private \Medora\Ai\SystemPromptBuilder $prompt;
    private \Medora\Ai\LanguageDetector $language;
    private \Medora\Ai\CtaDetector $cta;
    private \Medora\Ai\LeadScorer $scorer;
    private \Medora\Ai\SummaryBuilder $summary;
    private \Medora\Ai\ProviderFactory $providers;
    private \Medora\Database\ConversationRepository $conversations;
    private \Medora\Database\MessageRepository $messages;

    public function __construct()
    {
        $this->settings      = new \Medora\Core\Settings();
        $this->safety        = new \Medora\Safety\MedicalSafetyFilter($this->settings);
        $this->prompt        = new \Medora\Ai\SystemPromptBuilder($this->settings);
        $this->language      = new \Medora\Ai\LanguageDetector();
        $this->cta           = new \Medora\Ai\CtaDetector();
        $this->scorer        = new \Medora\Ai\LeadScorer();
        $this->summary       = new \Medora\Ai\SummaryBuilder($this->settings);
        $this->providers     = new \Medora\Ai\ProviderFactory($this->settings);
        $this->conversations = new \Medora\Database\ConversationRepository();
        $this->messages      = new \Medora\Database\MessageRepository();
    }

    /**
     * Handle one inbound message.
     *
     * @param array{session_id:string,message:string,ip:string,page_url:string,user_id:?int} $req
     * @return array{ok:bool,reply?:string,cta?:string,cta_card?:?array,error?:string,code?:string}
     */
    public function handle(array $req): array
    {
        if (! $this->settings->is_enabled()) {
            return ['ok' => false, 'code' => 'disabled', 'error' => __('چت‌بات غیرفعال است.', 'signteb-web-chat')];
        }

        $message = trim((string) $req['message']);
        if ($message === '') {
            return ['ok' => false, 'code' => 'empty', 'error' => __('پیام خالی است.', 'signteb-web-chat')];
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        // --- Rate limit (per IP + session) ---
        $limiter = new \Medora\Ratelimit\RateLimiter((int) $this->settings->get('rate_limit_per_min', 8));
        if (! $limiter->allow($req['ip'] . '|' . $req['session_id'])) {
            return ['ok' => false, 'code' => 'rate_limited', 'error' => __('لطفاً کمی صبر کنید و دوباره تلاش کنید.', 'signteb-web-chat')];
        }

        $lang = $this->language->resolve((string) $this->settings->get('language', 'auto'), $message);

        $conversation_id = $this->conversations->find_or_create($req['session_id'], [
            'ip'        => $req['ip'],
            'user_id'   => $req['user_id'],
            'language'  => $lang,
            'page_url'  => $req['page_url'],
            'branch_id' => (int) ($req['branch'] ?? 0),
        ]);

        // Lead capture: persist any patient identity sent with the request.
        $patient_name  = trim((string) ($req['name'] ?? ''));
        $patient_phone = trim((string) ($req['phone'] ?? ''));
        if ($patient_name !== '' || $patient_phone !== '') {
            $this->conversations->set_patient($conversation_id, $patient_name, $patient_phone);
        }

        // Persist the user turn early so history stays complete even on failure.
        $this->messages->add($conversation_id, 'user', $message);
        $this->conversations->touch($conversation_id);

        // --- Safety: emergency short-circuit (no AI call) ---
        $screen = $this->safety->screen_input($message);
        if ($screen['emergency']) {
            $reply = $screen['reply'];
            $this->messages->add($conversation_id, 'assistant', $reply, true);
            $this->conversations->mark_lead($conversation_id, 'contact');
            return ['ok' => true, 'reply' => $reply, 'cta' => 'contact', 'cta_card' => $this->cta_card('contact'), 'emergency' => true];
        }

        // --- Provider call ---
        $provider = $this->providers->create_active();
        if ($provider === null) {
            return $this->graceful_fallback($conversation_id, 'no_provider');
        }

        $conversation = $this->conversations->get($conversation_id);
        $known_name   = $conversation ? (string) ($conversation->patient_name ?? '') : $patient_name;

        $context = [
            'system'      => $this->prompt->build($lang, $known_name),
            'history'     => $this->messages->history($conversation_id, 20),
            'model'       => $this->settings->active_model(),
            'max_tokens'  => 1200,
            'temperature' => (float) apply_filters('swc_temperature', 0.8),
        ];

        $result = $provider->generate_reply($message, $context);

        if (empty($result['ok'])) {
            // Try the other provider as a fallback if its key is configured.
            $fallback = $this->providers->create_fallback($provider->id());
            if ($fallback !== null) {
                $context['model'] = $this->providers->model_for($fallback->id());
                $result           = $fallback->generate_reply($message, $context);
            }
        }

        if (empty($result['ok'])) {
            return $this->graceful_fallback($conversation_id, (string) ($result['error'] ?? 'api_error'));
        }

        // --- Safety: post-process the reply ---
        $reply = $this->safety->filter_output((string) $result['content'], $message);

        // --- CTA / lead detection ---
        $cta = $this->cta->detect($message, $reply);
        if ($cta !== '') {
            $this->conversations->mark_lead($conversation_id, $cta);
            do_action('swc_lead_detected', $conversation_id, $cta);
        }

        $this->messages->add($conversation_id, 'assistant', $reply, false, $result['tokens'] ?? null);

        // --- AI lead scoring + auto-summary (heuristic, no extra API call) ---
        $score = $this->score_and_summarize($conversation_id, $cta, $known_name, (string) ($conversation->patient_phone ?? $patient_phone));
        do_action('swc_message_handled', $conversation_id, $cta, $score['level']);

        // --- Smart appointment trigger: only surface the booking card when the
        // visitor shows genuine readiness (explicit booking intent, or a warm/
        // hot lead) — never push it on a cold, purely-informational chat.
        $show_card = $cta !== '' && ($cta === 'booking' || in_array($score['level'], ['hot', 'warm'], true));

        return [
            'ok'              => true,
            'reply'           => $reply,
            'cta'             => $cta,
            'cta_card'        => $show_card ? $this->cta_card($cta) : null,
            'lead'            => ['level' => $score['level'], 'label' => $score['label'], 'emoji' => $score['emoji']],
            'conversation_id' => $conversation_id,
        ];
    }

    /**
     * Re-score the conversation and refresh its stored summary.
     *
     * @return array{level:string,label:string,emoji:string,probability:int}
     */
    private function score_and_summarize(int $conversation_id, string $cta, string $name, string $phone): array
    {
        $user_texts = $this->messages->user_texts($conversation_id);
        $first      = $user_texts[0] ?? '';
        $joined     = implode(' ', $user_texts);

        $score = $this->scorer->score([
            'text'          => $joined,
            'cta'           => $cta,
            'has_phone'     => $phone !== '',
            'has_name'      => $name !== '',
            'message_count' => count($user_texts),
        ]);

        $this->conversations->set_score($conversation_id, $score['level']);
        $this->conversations->set_summary(
            $conversation_id,
            $this->summary->build(
                ['name' => $name, 'phone' => $phone, 'user_text' => $joined, 'first_message' => $first],
                $score
            )
        );

        return $score;
    }

    private function graceful_fallback(int $conversation_id, string $reason): array
    {
        $phone = (string) $this->settings->get('phone', '');
        $tail  = $phone !== '' ? " یا با شماره {$phone} تماس بگیرید" : '';
        $reply = "پوزش می‌خواهم، در حال حاضر امکان پاسخ‌گویی هوشمند نیست. لطفاً چند لحظه دیگر دوباره تلاش کنید{$tail}.";

        $this->messages->add($conversation_id, 'assistant', $reply, false);

        return [
            'ok'         => true,
            'reply'      => $reply,
            'cta'        => 'contact',
            'cta_card'   => $this->cta_card('contact'),
            'soft_error' => $reason,
        ];
    }

    /**
     * @return array{type:string,booking_url:string,whatsapp:string,phone:string}
     */
    private function cta_card(string $type): array
    {
        return [
            'type'        => $type,
            'booking_url' => (string) $this->settings->get('booking_url', ''),
            'whatsapp'    => (string) $this->settings->get('whatsapp', ''),
            'phone'       => (string) $this->settings->get('phone', ''),
        ];
    }
}

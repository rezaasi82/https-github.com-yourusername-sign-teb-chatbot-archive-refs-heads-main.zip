<?php
/**
 * SWC_AI_Manager — the conversation engine.
 *
 * The single entry point used by both the REST controller and the admin-ajax
 * handler. Coordinates the license/trial gate, rate limiting, the safety
 * layer, the dynamic system prompt, the swappable AI provider, persistence,
 * and CTA/lead detection. Never crashes: every failure path returns a polite,
 * structured fallback.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_AI_Manager
{
    private SWC_Settings $settings;
    private SWC_Medical_Safety_Filter $safety;
    private SWC_System_Prompt_Builder $prompt;
    private SWC_Language_Detector $language;
    private SWC_Cta_Detector $cta;
    private SWC_Conversation_Repository $conversations;
    private SWC_Message_Repository $messages;
    private SWC_License_Manager $license;

    public function __construct()
    {
        $this->settings      = new SWC_Settings();
        $this->safety        = new SWC_Medical_Safety_Filter($this->settings);
        $this->prompt        = new SWC_System_Prompt_Builder($this->settings);
        $this->language      = new SWC_Language_Detector();
        $this->cta           = new SWC_Cta_Detector();
        $this->conversations = new SWC_Conversation_Repository();
        $this->messages      = new SWC_Message_Repository();
        $this->license       = new SWC_License_Manager();
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

        if (! $this->license->can_send()) {
            return [
                'ok'    => false,
                'code'  => 'trial_expired',
                'error' => __('نسخه آزمایشی به پایان رسیده است. برای ادامه، لطفاً لایسنس را فعال کنید.', 'signteb-web-chat'),
            ];
        }

        $message = trim((string) $req['message']);
        if ($message === '') {
            return ['ok' => false, 'code' => 'empty', 'error' => __('پیام خالی است.', 'signteb-web-chat')];
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        // --- Rate limit (per IP + session) ---
        $limiter = new SWC_Rate_Limiter((int) $this->settings->get('rate_limit_per_min', 8));
        if (! $limiter->allow($req['ip'] . '|' . $req['session_id'])) {
            return ['ok' => false, 'code' => 'rate_limited', 'error' => __('لطفاً کمی صبر کنید و دوباره تلاش کنید.', 'signteb-web-chat')];
        }

        $lang = $this->language->resolve((string) $this->settings->get('language', 'auto'), $message);

        $conversation_id = $this->conversations->find_or_create($req['session_id'], [
            'ip'       => $req['ip'],
            'user_id'  => $req['user_id'],
            'language' => $lang,
            'page_url' => $req['page_url'],
        ]);

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
        $provider = $this->make_provider($this->settings->active_provider());
        if ($provider === null) {
            return $this->graceful_fallback($conversation_id, 'no_provider');
        }

        $context = [
            'system'     => $this->prompt->build($lang),
            'history'    => $this->messages->history($conversation_id, 12),
            'model'      => $this->settings->active_model(),
            'max_tokens' => 1024,
        ];

        $result = $provider->generate_reply($message, $context);

        if (empty($result['ok'])) {
            // Try the other provider as a fallback if its key is configured.
            $fallback = $this->make_fallback_provider($provider->id());
            if ($fallback !== null) {
                $context['model'] = $this->model_for($fallback->id());
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
        $this->license->record_usage();
        do_action('swc_message_handled', $conversation_id, $cta);

        return [
            'ok'       => true,
            'reply'    => $reply,
            'cta'      => $cta,
            'cta_card' => $cta !== '' ? $this->cta_card($cta) : null,
        ];
    }

    private function make_provider(string $id): ?SWC_AI_Provider_Interface
    {
        $key = $this->settings->get_api_key($id);
        if ($key === '') {
            return null;
        }
        return $id === 'openai' ? new SWC_Provider_OpenAI($key) : new SWC_Provider_Anthropic($key);
    }

    private function make_fallback_provider(string $primary_id): ?SWC_AI_Provider_Interface
    {
        $other = $primary_id === 'anthropic' ? 'openai' : 'anthropic';
        return $this->make_provider($other);
    }

    private function model_for(string $provider_id): string
    {
        $model = trim((string) $this->settings->get('model_' . $provider_id, ''));
        if ($model !== '') {
            return $model;
        }
        return $provider_id === 'openai' ? 'gpt-4o-mini' : 'claude-haiku-4-5-20251001';
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

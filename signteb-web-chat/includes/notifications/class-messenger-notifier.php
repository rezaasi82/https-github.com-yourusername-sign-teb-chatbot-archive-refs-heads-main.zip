<?php
/**
 * \Medora\Notifications\MessengerNotifier — server-side lead alerts to the clinic's own
 * Bale / Telegram group or channel via their Bot APIs.
 *
 * Unlike the SMS gateway (which texts a customer's phone), this pushes an
 * internal notification to a fixed chat_id the moment a lead is detected — so
 * the team sees new leads instantly in the messenger they already use.
 *
 * Bale mirrors the Telegram Bot API shape, so one implementation covers both:
 *   POST {base}/bot{token}/sendMessage  { chat_id, text }
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

class MessengerNotifier
{
    private const NONCE = 'swc_export';

    /** channel => [api base, encrypted token option, human label]. */
    private const CHANNELS = [
        'bale'     => ['https://tapi.bale.ai', 'swc_msgr_bale_token_enc', 'بله'],
        'telegram' => ['https://api.telegram.org', 'swc_msgr_tg_token_enc', 'Telegram'],
    ];

    private \Medora\Core\Settings $settings;

    public function __construct(?\Medora\Core\Settings $settings = null)
    {
        $this->settings = $settings ?? new \Medora\Core\Settings();
    }

    public function register(): void
    {
        add_action('swc_lead_detected', [$this, 'notify_new_lead'], 10, 2);
        add_action('wp_ajax_swc_test_messenger', [$this, 'ajax_test']);
    }

    /* -------------------- config -------------------- */

    /** @return array<string,array{label:string}> enabled channels only. */
    public function channels(): array
    {
        $out = [];
        foreach (self::CHANNELS as $id => $def) {
            $out[$id] = ['label' => $def[2]];
        }
        return $out;
    }

    public function is_enabled(string $channel): bool
    {
        return (int) $this->settings->get('msgr_' . $channel . '_enabled', 0) === 1
            && $this->chat_id($channel) !== ''
            && self::token($channel) !== '';
    }

    private function chat_id(string $channel): string
    {
        return trim((string) $this->settings->get('msgr_' . $channel . '_chat', ''));
    }

    public static function token(string $channel): string
    {
        $option = self::CHANNELS[$channel][1] ?? '';
        if ($option === '') {
            return '';
        }
        $enc = get_option($option, '');
        return is_string($enc) && $enc !== '' ? \Medora\Core\Encryption::decrypt($enc) : '';
    }

    public static function save_token(string $channel, string $plain): void
    {
        $option = self::CHANNELS[$channel][1] ?? '';
        if ($option === '') {
            return;
        }
        $plain = trim($plain);
        if ($plain === '') {
            delete_option($option);
            return;
        }
        update_option($option, \Medora\Core\Encryption::encrypt($plain), false);
    }

    public static function token_options(): array
    {
        return array_map(static fn($d) => $d[1], self::CHANNELS);
    }

    /* -------------------- sending -------------------- */

    /**
     * Send raw text to one channel.
     *
     * @return array{ok:bool,error?:string,code?:int}
     */
    public function send(string $channel, string $text): array
    {
        if (! isset(self::CHANNELS[$channel])) {
            return ['ok' => false, 'error' => 'bad_channel'];
        }
        $token = self::token($channel);
        $chat  = $this->chat_id($channel);
        if ($token === '' || $chat === '') {
            return ['ok' => false, 'error' => __('توکن ربات یا شناسه چت تنظیم نشده است.', 'signteb-web-chat')];
        }

        $url = self::CHANNELS[$channel][0] . '/bot' . $token . '/sendMessage';
        $res = wp_remote_post($url, [
            'timeout' => 22,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['chat_id' => $chat, 'text' => $text]),
        ]);
        if (is_wp_error($res)) {
            return ['ok' => false, 'error' => $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $data = json_decode((string) wp_remote_retrieve_body($res), true);
        if ($code >= 200 && $code < 300 && is_array($data) && ! empty($data['ok'])) {
            return ['ok' => true, 'code' => $code];
        }
        $desc = is_array($data) ? (string) ($data['description'] ?? '') : '';
        return ['ok' => false, 'code' => $code, 'error' => $desc !== '' ? $desc : sprintf(__('پاسخ سرویس: HTTP %d', 'signteb-web-chat'), $code)];
    }

    /**
     * Fired on lead detection: alert every enabled channel.
     */
    public function notify_new_lead(int $conversation_id, string $cta = ''): void
    {
        $enabled = array_filter(array_keys(self::CHANNELS), [$this, 'is_enabled']);
        if ($enabled === []) {
            return;
        }
        $c = (new \Medora\Database\ConversationRepository())->get($conversation_id);
        if (! $c) {
            return;
        }
        $text = $this->lead_text($c, $cta);
        foreach ($enabled as $channel) {
            $r = $this->send($channel, $text);
            \Medora\Security\AuditLog::record('messenger_notify', ['object' => $channel, 'severity' => ! empty($r['ok']) ? 'info' : 'warning']);
        }
    }

    private function lead_text(object $c, string $cta): string
    {
        $sms    = new \Medora\Notifications\SmsManager();
        $vars   = $sms->vars_for_lead($c);
        $clinic = $vars['clinic'];
        $lines  = [
            '🔔 ' . sprintf(__('لید جدید در %s', 'signteb-web-chat'), $clinic),
            sprintf(__('نام: %s', 'signteb-web-chat'), $vars['name']),
            sprintf(__('موبایل: %s', 'signteb-web-chat'), $vars['phone'] !== '' ? $vars['phone'] : '—'),
            sprintf(__('امتیاز: %s', 'signteb-web-chat'), $vars['score']),
        ];
        if ($cta !== '') {
            $lines[] = sprintf(__('اقدام: %s', 'signteb-web-chat'), $cta);
        }
        if ($vars['summary'] !== '') {
            $lines[] = "\n" . $vars['summary'];
        }
        return implode("\n", $lines);
    }

    /* -------------------- test -------------------- */

    public function ajax_test(): void
    {
        \Medora\Core\JsonGuard::arm();
        if (\Medora\Security\Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            \Medora\Security\Security::note_failure('messenger_test');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }
        $channel = sanitize_key(wp_unslash($_POST['channel'] ?? ''));
        if (! isset(self::CHANNELS[$channel])) {
            wp_send_json(['ok' => false, 'error' => 'bad_channel'], 400);
        }
        $clinic = (string) $this->settings->get('clinic_name', get_bloginfo('name'));
        wp_send_json($this->send($channel, sprintf(__('پیام آزمایشی %s از Medora AI ✅', 'signteb-web-chat'), $clinic)));
    }
}

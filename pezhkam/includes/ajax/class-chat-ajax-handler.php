<?php
/**
 * Admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * ChatController.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Ajax;

if (! defined('ABSPATH')) {
    exit;
}

class ChatAjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_pzk_chat_message', [$this, 'handle']);
        add_action('wp_ajax_nopriv_pzk_chat_message', [$this, 'handle']);
        add_action('wp_ajax_pzk_track_event', [$this, 'handle_event']);
        add_action('wp_ajax_nopriv_pzk_track_event', [$this, 'handle_event']);
    }

    public function handle(): void
    {
        \Pezhkam\Core\JsonGuard::arm();

        if (! check_ajax_referer('pzk_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'pezhkam')], 403);
        }

        $result = (new \Pezhkam\Ai\AiManager())->handle([
            'session_id' => \Pezhkam\Rest\Sanitizer::session_id(\Pezhkam\Core\Input::post_text('session_id')),
            'message'    => \Pezhkam\Core\Input::post_textarea('message'),
            'name'       => \Pezhkam\Rest\Sanitizer::name(\Pezhkam\Core\Input::post_text('name')),
            'phone'      => \Pezhkam\Rest\Sanitizer::phone(\Pezhkam\Core\Input::post_text('phone')),
            'ip'         => \Pezhkam\Rest\Sanitizer::client_ip(),
            'page_url'   => \Pezhkam\Core\Input::post_url('page_url'),
            'branch'     => \Pezhkam\Core\Input::post_int('branch'),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        \Pezhkam\Core\JsonGuard::arm();

        if (! check_ajax_referer('pzk_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        if (! \Pezhkam\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $type = \Pezhkam\Core\Input::post_key('type');
        $cid  = \Pezhkam\Core\Input::post_int('conversation_id');
        $ok   = (new \Pezhkam\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \Pezhkam\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

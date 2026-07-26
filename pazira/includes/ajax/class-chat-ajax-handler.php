<?php
/**
 * Admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * ChatController.
 *
 * @package Pazira
 */

namespace Pazira\Ajax;

if (! defined('ABSPATH')) {
    exit;
}

class ChatAjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_pzr_chat_message', [$this, 'handle']);
        add_action('wp_ajax_nopriv_pzr_chat_message', [$this, 'handle']);
        add_action('wp_ajax_pzr_track_event', [$this, 'handle_event']);
        add_action('wp_ajax_nopriv_pzr_track_event', [$this, 'handle_event']);
    }

    public function handle(): void
    {
        \Pazira\Core\JsonGuard::arm();

        if (! check_ajax_referer('pzr_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'pazira')], 403);
        }

        $result = (new \Pazira\Ai\AiManager())->handle([
            'session_id' => \Pazira\Rest\Sanitizer::session_id(\Pazira\Core\Input::post_text('session_id')),
            'message'    => \Pazira\Core\Input::post_textarea('message'),
            'name'       => \Pazira\Rest\Sanitizer::name(\Pazira\Core\Input::post_text('name')),
            'phone'      => \Pazira\Rest\Sanitizer::phone(\Pazira\Core\Input::post_text('phone')),
            'ip'         => \Pazira\Rest\Sanitizer::client_ip(),
            'page_url'   => \Pazira\Core\Input::post_url('page_url'),
            'branch'     => \Pazira\Core\Input::post_int('branch'),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        \Pazira\Core\JsonGuard::arm();

        if (! check_ajax_referer('pzr_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        if (! \Pazira\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $type = \Pazira\Core\Input::post_key('type');
        $cid  = \Pazira\Core\Input::post_int('conversation_id');
        $ok   = (new \Pazira\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \Pazira\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

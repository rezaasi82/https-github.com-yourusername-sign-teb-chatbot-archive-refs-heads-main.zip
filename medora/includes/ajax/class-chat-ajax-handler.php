<?php
/**
 * Admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * ChatController.
 *
 * @package Medora
 */

namespace Medora\Ajax;

if (! defined('ABSPATH')) {
    exit;
}

class ChatAjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_mdr_chat_message', [$this, 'handle']);
        add_action('wp_ajax_nopriv_mdr_chat_message', [$this, 'handle']);
        add_action('wp_ajax_mdr_track_event', [$this, 'handle_event']);
        add_action('wp_ajax_nopriv_mdr_track_event', [$this, 'handle_event']);
    }

    public function handle(): void
    {
        \Medora\Core\JsonGuard::arm();

        if (! check_ajax_referer('mdr_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'medora')], 403);
        }

        $result = (new \Medora\Ai\AiManager())->handle([
            'session_id' => \Medora\Rest\Sanitizer::session_id(\Medora\Core\Input::post_text('session_id')),
            'message'    => \Medora\Core\Input::post_textarea('message'),
            'name'       => \Medora\Rest\Sanitizer::name(\Medora\Core\Input::post_text('name')),
            'phone'      => \Medora\Rest\Sanitizer::phone(\Medora\Core\Input::post_text('phone')),
            'ip'         => \Medora\Rest\Sanitizer::client_ip(),
            'page_url'   => \Medora\Core\Input::post_url('page_url'),
            'branch'     => \Medora\Core\Input::post_int('branch'),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        \Medora\Core\JsonGuard::arm();

        if (! check_ajax_referer('mdr_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        if (! \Medora\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $type = \Medora\Core\Input::post_key('type');
        $cid  = \Medora\Core\Input::post_int('conversation_id');
        $ok   = (new \Medora\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \Medora\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

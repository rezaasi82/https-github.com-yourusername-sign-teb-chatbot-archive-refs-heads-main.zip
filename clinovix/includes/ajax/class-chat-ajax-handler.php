<?php
/**
 * Admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * ChatController.
 *
 * @package Clinovix
 */

namespace Clinovix\Ajax;

if (! defined('ABSPATH')) {
    exit;
}

class ChatAjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_clx_chat_message', [$this, 'handle']);
        add_action('wp_ajax_nopriv_clx_chat_message', [$this, 'handle']);
        add_action('wp_ajax_clx_track_event', [$this, 'handle_event']);
        add_action('wp_ajax_nopriv_clx_track_event', [$this, 'handle_event']);
    }

    public function handle(): void
    {
        \Clinovix\Core\JsonGuard::arm();

        if (! check_ajax_referer('clx_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'clinovix')], 403);
        }

        $result = (new \Clinovix\Ai\AiManager())->handle([
            'session_id' => \Clinovix\Rest\Sanitizer::session_id(\Clinovix\Core\Input::post_text('session_id')),
            'message'    => \Clinovix\Core\Input::post_textarea('message'),
            'name'       => \Clinovix\Rest\Sanitizer::name(\Clinovix\Core\Input::post_text('name')),
            'phone'      => \Clinovix\Rest\Sanitizer::phone(\Clinovix\Core\Input::post_text('phone')),
            'ip'         => \Clinovix\Rest\Sanitizer::client_ip(),
            'page_url'   => \Clinovix\Core\Input::post_url('page_url'),
            'branch'     => \Clinovix\Core\Input::post_int('branch'),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        \Clinovix\Core\JsonGuard::arm();

        if (! check_ajax_referer('clx_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        if (! \Clinovix\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $type = \Clinovix\Core\Input::post_key('type');
        $cid  = \Clinovix\Core\Input::post_int('conversation_id');
        $ok   = (new \Clinovix\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \Clinovix\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

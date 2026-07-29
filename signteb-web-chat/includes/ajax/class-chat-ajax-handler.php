<?php
/**
 * Admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * ChatController.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Ajax;

if (! defined('ABSPATH')) {
    exit;
}

class ChatAjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_swc_chat_message', [$this, 'handle']);
        add_action('wp_ajax_nopriv_swc_chat_message', [$this, 'handle']);
        add_action('wp_ajax_swc_track_event', [$this, 'handle_event']);
        add_action('wp_ajax_nopriv_swc_track_event', [$this, 'handle_event']);
    }

    public function handle(): void
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();

        if (! check_ajax_referer('swc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'signteb-web-chat')], 403);
        }

        $result = (new \SignTeb\WebChat\Ai\AiManager())->handle([
            'session_id' => \SignTeb\WebChat\Rest\Sanitizer::session_id(\SignTeb\WebChat\Core\Input::post_text('session_id')),
            'message'    => \SignTeb\WebChat\Core\Input::post_textarea('message'),
            'name'       => \SignTeb\WebChat\Rest\Sanitizer::name(\SignTeb\WebChat\Core\Input::post_text('name')),
            'phone'      => \SignTeb\WebChat\Rest\Sanitizer::phone(\SignTeb\WebChat\Core\Input::post_text('phone')),
            'ip'         => \SignTeb\WebChat\Rest\Sanitizer::client_ip(),
            'page_url'   => \SignTeb\WebChat\Core\Input::post_url('page_url'),
            'branch'     => \SignTeb\WebChat\Core\Input::post_int('branch'),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();

        if (! check_ajax_referer('swc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        if (! \SignTeb\WebChat\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $type = \SignTeb\WebChat\Core\Input::post_key('type');
        $cid  = \SignTeb\WebChat\Core\Input::post_int('conversation_id');
        $ok   = (new \SignTeb\WebChat\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \SignTeb\WebChat\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

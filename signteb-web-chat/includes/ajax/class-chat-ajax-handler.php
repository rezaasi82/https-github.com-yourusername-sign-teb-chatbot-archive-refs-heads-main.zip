<?php
/**
 * \Medora\Ajax\ChatAjaxHandler — admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * \Medora\Rest\ChatController.
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Ajax;

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
        \Medora\Core\JsonGuard::arm();

        if (! check_ajax_referer('swc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'signteb-web-chat')], 403);
        }

        $result = (new \Medora\Ai\AiManager())->handle([
            'session_id' => \Medora\Rest\Sanitizer::session_id((string) ($_POST['session_id'] ?? '')),
            'message'    => sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? ''))),
            'name'       => \Medora\Rest\Sanitizer::name(wp_unslash((string) ($_POST['name'] ?? ''))),
            'phone'      => \Medora\Rest\Sanitizer::phone(wp_unslash((string) ($_POST['phone'] ?? ''))),
            'ip'         => \Medora\Rest\Sanitizer::client_ip(),
            'page_url'   => esc_url_raw(wp_unslash((string) ($_POST['page_url'] ?? ''))),
            'branch'     => absint($_POST['branch'] ?? 0),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        \Medora\Core\JsonGuard::arm();

        if (! check_ajax_referer('swc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        if (! \Medora\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $type = sanitize_key((string) ($_POST['type'] ?? ''));
        $cid  = absint($_POST['conversation_id'] ?? 0);
        $ok   = (new \Medora\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \Medora\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

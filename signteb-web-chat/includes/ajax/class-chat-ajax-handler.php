<?php
/**
 * SWC_Chat_Ajax_Handler — admin-ajax.php fallback transport.
 *
 * For hosts that restrict the REST API. Same contract and security posture as
 * SWC_Chat_Controller.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Chat_Ajax_Handler
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
        SWC_Json_Guard::arm();

        if (! check_ajax_referer('swc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => __('درخواست نامعتبر است.', 'signteb-web-chat')], 403);
        }

        $result = (new SWC_AI_Manager())->handle([
            'session_id' => SWC_Sanitizer::session_id((string) ($_POST['session_id'] ?? '')),
            'message'    => sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? ''))),
            'name'       => SWC_Sanitizer::name(wp_unslash((string) ($_POST['name'] ?? ''))),
            'phone'      => SWC_Sanitizer::phone(wp_unslash((string) ($_POST['phone'] ?? ''))),
            'ip'         => SWC_Sanitizer::client_ip(),
            'page_url'   => esc_url_raw(wp_unslash((string) ($_POST['page_url'] ?? ''))),
            'branch'     => absint($_POST['branch'] ?? 0),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }

    public function handle_event(): void
    {
        SWC_Json_Guard::arm();

        if (! check_ajax_referer('swc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }

        $type = sanitize_key((string) ($_POST['type'] ?? ''));
        $cid  = absint($_POST['conversation_id'] ?? 0);
        $ok   = (new SWC_Event_Repository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new SWC_Conversation_Repository())->set_booking_status($cid, 'clicked');
        }
        wp_send_json(['ok' => $ok], $ok ? 200 : 400);
    }
}

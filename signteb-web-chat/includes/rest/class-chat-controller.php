<?php
/**
 * \Medora\Rest\ChatController — REST transport for the chat.
 *
 * Mirrors the admin-ajax handler so hosts that block the REST API still work.
 * Nonce-protected; rate-limited and license-gated downstream in \Medora\Ai\AiManager.
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Rest;

if (! defined('ABSPATH')) {
    exit;
}

class ChatController
{
    private const REST_NAMESPACE = 'signteb-web-chat/v1';

    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(self::REST_NAMESPACE, '/message', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_message'],
                'permission_callback' => [$this, 'verify_nonce'],
                'args'                => [
                    'message'    => ['required' => true, 'type' => 'string'],
                    'session_id' => ['required' => true, 'type' => 'string'],
                ],
            ]);

            register_rest_route(self::REST_NAMESPACE, '/event', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_event'],
                'permission_callback' => [$this, 'verify_nonce'],
                'args'                => [
                    'type' => ['required' => true, 'type' => 'string'],
                ],
            ]);
        });
    }

    public function verify_nonce(\WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        return is_string($nonce) && (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    public function handle_message(\WP_REST_Request $request): \WP_REST_Response
    {
        \Medora\Core\JsonGuard::arm();

        $result = (new \Medora\Ai\AiManager())->handle([
            'session_id' => \Medora\Rest\Sanitizer::session_id((string) $request->get_param('session_id')),
            'message'    => sanitize_textarea_field((string) $request->get_param('message')),
            'name'       => \Medora\Rest\Sanitizer::name((string) $request->get_param('name')),
            'phone'      => \Medora\Rest\Sanitizer::phone((string) $request->get_param('phone')),
            'ip'         => \Medora\Rest\Sanitizer::client_ip(),
            'page_url'   => esc_url_raw((string) $request->get_param('page_url')),
            'branch'     => absint($request->get_param('branch')),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        return new \WP_REST_Response($result, $status);
    }

    public function handle_event(\WP_REST_Request $request): \WP_REST_Response
    {
        \Medora\Core\JsonGuard::arm();
        if (! \Medora\Security\Security::rate_limit('event', 60, MINUTE_IN_SECONDS)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        $type = sanitize_key((string) $request->get_param('type'));
        $cid  = absint($request->get_param('conversation_id'));
        $ok   = (new \Medora\Database\EventRepository())->record($type, $cid);
        if ($ok && $type === 'booking' && $cid > 0) {
            (new \Medora\Database\ConversationRepository())->set_booking_status($cid, 'clicked');
        }
        return new \WP_REST_Response(['ok' => $ok], $ok ? 200 : 400);
    }
}

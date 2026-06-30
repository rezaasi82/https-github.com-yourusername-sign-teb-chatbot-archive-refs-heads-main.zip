<?php

namespace STMC_Chat\Rest;

use STMC_Chat\AI\AIManager;
use STMC_Chat\Core\JsonGuard;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * REST transport for the chat. Mirrors the admin-ajax handler so hosts that
 * block the REST API still work. Nonce-protected, rate-limited downstream.
 */
class ChatController
{
    private const NAMESPACE = 'signteb-ai-chat/v1';

    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(self::NAMESPACE, '/message', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_message'],
                'permission_callback' => [$this, 'verify_nonce'],
                'args'                => [
                    'message'    => ['required' => true, 'type' => 'string'],
                    'session_id' => ['required' => true, 'type' => 'string'],
                ],
            ]);
        });
    }

    public function verify_nonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function handle_message(WP_REST_Request $request): WP_REST_Response
    {
        JsonGuard::arm();

        $result = (new AIManager())->handle([
            'session_id' => Sanitizer::session_id((string) $request->get_param('session_id')),
            'message'    => sanitize_textarea_field((string) $request->get_param('message')),
            'ip'         => Sanitizer::client_ip(),
            'page_url'   => esc_url_raw((string) $request->get_param('page_url')),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        return new WP_REST_Response($result, $status);
    }
}

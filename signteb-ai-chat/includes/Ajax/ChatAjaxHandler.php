<?php

namespace STMC_Chat\Ajax;

use STMC_Chat\AI\AIManager;
use STMC_Chat\Core\JsonGuard;
use STMC_Chat\Rest\Sanitizer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * admin-ajax.php fallback transport for hosts that restrict the REST API.
 * Same contract and security posture as ChatController.
 */
class ChatAjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_stmc_chat_message', [$this, 'handle']);
        add_action('wp_ajax_nopriv_stmc_chat_message', [$this, 'handle']);
    }

    public function handle(): void
    {
        JsonGuard::arm();

        if (! check_ajax_referer('stmc_chat_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'code' => 'bad_nonce', 'error' => 'درخواست نامعتبر است.'], 403);
        }

        $result = (new AIManager())->handle([
            'session_id' => Sanitizer::session_id((string) ($_POST['session_id'] ?? '')),
            'message'    => sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? ''))),
            'ip'         => Sanitizer::client_ip(),
            'page_url'   => esc_url_raw(wp_unslash((string) ($_POST['page_url'] ?? ''))),
            'user_id'    => get_current_user_id() ?: null,
        ]);

        $status = ! empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'rate_limited' ? 429 : 400);
        wp_send_json($result, $status);
    }
}

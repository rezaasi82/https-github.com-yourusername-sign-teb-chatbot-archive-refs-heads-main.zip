<?php

namespace Nobatyar\Rest\Controllers;

use Nobatyar\GiftCards\GiftCardEngine;

if (! defined('ABSPATH')) {
    exit;
}

class GiftCardController
{
    private const RATE_LIMIT_MAX_REQUESTS   = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    private GiftCardEngine $gift_card_engine;

    public function __construct(GiftCardEngine $gift_card_engine)
    {
        $this->gift_card_engine = $gift_card_engine;
    }

    public function register_routes(): void
    {
        register_rest_route('nobatyar/v1', '/gift-cards/validate', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'validate_gift_card'],
            'permission_callback' => [$this, 'check_public_permission'],
            'args'                => [
                'code' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    /**
     * @return true|\WP_Error
     */
    public function check_public_permission(\WP_REST_Request $request)
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (! $nonce || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('nobatyar_invalid_nonce', __('درخواست نامعتبر است.', 'nobatyar-booking'), ['status' => 403]);
        }

        return true;
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function validate_gift_card(\WP_REST_Request $request)
    {
        $rate_limit_error = $this->check_rate_limit();

        if (is_wp_error($rate_limit_error)) {
            return $rate_limit_error;
        }

        $gift_card = $this->gift_card_engine->validate((string) $request->get_param('code'));

        if (is_wp_error($gift_card)) {
            return $gift_card;
        }

        return new \WP_REST_Response([
            'valid'             => true,
            'remaining_balance' => (float) $gift_card['remaining_balance'],
        ], 200);
    }

    /**
     * Shares the exact same per-IP transient bucket as BookingController/
     * PackageController/CouponController so gift card validation requests
     * count against the same overall request budget.
     *
     * @return null|\WP_Error
     */
    private function check_rate_limit()
    {
        $key   = 'nobatyar_throttle_' . md5($this->get_client_ip());
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
            return new \WP_Error(
                'nobatyar_rate_limited',
                __('تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.', 'nobatyar-booking'),
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW_SECONDS);

        return null;
    }

    private function get_client_ip(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    }
}

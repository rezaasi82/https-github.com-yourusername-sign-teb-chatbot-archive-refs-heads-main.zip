<?php

namespace Nobatyar\Rest\Controllers;

use Nobatyar\Payment\PaymentEngine;

if (! defined('ABSPATH')) {
    exit;
}

class PaymentController
{
    private PaymentEngine $payment_engine;

    public function __construct(PaymentEngine $payment_engine)
    {
        $this->payment_engine = $payment_engine;
    }

    public function register_routes(): void
    {
        register_rest_route('nobatyar/v1', '/payments/init', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'init_payment'],
            'permission_callback' => [$this, 'check_public_permission'],
            'args'                => [
                'booking_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route('nobatyar/v1', '/payments/callback', [
            // Zarinpal/NextPay redirect the browser via GET; IdPay POSTs server-to-server.
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_callback'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_callback'],
                'permission_callback' => '__return_true',
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
    public function init_payment(\WP_REST_Request $request)
    {
        $result = $this->payment_engine->init_payment(
            (int) $request->get_param('booking_id'),
            rest_url('nobatyar/v1/payments/callback')
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response($result, 200);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_callback(\WP_REST_Request $request)
    {
        $callback_params = array_filter(
            [
                'Authority' => $request->get_param('Authority'),
                'Status'    => $request->get_param('Status'),
                'id'        => $request->get_param('id'),
                'order_id'  => $request->get_param('order_id'),
                'trans_id'  => $request->get_param('trans_id'),
            ],
            static fn ($value) => null !== $value
        );

        $result = $this->payment_engine->verify_from_callback($callback_params);

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response($result, 200);
    }
}

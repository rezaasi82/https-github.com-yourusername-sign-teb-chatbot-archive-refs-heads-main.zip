<?php

namespace Nobatyar\Payment\Gateways;

use Nobatyar\Payment\PaymentGatewayInterface;
use Nobatyar\Payment\PaymentInitResult;
use Nobatyar\Payment\PaymentVerifyResult;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * IdPay's v1.1 REST API (https://idpay.ir/dashboard/web-service/api) -
 * implemented best-effort from published docs, not verified against a live
 * sandbox account (lower confidence than ZarinpalGateway).
 */
class IdPayGateway implements PaymentGatewayInterface
{
    private string $api_key;
    private bool $sandbox;

    public function __construct(string $api_key, bool $sandbox = false)
    {
        $this->api_key = $api_key;
        $this->sandbox = $sandbox;
    }

    public function get_name(): string
    {
        return 'idpay';
    }

    public function init(float $amount, string $callback_url, array $transaction): PaymentInitResult
    {
        $response = wp_remote_post('https://api.idpay.ir/v1.1/payment', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->api_key,
                'X-SANDBOX'    => $this->sandbox ? '1' : '0',
            ],
            'body' => wp_json_encode([
                'order_id' => (string) $transaction['id'],
                'amount'   => (int) $amount,
                'callback' => $callback_url,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return PaymentInitResult::failure($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['link']) || empty($body['id'])) {
            return PaymentInitResult::failure($body['error_message'] ?? __('پاسخ نامعتبر از درگاه آی‌دی‌پی.', 'nobatyar-booking'));
        }

        return PaymentInitResult::success($body['link'], $body['id']);
    }

    public function verify(array $callback_params, array $transaction): PaymentVerifyResult
    {
        $response = wp_remote_post('https://api.idpay.ir/v1.1/payment/verify', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->api_key,
                'X-SANDBOX'    => $this->sandbox ? '1' : '0',
            ],
            'body' => wp_json_encode([
                'id'       => $transaction['authority'],
                'order_id' => (string) $transaction['id'],
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return PaymentVerifyResult::failure($response->get_error_message());
        }

        $raw    = wp_remote_retrieve_body($response);
        $body   = json_decode($raw, true);
        $status = (int) ($body['status'] ?? 0);

        // 100 = verified & settled per IdPay docs; other codes are pending/failed/error states.
        if (100 === $status) {
            return PaymentVerifyResult::success($body['track_id'] ?? null, $raw);
        }

        return PaymentVerifyResult::failure(sprintf('IdPay status %d', $status), $raw);
    }

    public function extract_authority(array $callback_params): ?string
    {
        return $callback_params['id'] ?? null;
    }
}

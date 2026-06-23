<?php

namespace Nobatyar\Payment\Gateways;

use Nobatyar\Payment\PaymentGatewayInterface;
use Nobatyar\Payment\PaymentInitResult;
use Nobatyar\Payment\PaymentVerifyResult;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Zarinpal's REST v4 API (https://www.zarinpal.com/docs/paymentGateway/) -
 * amount is in Rial, authority travels as a query param on the callback.
 */
class ZarinpalGateway implements PaymentGatewayInterface
{
    private string $merchant_id;
    private bool $sandbox;

    public function __construct(string $merchant_id, bool $sandbox = false)
    {
        $this->merchant_id = $merchant_id;
        $this->sandbox     = $sandbox;
    }

    public function get_name(): string
    {
        return 'zarinpal';
    }

    public function init(float $amount, string $callback_url, array $transaction): PaymentInitResult
    {
        $endpoint = $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            : 'https://api.zarinpal.com/pg/v4/payment/request.json';

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode([
                'merchant_id'  => $this->merchant_id,
                'amount'       => (int) $amount,
                'callback_url' => $callback_url,
                'description'  => sprintf(__('پرداخت نوبت #%d', 'nobatyar-booking'), (int) $transaction['booking_id']),
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return PaymentInitResult::failure($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = $body['data']['code'] ?? null;

        if (100 !== $code) {
            return PaymentInitResult::failure($body['errors']['message'] ?? sprintf('Zarinpal error code %s', $code ?? 'unknown'));
        }

        $authority = $body['data']['authority'];
        $base      = $this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com';

        return PaymentInitResult::success("{$base}/pg/StartPay/{$authority}", $authority);
    }

    public function verify(array $callback_params, array $transaction): PaymentVerifyResult
    {
        if ('OK' !== ($callback_params['Status'] ?? '')) {
            return PaymentVerifyResult::failure(__('پرداخت توسط کاربر لغو شد.', 'nobatyar-booking'));
        }

        $endpoint = $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
            : 'https://api.zarinpal.com/pg/v4/payment/verify.json';

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode([
                'merchant_id' => $this->merchant_id,
                'amount'      => (int) $transaction['amount'],
                'authority'   => $transaction['authority'],
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return PaymentVerifyResult::failure($response->get_error_message());
        }

        $raw  = wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);
        $code = $body['data']['code'] ?? null;

        // 100 = first verification, 101 = already verified - both count as success.
        if (100 === $code || 101 === $code) {
            return PaymentVerifyResult::success($body['data']['ref_id'] ?? null, $raw);
        }

        return PaymentVerifyResult::failure($body['errors']['message'] ?? sprintf('Zarinpal verify error code %s', $code ?? 'unknown'), $raw);
    }

    public function extract_authority(array $callback_params): ?string
    {
        return $callback_params['Authority'] ?? null;
    }
}

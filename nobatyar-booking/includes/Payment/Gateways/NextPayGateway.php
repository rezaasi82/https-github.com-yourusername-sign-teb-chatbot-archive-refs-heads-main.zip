<?php

namespace Nobatyar\Payment\Gateways;

use Nobatyar\Payment\PaymentGatewayInterface;
use Nobatyar\Payment\PaymentInitResult;
use Nobatyar\Payment\PaymentVerifyResult;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * NextPay's classic gateway (https://nextpay.org/nx/docs) - implemented
 * best-effort from published docs, not verified against a live sandbox
 * account (lower confidence than ZarinpalGateway).
 */
class NextPayGateway implements PaymentGatewayInterface
{
    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function get_name(): string
    {
        return 'nextpay';
    }

    public function init(float $amount, string $callback_url, array $transaction): PaymentInitResult
    {
        $response = wp_remote_post('https://nextpay.org/nx/gateway/token.http', [
            'body' => [
                'api_key'      => $this->api_key,
                'amount'       => (int) $amount,
                'callback_uri' => $callback_url,
                'order_id'     => (string) $transaction['id'],
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return PaymentInitResult::failure($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = $body['code'] ?? null;

        // -1 = success per NextPay's token endpoint.
        if (-1 !== $code || empty($body['trans_id'])) {
            return PaymentInitResult::failure($body['message'] ?? sprintf('NextPay error code %s', $code ?? 'unknown'));
        }

        $trans_id = $body['trans_id'];

        return PaymentInitResult::success("https://nextpay.org/nx/gateway/payment/{$trans_id}", $trans_id);
    }

    public function verify(array $callback_params, array $transaction): PaymentVerifyResult
    {
        $response = wp_remote_post('https://nextpay.org/nx/gateway/verify.http', [
            'body' => [
                'api_key'  => $this->api_key,
                'amount'   => (int) $transaction['amount'],
                'trans_id' => $transaction['authority'],
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return PaymentVerifyResult::failure($response->get_error_message());
        }

        $raw  = wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);
        $code = $body['code'] ?? null;

        // 0 = success per NextPay's verify endpoint.
        if (0 !== $code) {
            return PaymentVerifyResult::failure($body['message'] ?? sprintf('NextPay verify error code %s', $code ?? 'unknown'), $raw);
        }

        return PaymentVerifyResult::success($body['Shaparak_Ref_Id'] ?? null, $raw);
    }

    public function extract_authority(array $callback_params): ?string
    {
        return $callback_params['trans_id'] ?? null;
    }
}

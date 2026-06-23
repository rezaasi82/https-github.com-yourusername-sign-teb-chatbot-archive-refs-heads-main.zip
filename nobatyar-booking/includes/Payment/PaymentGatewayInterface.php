<?php

namespace Nobatyar\Payment;

if (! defined('ABSPATH')) {
    exit;
}

interface PaymentGatewayInterface
{
    /**
     * Machine-readable identifier stored in nby_transactions.gateway.
     */
    public function get_name(): string;

    /**
     * @param array $transaction the freshly-created nby_transactions row (id, booking_id, amount, ...)
     */
    public function init(float $amount, string $callback_url, array $transaction): PaymentInitResult;

    /**
     * @param array $callback_params raw params from the gateway's callback request
     * @param array $transaction the matching nby_transactions row (authority, amount, booking_id, ...)
     */
    public function verify(array $callback_params, array $transaction): PaymentVerifyResult;

    /**
     * Pulls this gateway's own transaction identifier out of an inbound
     * callback request so PaymentEngine can work out which gateway sent it -
     * the callback payload itself carries no self-identifying field.
     */
    public function extract_authority(array $callback_params): ?string;
}

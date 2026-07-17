<?php

namespace App\Domain\Billing;

use App\Models\Invoice;
use App\Models\Payment;

/**
 * Adding a new gateway = one new driver class + a config entry. Nothing in
 * the billing core changes (same rule the Zarinpal/NextPay/Stripe drivers follow).
 */
interface PaymentGatewayInterface
{
    /** Gateway identifier, e.g. 'zarinpal'. */
    public function id(): string;

    /** ISO currency codes this gateway can settle. */
    public function supportedCurrencies(): array;

    /**
     * Start a payment for an invoice. Returns a redirect URL for hosted
     * checkout gateways, or null for offline flows (manual / bank transfer).
     */
    public function initiate(Invoice $invoice, string $callbackUrl): ?string;

    /**
     * Verify a gateway callback/webhook and settle the payment.
     * Implementations MUST verify authenticity (signature / server-to-server
     * verification call) before marking anything paid.
     *
     * @param  array<string, mixed>  $payload  raw callback parameters
     */
    public function verify(Invoice $invoice, array $payload): Payment;
}

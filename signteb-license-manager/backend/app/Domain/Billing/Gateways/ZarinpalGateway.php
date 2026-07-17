<?php

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ZarinpalGateway implements PaymentGatewayInterface
{
    private const REQUEST_URL = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
    private const VERIFY_URL = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
    private const REDIRECT_URL = 'https://payment.zarinpal.com/pg/StartPay/';

    public function id(): string
    {
        return 'zarinpal';
    }

    public function supportedCurrencies(): array
    {
        return config('slm.gateways.zarinpal.currencies', ['IRR']);
    }

    public function initiate(Invoice $invoice, string $callbackUrl): ?string
    {
        $response = Http::acceptJson()->post(self::REQUEST_URL, [
            'merchant_id' => config('slm.gateways.zarinpal.merchant_id'),
            'amount' => (int) $invoice->total, // IRR
            'callback_url' => $callbackUrl,
            'description' => "SignTeb invoice {$invoice->number}",
        ])->throw()->json('data');

        $invoice->payments()->create([
            'uuid' => (string) Str::uuid(),
            'gateway' => $this->id(),
            'gateway_ref' => $response['authority'],
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => 'pending',
        ]);

        return self::REDIRECT_URL.$response['authority'];
    }

    public function verify(Invoice $invoice, array $payload): Payment
    {
        $payment = $invoice->payments()
            ->where('gateway', $this->id())
            ->where('gateway_ref', $payload['Authority'] ?? '')
            ->where('status', 'pending')
            ->firstOrFail();

        // Server-to-server verification — the browser callback alone is never trusted.
        $result = Http::acceptJson()->post(self::VERIFY_URL, [
            'merchant_id' => config('slm.gateways.zarinpal.merchant_id'),
            'amount' => (int) $payment->amount,
            'authority' => $payment->gateway_ref,
        ])->json('data');

        // code 100 = verified, 101 = already verified (idempotent retry)
        if (in_array($result['code'] ?? null, [100, 101], true)) {
            $payment->update([
                'status' => 'succeeded',
                'paid_at' => now(),
                'raw_response' => $result,
            ]);
        } else {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => 'zarinpal_code_'.($result['code'] ?? 'unknown'),
                'raw_response' => $result,
            ]);
        }

        return $payment->refresh();
    }
}

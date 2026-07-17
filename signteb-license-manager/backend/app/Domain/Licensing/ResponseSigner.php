<?php

namespace App\Domain\Licensing;

use App\Models\Product;

/**
 * HMAC-SHA256 signs every licensing API response with the product's secret so the
 * SDK can verify authenticity. This defeats the classic nulled-plugin attack of
 * pointing the license hostname at a fake server that always answers "valid".
 */
class ResponseSigner
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> payload wrapped with timestamp + signature
     */
    public function sign(array $payload, Product $product): array
    {
        $envelope = [
            'data' => $payload,
            'timestamp' => time(),
        ];

        $envelope['signature'] = hash_hmac(
            'sha256',
            json_encode($envelope['data'], JSON_UNESCAPED_SLASHES).'|'.$envelope['timestamp'],
            $product->signing_secret,
        );

        return $envelope;
    }

    public function verifyRequestSignature(
        string $signature,
        int $timestamp,
        string $method,
        string $path,
        string $body,
        string $licenseKey,
    ): bool {
        if (abs(time() - $timestamp) > (int) config('slm.signature_ttl_seconds')) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$method}|{$path}|{$timestamp}|{$body}", $licenseKey);

        return hash_equals($expected, $signature);
    }
}

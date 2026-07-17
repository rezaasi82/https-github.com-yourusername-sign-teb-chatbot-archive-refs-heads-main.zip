<?php

return [

    /*
     * License key format: {PRODUCT_PREFIX}-XXXX-XXXX-XXXX
     * Alphabet excludes ambiguous characters (0/O, 1/I/L).
     */
    'license' => [
        'alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'segments' => 3,
        'segment_length' => 4,
        'default_activation_limit' => 1,
        'default_grace_days' => 14,
        'max_transfers_per_month' => 1,
    ],

    /*
     * Request-signature window for SDK calls (replay protection).
     */
    'signature_ttl_seconds' => 300,

    /*
     * Signed download URL lifetime for update packages.
     */
    'download_url_ttl_seconds' => 300,

    /*
     * Domains that never consume an activation slot.
     */
    'dev_domain_patterns' => [
        'localhost', '127.0.0.1', '*.test', '*.local', '*.localhost',
        'staging.*', 'dev.*', '*.dev.cc', '*.staging.*',
    ],

    'rate_limits' => [
        'public_per_ip_per_minute' => 30,
        'validate_per_license_per_minute' => 12,
        'ai_per_license_per_minute' => 60,
    ],

    'ai' => [
        // Redis key prefix for token quota buckets.
        'quota_prefix' => 'slm:quota',
    ],

    'gateways' => [
        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => env('ZARINPAL_SANDBOX', false),
            'currencies' => ['IRR'],
        ],
        'nextpay' => [
            'api_key' => env('NEXTPAY_API_KEY'),
            'currencies' => ['IRR'],
        ],
        'stripe' => [
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currencies' => ['USD', 'AED', 'EUR'],
        ],
    ],
];

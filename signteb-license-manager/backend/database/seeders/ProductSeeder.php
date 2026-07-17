<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'MEDORA AI', 'slug' => 'medora-ai', 'key_prefix' => 'MED', 'category' => 'ai-assistant'],
            ['name' => 'SignBot', 'slug' => 'signbot', 'key_prefix' => 'SBT', 'category' => 'chatbot'],
            ['name' => 'SignTeb SEO Dashboard', 'slug' => 'signteb-seo', 'key_prefix' => 'SEO', 'category' => 'seo'],
            ['name' => 'QRCODR', 'slug' => 'qrcodr', 'key_prefix' => 'QRC', 'category' => 'utility'],
        ];

        foreach ($products as $data) {
            $product = Product::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'uuid' => (string) Str::uuid(),
                    'status' => 'active',
                    'signing_secret' => bin2hex(random_bytes(32)),
                ],
            );

            foreach ([
                ['tier' => 'starter', 'price' => 0, 'activation_limit' => 1, 'monthly_token_limit' => 50_000],
                ['tier' => 'professional', 'price' => 4_900_000, 'activation_limit' => 1, 'monthly_token_limit' => 500_000],
                ['tier' => 'clinic', 'price' => 9_900_000, 'activation_limit' => 3, 'monthly_token_limit' => 2_000_000],
                ['tier' => 'agency', 'price' => 24_900_000, 'activation_limit' => 10, 'monthly_token_limit' => 10_000_000],
            ] as $tier) {
                Plan::firstOrCreate(
                    ['product_id' => $product->id, 'slug' => $tier['tier'], 'billing_cycle' => 'yearly'],
                    [
                        ...$tier,
                        'name' => ucfirst($tier['tier']),
                        'currency' => 'IRR',
                        'trial_days' => $tier['tier'] === 'starter' ? 0 : 14,
                        'grace_days' => 14,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}

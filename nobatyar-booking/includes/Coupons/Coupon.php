<?php

namespace Nobatyar\Coupons;

if (! defined('ABSPATH')) {
    exit;
}

class Coupon
{
    public int $id;
    public string $code;
    public string $discount_type;
    public float $discount_value;
    public ?int $service_id;
    public ?int $max_uses;
    public int $used_count;
    public ?string $valid_from;
    public ?string $valid_until;
    public ?float $min_amount;
    public bool $is_active;

    public static function from_row(array $row): self
    {
        $coupon = new self();
        $coupon->id              = (int) $row['id'];
        $coupon->code            = $row['code'];
        $coupon->discount_type   = $row['discount_type'];
        $coupon->discount_value  = (float) $row['discount_value'];
        $coupon->service_id      = isset($row['service_id']) ? (int) $row['service_id'] : null;
        $coupon->max_uses        = isset($row['max_uses']) ? (int) $row['max_uses'] : null;
        $coupon->used_count      = (int) ($row['used_count'] ?? 0);
        $coupon->valid_from      = $row['valid_from'] ?? null;
        $coupon->valid_until     = $row['valid_until'] ?? null;
        $coupon->min_amount      = isset($row['min_amount']) ? (float) $row['min_amount'] : null;
        $coupon->is_active       = (bool) $row['is_active'];
        return $coupon;
    }

    /**
     * Pure discount math shared by CouponEngine::validate() (for previews)
     * and PaymentEngine::resolve_amount() (for the actual charge) so the two
     * never compute a discount differently. Never discounts below zero.
     */
    public static function calculate_discount(array $coupon_row, float $amount): float
    {
        if (CouponDiscountType::PERCENT === $coupon_row['discount_type']) {
            $discount = $amount * ((float) $coupon_row['discount_value'] / 100);
        } else {
            $discount = (float) $coupon_row['discount_value'];
        }

        return min(max(0.0, $discount), $amount);
    }
}

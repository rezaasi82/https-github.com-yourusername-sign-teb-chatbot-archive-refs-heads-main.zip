<?php

namespace Nobatyar\GiftCards;

if (! defined('ABSPATH')) {
    exit;
}

class GiftCard
{
    public int $id;
    public string $code;
    public float $initial_balance;
    public float $remaining_balance;
    public ?string $expires_at;
    public bool $is_active;
    public ?string $recipient_name;
    public ?string $recipient_email;
    public ?string $note;

    public static function from_row(array $row): self
    {
        $gift_card                    = new self();
        $gift_card->id                = (int) $row['id'];
        $gift_card->code              = $row['code'];
        $gift_card->initial_balance   = (float) $row['initial_balance'];
        $gift_card->remaining_balance = (float) $row['remaining_balance'];
        $gift_card->expires_at        = $row['expires_at'] ?? null;
        $gift_card->is_active         = (bool) $row['is_active'];
        $gift_card->recipient_name    = $row['recipient_name'] ?? null;
        $gift_card->recipient_email   = $row['recipient_email'] ?? null;
        $gift_card->note              = $row['note'] ?? null;

        return $gift_card;
    }

    /**
     * Pure redemption math: never more than the card's remaining balance,
     * never more than the amount actually owed. Unlike
     * Coupon::calculate_discount() (re-derivable from static discount_type/
     * discount_value at any time), a gift card's balance mutates per
     * redemption, so BookingEngine::book() calls this once to lock in the
     * redeemed amount at booking-creation time rather than PaymentEngine
     * recomputing it later from a repository lookup.
     */
    public static function calculate_redemption(array $gift_card_row, float $amount): float
    {
        $remaining = max(0.0, (float) $gift_card_row['remaining_balance']);

        return min($remaining, max(0.0, $amount));
    }
}

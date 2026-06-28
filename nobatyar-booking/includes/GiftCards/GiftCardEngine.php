<?php

namespace Nobatyar\GiftCards;

use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;

if (! defined('ABSPATH')) {
    exit;
}

class GiftCardEngine
{
    private GiftCardRepository $gift_card_repository;
    private LicenseManager $license_manager;

    public function __construct(GiftCardRepository $gift_card_repository, LicenseManager $license_manager)
    {
        $this->gift_card_repository = $gift_card_repository;
        $this->license_manager      = $license_manager;
    }

    /**
     * Gift cards are gated at Business only — see LicenseTier's own
     * docblock: "Gift Cards: Business-only". Unlike Coupons there is no Pro
     * fallback. Mirrors CouponEngine::require_pro_tier(): validate() is
     * gated too, since checking a code IS the feature itself, not a check
     * against a pre-existing paid entitlement that must always remain visible.
     */
    private function require_business_tier(): ?\WP_Error
    {
        if (! $this->license_manager->is_tier_available(LicenseTier::BUSINESS)) {
            return new \WP_Error('nobatyar_feature_locked', __('کارت‌های هدیه ویژگی پلن Business است.', 'nobatyar-booking'), ['status' => 403]);
        }

        return null;
    }

    /**
     * @param array $args code?, initial_balance, expires_at?, recipient_name?, recipient_email?, note?, is_active?
     * @return int|\WP_Error gift card id on success
     */
    public function create_gift_card(array $args)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $validation_error = $this->validate_gift_card_args($args);

        if ($validation_error) {
            return $validation_error;
        }

        $code = '' !== trim((string) ($args['code'] ?? ''))
            ? trim((string) $args['code'])
            : $this->generate_unique_code();

        if ($this->gift_card_repository->find_by_code($code)) {
            return new \WP_Error('nobatyar_duplicate_gift_card_code', __('این کد کارت هدیه قبلاً ثبت شده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        return $this->gift_card_repository->create($this->normalize_gift_card_args($args, $code));
    }

    /**
     * Only metadata (code, expiry, active flag, recipient, note) is editable
     * here — balance is never touched by an update, since it must always
     * reflect actual redemptions (see GiftCardRepository::update()).
     *
     * @return true|\WP_Error
     */
    public function update_gift_card(int $id, array $args)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        if (! $this->gift_card_repository->find($id)) {
            return new \WP_Error('nobatyar_gift_card_not_found', __('کارت هدیه یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        $code = trim((string) ($args['code'] ?? ''));

        if ('' === $code) {
            return new \WP_Error('nobatyar_invalid_gift_card_code', __('کد کارت هدیه را وارد کنید.', 'nobatyar-booking'), ['status' => 422]);
        }

        $existing = $this->gift_card_repository->find_by_code($code);

        if ($existing && (int) $existing['id'] !== $id) {
            return new \WP_Error('nobatyar_duplicate_gift_card_code', __('این کد کارت هدیه قبلاً ثبت شده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        $this->gift_card_repository->update($id, [
            'code'            => $code,
            'expires_at'      => '' === ($args['expires_at'] ?? '') ? null : $args['expires_at'],
            'is_active'       => $args['is_active'] ?? true,
            'recipient_name'  => $args['recipient_name'] ?? null,
            'recipient_email' => $args['recipient_email'] ?? null,
            'note'            => $args['note'] ?? null,
        ]);

        return true;
    }

    /**
     * @return true|\WP_Error
     */
    public function delete_gift_card(int $id)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $this->gift_card_repository->delete($id);

        return true;
    }

    /**
     * Validates a gift card code without redeeming it — shared by the
     * public preview endpoint and by BookingEngine::book() right before a
     * gift card is attached to a booking.
     *
     * @return array|\WP_Error the gift card row on success
     */
    public function validate(string $code)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $code = trim($code);

        if ('' === $code) {
            return new \WP_Error('nobatyar_invalid_gift_card_code', __('کد کارت هدیه را وارد کنید.', 'nobatyar-booking'), ['status' => 422]);
        }

        $gift_card = $this->gift_card_repository->find_by_code($code);

        if (! $gift_card || ! (int) $gift_card['is_active']) {
            return new \WP_Error('nobatyar_invalid_gift_card', __('کد کارت هدیه معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        $now = current_time('mysql');

        if (! empty($gift_card['expires_at']) && $gift_card['expires_at'] < $now) {
            return new \WP_Error('nobatyar_gift_card_expired', __('این کارت هدیه منقضی شده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if ((float) $gift_card['remaining_balance'] <= 0) {
            return new \WP_Error('nobatyar_gift_card_no_balance', __('موجودی این کارت هدیه به پایان رسیده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        return $gift_card;
    }

    /**
     * Deducts a previously-locked-in redemption amount from a gift card's
     * balance — called by BookingEngine::book() once a validated gift card
     * is actually attached to a new booking, mirroring
     * CouponEngine::mark_used() / PackageRepository::decrement_purchase_sessions().
     */
    public function redeem(int $gift_card_id, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->gift_card_repository->deduct_balance($gift_card_id, $amount);
    }

    private function validate_gift_card_args(array $args): ?\WP_Error
    {
        if ((float) ($args['initial_balance'] ?? 0) <= 0) {
            return new \WP_Error('nobatyar_invalid_initial_balance', __('موجودی اولیه باید بزرگ‌تر از صفر باشد.', 'nobatyar-booking'), ['status' => 422]);
        }

        return null;
    }

    private function normalize_gift_card_args(array $args, string $code): array
    {
        return [
            'code'            => $code,
            'initial_balance' => (float) $args['initial_balance'],
            'expires_at'      => '' === ($args['expires_at'] ?? '') ? null : $args['expires_at'],
            'recipient_name'  => $args['recipient_name'] ?? null,
            'recipient_email' => $args['recipient_email'] ?? null,
            'note'            => $args['note'] ?? null,
            'is_active'       => $args['is_active'] ?? true,
        ];
    }

    /**
     * Auto-generates a short, human-typeable code when the admin leaves the
     * code field blank — collision-checked against find_by_code() the same
     * way an admin-supplied duplicate code is rejected above.
     */
    private function generate_unique_code(): string
    {
        do {
            $code = 'GIFT-' . strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 8));
        } while ($this->gift_card_repository->find_by_code($code));

        return $code;
    }
}

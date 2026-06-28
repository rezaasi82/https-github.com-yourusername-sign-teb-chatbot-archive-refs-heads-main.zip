<?php

namespace Nobatyar\Coupons;

use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;

if (! defined('ABSPATH')) {
    exit;
}

class CouponEngine
{
    private CouponRepository $coupon_repository;
    private LicenseManager $license_manager;

    public function __construct(CouponRepository $coupon_repository, LicenseManager $license_manager)
    {
        $this->coupon_repository = $coupon_repository;
        $this->license_manager   = $license_manager;
    }

    /**
     * Coupons are gated at Pro — the one Phase 9 feature available below
     * Business (see LicenseTier's own docblock: "Coupons: Pro+Business").
     * Unlike PackageEngine's read lookup, validate() is gated too: checking a
     * code IS the discount feature itself, not a check against a
     * pre-existing paid entitlement that must always remain visible.
     */
    private function require_pro_tier(): ?\WP_Error
    {
        if (! $this->license_manager->is_tier_available(LicenseTier::PRO)) {
            return new \WP_Error('nobatyar_feature_locked', __('کد تخفیف ویژگی پلن Pro و Business است.', 'nobatyar-booking'), ['status' => 403]);
        }

        return null;
    }

    /**
     * @param array $args code, discount_type, discount_value, service_id?, max_uses?, valid_from?, valid_until?, min_amount?, is_active?
     * @return int|\WP_Error coupon id on success
     */
    public function create_coupon(array $args)
    {
        $locked = $this->require_pro_tier();

        if ($locked) {
            return $locked;
        }

        $validation_error = $this->validate_coupon_args($args);

        if ($validation_error) {
            return $validation_error;
        }

        $code = trim((string) $args['code']);

        if ($this->coupon_repository->find_by_code($code)) {
            return new \WP_Error('nobatyar_duplicate_coupon_code', __('این کد تخفیف قبلاً ثبت شده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        return $this->coupon_repository->create($this->normalize_coupon_args($args, $code));
    }

    /**
     * @return true|\WP_Error
     */
    public function update_coupon(int $id, array $args)
    {
        $locked = $this->require_pro_tier();

        if ($locked) {
            return $locked;
        }

        if (! $this->coupon_repository->find($id)) {
            return new \WP_Error('nobatyar_coupon_not_found', __('کد تخفیف یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        $validation_error = $this->validate_coupon_args($args);

        if ($validation_error) {
            return $validation_error;
        }

        $code     = trim((string) $args['code']);
        $existing = $this->coupon_repository->find_by_code($code);

        if ($existing && (int) $existing['id'] !== $id) {
            return new \WP_Error('nobatyar_duplicate_coupon_code', __('این کد تخفیف قبلاً ثبت شده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        $this->coupon_repository->update($id, $this->normalize_coupon_args($args, $code));

        return true;
    }

    /**
     * @return true|\WP_Error
     */
    public function delete_coupon(int $id)
    {
        $locked = $this->require_pro_tier();

        if ($locked) {
            return $locked;
        }

        $this->coupon_repository->delete($id);

        return true;
    }

    /**
     * Validates a coupon code against a target service/amount without
     * marking it used — shared by the public preview endpoint and by
     * BookingEngine::book() right before a coupon is attached to a booking.
     *
     * @return array|\WP_Error the coupon row on success
     */
    public function validate(string $code, int $service_id, ?float $amount = null)
    {
        $locked = $this->require_pro_tier();

        if ($locked) {
            return $locked;
        }

        $code = trim($code);

        if ('' === $code) {
            return new \WP_Error('nobatyar_invalid_coupon_code', __('کد تخفیف را وارد کنید.', 'nobatyar-booking'), ['status' => 422]);
        }

        $coupon = $this->coupon_repository->find_by_code($code);

        if (! $coupon || ! (int) $coupon['is_active']) {
            return new \WP_Error('nobatyar_invalid_coupon', __('کد تخفیف معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        $now = current_time('mysql');

        if (! empty($coupon['valid_from']) && $coupon['valid_from'] > $now) {
            return new \WP_Error('nobatyar_coupon_not_yet_valid', __('این کد تخفیف هنوز فعال نشده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if (! empty($coupon['valid_until']) && $coupon['valid_until'] < $now) {
            return new \WP_Error('nobatyar_coupon_expired', __('این کد تخفیف منقضی شده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if (! empty($coupon['max_uses']) && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            return new \WP_Error('nobatyar_coupon_usage_exceeded', __('ظرفیت استفاده از این کد تخفیف به پایان رسیده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if (! empty($coupon['service_id']) && (int) $coupon['service_id'] !== $service_id) {
            return new \WP_Error('nobatyar_coupon_service_mismatch', __('این کد تخفیف برای خدمت انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 422]);
        }

        if (! empty($coupon['min_amount']) && null !== $amount && $amount < (float) $coupon['min_amount']) {
            return new \WP_Error('nobatyar_coupon_min_amount', __('مبلغ این نوبت کمتر از حداقل مبلغ مجاز برای این کد تخفیف است.', 'nobatyar-booking'), ['status' => 422]);
        }

        return $coupon;
    }

    /**
     * Marks one redemption against a coupon — called by BookingEngine::book()
     * once a validated coupon is actually attached to a new booking, mirroring
     * PackageRepository::decrement_purchase_sessions()'s atomic counter update.
     */
    public function mark_used(int $coupon_id): void
    {
        $this->coupon_repository->increment_used_count($coupon_id);
    }

    private function validate_coupon_args(array $args): ?\WP_Error
    {
        if ('' === trim((string) ($args['code'] ?? ''))) {
            return new \WP_Error('nobatyar_invalid_coupon_code', __('کد تخفیف را وارد کنید.', 'nobatyar-booking'), ['status' => 422]);
        }

        if (! CouponDiscountType::is_valid((string) ($args['discount_type'] ?? ''))) {
            return new \WP_Error('nobatyar_invalid_discount_type', __('نوع تخفیف نامعتبر است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if ((float) ($args['discount_value'] ?? 0) <= 0) {
            return new \WP_Error('nobatyar_invalid_discount_value', __('مقدار تخفیف باید بزرگ‌تر از صفر باشد.', 'nobatyar-booking'), ['status' => 422]);
        }

        return null;
    }

    private function normalize_coupon_args(array $args, string $code): array
    {
        return [
            'code'           => $code,
            'discount_type'  => $args['discount_type'],
            'discount_value' => (float) $args['discount_value'],
            'service_id'     => ! empty($args['service_id']) ? (int) $args['service_id'] : null,
            'max_uses'       => ! empty($args['max_uses']) ? (int) $args['max_uses'] : null,
            'valid_from'     => $args['valid_from'] ?? null,
            'valid_until'    => $args['valid_until'] ?? null,
            'min_amount'     => isset($args['min_amount']) && '' !== $args['min_amount'] ? (float) $args['min_amount'] : null,
            'is_active'      => $args['is_active'] ?? true,
        ];
    }
}

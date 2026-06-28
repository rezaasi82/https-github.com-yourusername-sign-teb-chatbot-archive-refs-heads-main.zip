<?php

namespace Nobatyar\Admin\Coupons;

use Nobatyar\Coupons\CouponDiscountType;
use Nobatyar\Coupons\CouponEngine;
use Nobatyar\Coupons\CouponRepository;
use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Mirrors PackagesPage: coupons are an entirely new Pro+Business-tier
 * concept, so the whole create/edit/delete capability is gated (not just
 * one field), and writes are delegated to CouponEngine rather than touching
 * CouponRepository directly. Existing coupons (created during a prior
 * Pro/Business period) still display read-only if the site later downgrades.
 */
class CouponsPage
{
    private CouponEngine $coupon_engine;
    private CouponRepository $coupon_repository;
    private ServiceRepository $service_repository;
    private LicenseManager $license_manager;

    public function __construct(
        CouponEngine $coupon_engine,
        CouponRepository $coupon_repository,
        ServiceRepository $service_repository,
        LicenseManager $license_manager
    ) {
        $this->coupon_engine      = $coupon_engine;
        $this->coupon_repository  = $coupon_repository;
        $this->service_repository = $service_repository;
        $this->license_manager    = $license_manager;
    }

    public function handle_submission(): void
    {
        if (! isset($_POST['nobatyar_coupons_action'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_coupons_save', 'nobatyar_coupons_nonce')) {
            return;
        }

        $action = sanitize_key($_POST['nobatyar_coupons_action']);

        switch ($action) {
            case 'save':
                $this->save();
                break;
            case 'delete':
                $this->delete();
                break;
        }
    }

    private function save(): void
    {
        $id   = isset($_POST['coupon_id']) ? absint($_POST['coupon_id']) : 0;
        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));

        if ('' === $code) {
            return;
        }

        $data = [
            'code'           => $code,
            'discount_type'  => sanitize_key($_POST['discount_type'] ?? CouponDiscountType::PERCENT),
            'discount_value' => (float) ($_POST['discount_value'] ?? 0),
            'service_id'     => absint($_POST['service_id'] ?? 0),
            'max_uses'       => '' === ($_POST['max_uses'] ?? '') ? null : absint($_POST['max_uses']),
            'valid_from'     => '' === ($_POST['valid_from'] ?? '') ? null : sanitize_text_field($_POST['valid_from']),
            'valid_until'    => '' === ($_POST['valid_until'] ?? '') ? null : sanitize_text_field($_POST['valid_until']),
            'min_amount'     => '' === ($_POST['min_amount'] ?? '') ? null : (float) $_POST['min_amount'],
            'is_active'      => ! empty($_POST['is_active']),
        ];

        if ($id) {
            $this->coupon_engine->update_coupon($id, $data);
        } else {
            $this->coupon_engine->create_coupon($data);
        }
    }

    private function delete(): void
    {
        $id = isset($_POST['coupon_id']) ? absint($_POST['coupon_id']) : 0;

        if ($id) {
            $this->coupon_engine->delete_coupon($id);
        }
    }

    public function render(?int $editing_id = null): string
    {
        $coupons_available = $this->license_manager->is_tier_available(LicenseTier::PRO);
        $coupons           = $this->coupon_repository->all();
        $services           = $this->index_services_by_id($this->service_repository->all(false));
        $editing            = $editing_id ? $this->coupon_repository->find($editing_id) : null;

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-coupons">
            <h1><?php esc_html_e('مدیریت کدهای تخفیف', 'nobatyar-booking'); ?></h1>

            <?php if (! $coupons_available) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('کدهای تخفیف ویژگی پلن Pro و Business است. کدهای قبلی (در صورت وجود) فقط قابل مشاهده هستند و امکان ایجاد یا ویرایش کد جدید وجود ندارد.', 'nobatyar-booking'); ?></p>
                </div>
            <?php else : ?>
                <?php echo $this->render_form($editing, $services); ?>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('کد', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('نوع تخفیف', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('مقدار', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('خدمت', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('استفاده‌شده / حداکثر', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'nobatyar-booking'); ?></th>
                        <?php if ($coupons_available) : ?>
                            <th><?php esc_html_e('عملیات', 'nobatyar-booking'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon) : ?>
                        <tr>
                            <td><?php echo esc_html($coupon['code']); ?></td>
                            <td><?php echo esc_html($coupon['discount_type'] === CouponDiscountType::PERCENT ? __('درصدی', 'nobatyar-booking') : __('مقدار ثابت', 'nobatyar-booking')); ?></td>
                            <td><?php echo esc_html($coupon['discount_value']); ?></td>
                            <td><?php echo esc_html($coupon['service_id'] ? ($services[(int) $coupon['service_id']]['name'] ?? '') : __('همه خدمات', 'nobatyar-booking')); ?></td>
                            <td><?php echo esc_html($coupon['used_count'] . ' / ' . ($coupon['max_uses'] ?: __('نامحدود', 'nobatyar-booking'))); ?></td>
                            <td><?php echo $coupon['is_active'] ? esc_html__('فعال', 'nobatyar-booking') : esc_html__('غیرفعال', 'nobatyar-booking'); ?></td>
                            <?php if ($coupons_available) : ?>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['edit' => $coupon['id']])); ?>"><?php esc_html_e('ویرایش', 'nobatyar-booking'); ?></a>
                                    |
                                    <form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('حذف شود؟', 'nobatyar-booking')); ?>');">
                                        <?php wp_nonce_field('nobatyar_coupons_save', 'nobatyar_coupons_nonce'); ?>
                                        <input type="hidden" name="nobatyar_coupons_action" value="delete">
                                        <input type="hidden" name="coupon_id" value="<?php echo esc_attr($coupon['id']); ?>">
                                        <button type="submit" class="button-link-delete"><?php esc_html_e('حذف', 'nobatyar-booking'); ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $coupons) : ?>
                        <tr><td colspan="7"><?php esc_html_e('هنوز کد تخفیفی ثبت نشده است.', 'nobatyar-booking'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_form(?array $editing, array $services): string
    {
        ob_start();
        ?>
        <form method="post" class="nobatyar-coupons-form">
            <?php wp_nonce_field('nobatyar_coupons_save', 'nobatyar_coupons_nonce'); ?>
            <input type="hidden" name="nobatyar_coupons_action" value="save">
            <input type="hidden" name="coupon_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">

            <p>
                <label><?php esc_html_e('کد تخفیف', 'nobatyar-booking'); ?></label>
                <input type="text" name="code" value="<?php echo esc_attr($editing['code'] ?? ''); ?>" required>
            </p>
            <p>
                <label><?php esc_html_e('نوع تخفیف', 'nobatyar-booking'); ?></label>
                <select name="discount_type">
                    <?php foreach (CouponDiscountType::all() as $type) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($editing['discount_type'] ?? CouponDiscountType::PERCENT, $type); ?>>
                            <?php echo esc_html(CouponDiscountType::PERCENT === $type ? __('درصدی', 'nobatyar-booking') : __('مقدار ثابت', 'nobatyar-booking')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label><?php esc_html_e('مقدار تخفیف', 'nobatyar-booking'); ?></label>
                <input type="number" step="0.01" name="discount_value" value="<?php echo esc_attr($editing['discount_value'] ?? ''); ?>" required>
            </p>
            <p>
                <label><?php esc_html_e('خدمت (اختیاری)', 'nobatyar-booking'); ?></label>
                <select name="service_id">
                    <option value=""><?php esc_html_e('همه خدمات', 'nobatyar-booking'); ?></option>
                    <?php foreach ($services as $service) : ?>
                        <option value="<?php echo esc_attr($service['id']); ?>" <?php selected($editing['service_id'] ?? '', $service['id']); ?>><?php echo esc_html($service['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label><?php esc_html_e('حداکثر تعداد استفاده (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="number" name="max_uses" min="1" value="<?php echo esc_attr($editing['max_uses'] ?? ''); ?>">
                <br><span class="description"><?php esc_html_e('خالی بگذارید برای استفاده نامحدود.', 'nobatyar-booking'); ?></span>
            </p>
            <p>
                <label><?php esc_html_e('حداقل مبلغ نوبت (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="number" step="0.01" name="min_amount" value="<?php echo esc_attr($editing['min_amount'] ?? ''); ?>">
            </p>
            <p>
                <label><?php esc_html_e('معتبر از تاریخ (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="text" name="valid_from" value="<?php echo esc_attr($editing['valid_from'] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
            </p>
            <p>
                <label><?php esc_html_e('معتبر تا تاریخ (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="text" name="valid_until" value="<?php echo esc_attr($editing['valid_until'] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
            </p>
            <p>
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php checked(null === $editing || ! empty($editing['is_active'])); ?>>
                    <?php esc_html_e('فعال', 'nobatyar-booking'); ?>
                </label>
            </p>

            <button type="submit" class="button button-primary">
                <?php echo $editing ? esc_html__('به‌روزرسانی', 'nobatyar-booking') : esc_html__('افزودن', 'nobatyar-booking'); ?>
            </button>
        </form>
        <?php

        return ob_get_clean();
    }

    private function index_services_by_id(array $services): array
    {
        $indexed = [];

        foreach ($services as $service) {
            $indexed[(int) $service['id']] = $service;
        }

        return $indexed;
    }
}

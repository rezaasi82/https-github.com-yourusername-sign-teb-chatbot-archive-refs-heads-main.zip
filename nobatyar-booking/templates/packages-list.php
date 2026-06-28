<?php
/**
 * @var array $packages
 * @var array $services
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="nobatyar-booking-form-wrap">
    <div id="nobatyar-packages-list" class="nobatyar-packages-list">
        <h3><?php esc_html_e('پکیج‌های نشست', 'nobatyar-booking'); ?></h3>

        <table class="nobatyar-packages-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('نام پکیج', 'nobatyar-booking'); ?></th>
                    <th><?php esc_html_e('خدمت', 'nobatyar-booking'); ?></th>
                    <th><?php esc_html_e('تعداد نشست', 'nobatyar-booking'); ?></th>
                    <th><?php esc_html_e('قیمت', 'nobatyar-booking'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $package) : ?>
                    <tr>
                        <td><?php echo esc_html($package['name']); ?></td>
                        <td><?php echo esc_html($services[(int) $package['service_id']]['name'] ?? ''); ?></td>
                        <td><?php echo esc_html($package['session_count']); ?></td>
                        <td><?php echo esc_html($package['price']); ?></td>
                        <td>
                            <button type="button" class="nobatyar-package-purchase-btn" data-package-id="<?php echo esc_attr($package['id']); ?>">
                                <?php esc_html_e('خرید', 'nobatyar-booking'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (! $packages) : ?>
                    <tr><td colspan="5"><?php esc_html_e('در حال حاضر پکیجی برای فروش موجود نیست.', 'nobatyar-booking'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <form id="nobatyar-package-purchase-form" class="nobatyar-booking-form" hidden>
            <input type="hidden" id="nobatyar-package-id" name="package_id" />

            <p class="nobatyar-field">
                <label for="nobatyar-package-customer-name"><?php esc_html_e('نام و نام خانوادگی', 'nobatyar-booking'); ?></label>
                <input type="text" id="nobatyar-package-customer-name" name="customer_name" required />
            </p>

            <p class="nobatyar-field">
                <label for="nobatyar-package-customer-phone"><?php esc_html_e('شماره موبایل', 'nobatyar-booking'); ?></label>
                <input type="tel" id="nobatyar-package-customer-phone" name="customer_phone" required />
            </p>

            <p class="nobatyar-field">
                <label for="nobatyar-package-customer-email"><?php esc_html_e('ایمیل (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="email" id="nobatyar-package-customer-email" name="customer_email" />
            </p>

            <p class="nobatyar-field">
                <button type="submit"><?php esc_html_e('ثبت خرید', 'nobatyar-booking'); ?></button>
            </p>

            <div id="nobatyar-package-message" class="nobatyar-booking-message" role="status"></div>
        </form>
    </div>
</div>

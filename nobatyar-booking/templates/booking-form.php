<?php
/**
 * @var array $providers
 * @var array $services
 */

use Nobatyar\Labels\TerminologyMap;

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="nobatyar-booking-form-wrap">
    <form id="nobatyar-booking-form" class="nobatyar-booking-form">
        <p class="nobatyar-field">
            <label for="nobatyar-service"><?php echo esc_html(TerminologyMap::get('service')); ?></label>
            <select id="nobatyar-service" name="service_id" required>
                <option value=""><?php esc_html_e('انتخاب کنید', 'nobatyar-booking'); ?></option>
                <?php foreach ($services as $service) : ?>
                    <option value="<?php echo esc_attr($service['id']); ?>"><?php echo esc_html($service['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p class="nobatyar-field">
            <label for="nobatyar-provider"><?php echo esc_html(TerminologyMap::get('provider')); ?></label>
            <select id="nobatyar-provider" name="provider_id" required>
                <option value=""><?php esc_html_e('انتخاب کنید', 'nobatyar-booking'); ?></option>
                <?php foreach ($providers as $provider) : ?>
                    <option value="<?php echo esc_attr($provider['id']); ?>"><?php echo esc_html($provider['label_override'] ?: $provider['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p class="nobatyar-field">
            <label for="nobatyar-date"><?php esc_html_e('تاریخ', 'nobatyar-booking'); ?></label>
            <input type="date" id="nobatyar-date" name="date" required />
        </p>

        <p class="nobatyar-field">
            <label for="nobatyar-slot"><?php esc_html_e('بازه زمانی', 'nobatyar-booking'); ?></label>
            <select id="nobatyar-slot" name="booking_datetime" required>
                <option value=""><?php esc_html_e('ابتدا سرویس‌دهنده، خدمت و تاریخ را انتخاب کنید', 'nobatyar-booking'); ?></option>
            </select>
        </p>

        <p class="nobatyar-field">
            <label for="nobatyar-customer-name"><?php echo esc_html(TerminologyMap::get('customer')); ?></label>
            <input type="text" id="nobatyar-customer-name" name="customer_name" required />
        </p>

        <p class="nobatyar-field">
            <label for="nobatyar-customer-phone"><?php esc_html_e('شماره موبایل', 'nobatyar-booking'); ?></label>
            <input type="tel" id="nobatyar-customer-phone" name="customer_phone" required />
        </p>

        <p class="nobatyar-field">
            <label for="nobatyar-customer-email"><?php esc_html_e('ایمیل (اختیاری)', 'nobatyar-booking'); ?></label>
            <input type="email" id="nobatyar-customer-email" name="customer_email" />
        </p>

        <p class="nobatyar-field">
            <button type="submit"><?php esc_html_e('ثبت نوبت', 'nobatyar-booking'); ?></button>
        </p>

        <div id="nobatyar-booking-message" class="nobatyar-booking-message" role="status"></div>
    </form>
</div>

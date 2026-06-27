<?php
/**
 * @var array $providers
 * @var array $services
 * @var bool $recurring_enabled
 * @var array $recurrence_frequencies
 */

use Nobatyar\Booking\RecurrenceFrequency;
use Nobatyar\Labels\TerminologyMap;

if (! defined('ABSPATH')) {
    exit;
}

$recurrence_frequency_labels = [
    RecurrenceFrequency::WEEKLY   => __('هفتگی', 'nobatyar-booking'),
    RecurrenceFrequency::BIWEEKLY => __('دو هفته یک‌بار', 'nobatyar-booking'),
    RecurrenceFrequency::MONTHLY  => __('ماهانه', 'nobatyar-booking'),
];
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

        <p class="nobatyar-field nobatyar-jalali-field">
            <label for="nobatyar-date-display"><?php esc_html_e('تاریخ', 'nobatyar-booking'); ?></label>
            <input
                type="text"
                id="nobatyar-date-display"
                class="nobatyar-jalali-display"
                readonly="readonly"
                autocomplete="off"
                placeholder="<?php esc_attr_e('انتخاب تاریخ', 'nobatyar-booking'); ?>"
            />
            <input type="hidden" id="nobatyar-date" name="date" />
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

        <?php if (! empty($recurring_enabled)) : ?>
            <p class="nobatyar-field nobatyar-recurrence-toggle">
                <label>
                    <input type="checkbox" id="nobatyar-recurrence-enable" name="recurrence_enable" />
                    <?php esc_html_e('این نوبت به‌صورت تکرارشونده ثبت شود', 'nobatyar-booking'); ?>
                </label>
            </p>

            <div id="nobatyar-recurrence-fields" class="nobatyar-recurrence-fields" hidden>
                <p class="nobatyar-field">
                    <label for="nobatyar-recurrence-frequency"><?php esc_html_e('الگوی تکرار', 'nobatyar-booking'); ?></label>
                    <select id="nobatyar-recurrence-frequency" name="recurrence_frequency">
                        <?php foreach ($recurrence_frequencies as $frequency) : ?>
                            <option value="<?php echo esc_attr($frequency); ?>"><?php echo esc_html($recurrence_frequency_labels[$frequency] ?? $frequency); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p class="nobatyar-field">
                    <label for="nobatyar-recurrence-occurrences"><?php esc_html_e('تعداد نوبت‌ها', 'nobatyar-booking'); ?></label>
                    <input type="number" id="nobatyar-recurrence-occurrences" name="recurrence_occurrences" min="2" max="52" value="4" />
                </p>
            </div>
        <?php endif; ?>

        <p class="nobatyar-field">
            <button type="submit"><?php esc_html_e('ثبت نوبت', 'nobatyar-booking'); ?></button>
        </p>

        <div id="nobatyar-booking-message" class="nobatyar-booking-message" role="status"></div>
    </form>
</div>

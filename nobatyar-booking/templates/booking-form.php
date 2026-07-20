<?php
/**
 * @var array $providers
 * @var array $services
 * @var bool  $recurring_enabled
 * @var array $recurrence_frequencies
 * @var bool  $packages_redeem_enabled
 * @var bool  $coupons_enabled
 * @var bool  $gift_cards_enabled
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
<div class="nby-wrap">

    <nav class="nby-steps" aria-label="<?php esc_attr_e('مراحل رزرو', 'nobatyar-booking'); ?>">
        <div class="nby-step is-active" data-step="1" aria-current="step">
            <span class="nby-step__bubble">۱</span>
            <span class="nby-step__label"><?php esc_html_e('خدمت', 'nobatyar-booking'); ?></span>
        </div>
        <span class="nby-step__connector" aria-hidden="true"></span>
        <div class="nby-step" data-step="2">
            <span class="nby-step__bubble">۲</span>
            <span class="nby-step__label"><?php esc_html_e('تاریخ', 'nobatyar-booking'); ?></span>
        </div>
        <span class="nby-step__connector" aria-hidden="true"></span>
        <div class="nby-step" data-step="3">
            <span class="nby-step__bubble">۳</span>
            <span class="nby-step__label"><?php esc_html_e('اطلاعات', 'nobatyar-booking'); ?></span>
        </div>
    </nav>

    <div id="nby-error" class="nby-error-bar" role="alert" hidden></div>

    <form id="nby-form" class="nby-form" novalidate>

        <!-- Panel 1: Service & Provider -->
        <div class="nby-panel" data-panel="1">
            <div class="nby-field">
                <label class="nby-label" for="nby-service">
                    <?php echo esc_html(TerminologyMap::get('service')); ?>
                </label>
                <select id="nby-service" name="service_id" class="nby-select">
                    <option value=""><?php esc_html_e('انتخاب کنید', 'nobatyar-booking'); ?></option>
                    <?php foreach ($services as $service) : ?>
                        <option value="<?php echo esc_attr($service['id']); ?>">
                            <?php echo esc_html($service['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nby-field">
                <label class="nby-label" for="nby-provider">
                    <?php echo esc_html(TerminologyMap::get('provider')); ?>
                </label>
                <select id="nby-provider" name="provider_id" class="nby-select">
                    <option value=""><?php esc_html_e('انتخاب کنید', 'nobatyar-booking'); ?></option>
                    <?php foreach ($providers as $provider) : ?>
                        <option value="<?php echo esc_attr($provider['id']); ?>">
                            <?php echo esc_html($provider['label_override'] ?: $provider['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nby-actions">
                <button type="button" id="nby-next-1" class="nby-btn nby-btn--primary nby-btn--block">
                    <?php esc_html_e('مرحله بعد', 'nobatyar-booking'); ?>
                </button>
            </div>
        </div>

        <!-- Panel 2: Date & Slot -->
        <div class="nby-panel" data-panel="2" hidden>
            <div class="nby-field nobatyar-jalali-field">
                <label class="nby-label" for="nobatyar-date-display">
                    <?php esc_html_e('تاریخ', 'nobatyar-booking'); ?>
                </label>
                <input
                    type="text"
                    id="nobatyar-date-display"
                    class="nby-input nobatyar-jalali-display"
                    readonly="readonly"
                    autocomplete="off"
                    placeholder="<?php esc_attr_e('انتخاب تاریخ', 'nobatyar-booking'); ?>"
                />
                <input type="hidden" id="nobatyar-date" name="date" />
            </div>

            <div class="nby-field">
                <label class="nby-label">
                    <?php esc_html_e('بازه زمانی', 'nobatyar-booking'); ?>
                </label>
                <div
                    id="nby-slots"
                    class="nby-slots"
                    role="group"
                    aria-label="<?php esc_attr_e('انتخاب بازه زمانی', 'nobatyar-booking'); ?>"
                >
                    <p class="nby-slots__hint"><?php esc_html_e('ابتدا تاریخ را انتخاب کنید', 'nobatyar-booking'); ?></p>
                </div>
                <input type="hidden" id="nby-slot" name="booking_datetime" />
            </div>

            <div class="nby-actions nby-actions--split">
                <button type="button" id="nby-back-2" class="nby-btn nby-btn--ghost">
                    <?php esc_html_e('بازگشت', 'nobatyar-booking'); ?>
                </button>
                <button type="button" id="nby-next-2" class="nby-btn nby-btn--primary">
                    <?php esc_html_e('مرحله بعد', 'nobatyar-booking'); ?>
                </button>
            </div>
        </div>

        <!-- Panel 3: Customer Info + Extras -->
        <div class="nby-panel" data-panel="3" hidden>
            <div class="nby-field">
                <label class="nby-label" for="nby-customer-name">
                    <?php echo esc_html(TerminologyMap::get('customer')); ?>
                </label>
                <input
                    type="text"
                    id="nby-customer-name"
                    name="customer_name"
                    class="nby-input"
                    autocomplete="name"
                />
            </div>

            <div class="nby-field">
                <label class="nby-label" for="nby-customer-phone">
                    <?php esc_html_e('شماره موبایل', 'nobatyar-booking'); ?>
                </label>
                <input
                    type="tel"
                    id="nby-customer-phone"
                    name="customer_phone"
                    class="nby-input"
                    autocomplete="tel"
                    dir="ltr"
                />
            </div>

            <div class="nby-field">
                <label class="nby-label" for="nby-customer-email">
                    <?php esc_html_e('ایمیل (اختیاری)', 'nobatyar-booking'); ?>
                </label>
                <input
                    type="email"
                    id="nby-customer-email"
                    name="customer_email"
                    class="nby-input"
                    autocomplete="email"
                    dir="ltr"
                />
            </div>

            <?php if (! empty($coupons_enabled)) : ?>
                <div class="nby-field">
                    <label class="nby-label" for="nby-coupon-code">
                        <?php esc_html_e('کد تخفیف (اختیاری)', 'nobatyar-booking'); ?>
                    </label>
                    <div class="nby-input-group">
                        <input
                            type="text"
                            id="nby-coupon-code"
                            name="coupon_code"
                            class="nby-input"
                            autocomplete="off"
                            dir="ltr"
                        />
                        <button type="button" id="nby-coupon-apply" class="nby-btn nby-btn--secondary">
                            <?php esc_html_e('اعمال', 'nobatyar-booking'); ?>
                        </button>
                    </div>
                    <span id="nby-coupon-result" class="nby-promo-result" aria-live="polite"></span>
                </div>
            <?php endif; ?>

            <?php if (! empty($gift_cards_enabled)) : ?>
                <div class="nby-field">
                    <label class="nby-label" for="nby-gift-card-code">
                        <?php esc_html_e('کد کارت هدیه (اختیاری)', 'nobatyar-booking'); ?>
                    </label>
                    <div class="nby-input-group">
                        <input
                            type="text"
                            id="nby-gift-card-code"
                            name="gift_card_code"
                            class="nby-input"
                            autocomplete="off"
                            dir="ltr"
                        />
                        <button type="button" id="nby-gift-card-apply" class="nby-btn nby-btn--secondary">
                            <?php esc_html_e('اعمال', 'nobatyar-booking'); ?>
                        </button>
                    </div>
                    <span id="nby-gift-card-result" class="nby-promo-result" aria-live="polite"></span>
                </div>
            <?php endif; ?>

            <?php if (! empty($packages_redeem_enabled)) : ?>
                <div class="nby-field">
                    <label class="nby-toggle">
                        <input
                            type="checkbox"
                            id="nby-use-package"
                            name="use_package"
                            class="nby-toggle__input"
                        />
                        <span class="nby-toggle__track" aria-hidden="true"></span>
                        <span class="nby-toggle__text">
                            <?php esc_html_e('استفاده از اعتبار پکیج', 'nobatyar-booking'); ?>
                        </span>
                    </label>
                </div>

                <div id="nby-package-fields" class="nby-expandable" hidden>
                    <div class="nby-field">
                        <button
                            type="button"
                            id="nby-package-lookup"
                            class="nby-btn nby-btn--secondary nby-btn--block"
                        >
                            <?php esc_html_e('بررسی اعتبار پکیج با شماره موبایل', 'nobatyar-booking'); ?>
                        </button>
                    </div>
                    <div class="nby-field">
                        <label class="nby-label" for="nby-package-purchase">
                            <?php esc_html_e('پکیج خریداری‌شده', 'nobatyar-booking'); ?>
                        </label>
                        <select id="nby-package-purchase" name="package_purchase_id" class="nby-select">
                            <option value="">
                                <?php esc_html_e('ابتدا شماره موبایل را بررسی کنید', 'nobatyar-booking'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (! empty($recurring_enabled)) : ?>
                <div class="nby-field">
                    <label class="nby-toggle">
                        <input
                            type="checkbox"
                            id="nby-recurrence-enable"
                            name="recurrence_enable"
                            class="nby-toggle__input"
                        />
                        <span class="nby-toggle__track" aria-hidden="true"></span>
                        <span class="nby-toggle__text">
                            <?php esc_html_e('رزرو تکرارشونده', 'nobatyar-booking'); ?>
                        </span>
                    </label>
                </div>

                <div id="nby-recurrence-fields" class="nby-expandable" hidden>
                    <div class="nby-field">
                        <label class="nby-label" for="nby-recurrence-frequency">
                            <?php esc_html_e('الگوی تکرار', 'nobatyar-booking'); ?>
                        </label>
                        <select id="nby-recurrence-frequency" name="recurrence_frequency" class="nby-select">
                            <?php foreach ($recurrence_frequencies as $frequency) : ?>
                                <option value="<?php echo esc_attr($frequency); ?>">
                                    <?php echo esc_html($recurrence_frequency_labels[$frequency] ?? $frequency); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="nby-field">
                        <label class="nby-label" for="nby-recurrence-occurrences">
                            <?php esc_html_e('تعداد نوبت‌ها', 'nobatyar-booking'); ?>
                        </label>
                        <input
                            type="number"
                            id="nby-recurrence-occurrences"
                            name="recurrence_occurrences"
                            class="nby-input"
                            min="2"
                            max="52"
                            value="4"
                        />
                    </div>
                </div>
            <?php endif; ?>

            <div class="nby-actions nby-actions--split">
                <button type="button" id="nby-back-3" class="nby-btn nby-btn--ghost">
                    <?php esc_html_e('بازگشت', 'nobatyar-booking'); ?>
                </button>
                <button type="submit" id="nby-submit" class="nby-btn nby-btn--success">
                    <?php esc_html_e('ثبت نوبت', 'nobatyar-booking'); ?>
                </button>
            </div>
        </div>

        <!-- Success Panel -->
        <div class="nby-panel nby-panel--success" data-panel="success" hidden>
            <div class="nby-success">
                <div class="nby-success__icon" aria-hidden="true">
                    <svg viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg" width="64" height="64">
                        <circle cx="26" cy="26" r="24" stroke="currentColor" stroke-width="2.5"/>
                        <path d="M14 26.5l8 8 16-16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p id="nby-success-message" class="nby-success__message"></p>
                <button type="button" id="nby-book-another" class="nby-btn nby-btn--ghost">
                    <?php esc_html_e('رزرو نوبت دیگر', 'nobatyar-booking'); ?>
                </button>
            </div>
        </div>

    </form>

</div>

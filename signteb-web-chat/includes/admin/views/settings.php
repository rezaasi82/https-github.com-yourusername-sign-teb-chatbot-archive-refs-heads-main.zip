<?php
/**
 * Tabbed settings content (provider | clinic | appearance | license).
 *
 * @var SWC_Settings        $s
 * @var SWC_License_Manager $license
 * @var string              $tab
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=' . $tab)); ?>">
    <?php wp_nonce_field('swc_settings'); ?>
    <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">

    <?php if ($tab === 'provider') : ?>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فعال‌سازی چت‌بات', 'signteb-web-chat'); ?></th>
                <td><label><input type="checkbox" name="enabled" value="1" <?php checked($s->get('enabled', 1), 1); ?>> <?php esc_html_e('نمایش ویجت در سایت', 'signteb-web-chat'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('سرویس‌دهنده AI', 'signteb-web-chat'); ?></th>
                <td>
                    <select name="provider" id="swc-provider">
                        <option value="anthropic" <?php selected($s->active_provider(), 'anthropic'); ?>>Anthropic Claude</option>
                        <option value="openai" <?php selected($s->active_provider(), 'openai'); ?>>OpenAI</option>
                    </select>
                    <p class="description"><?php esc_html_e('فیلدهای کلید و مدلِ همان سرویس در پایین نمایش داده می‌شوند.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>

            <tbody class="swc-provider-block" data-provider="anthropic">
            <tr>
                <th><?php esc_html_e('کلید Anthropic', 'signteb-web-chat'); ?></th>
                <td>
                    <input type="password" name="api_key_anthropic" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('anthropic') ? esc_attr__('•••••••• (ذخیره‌شده)', 'signteb-web-chat') : 'sk-ant-…'; ?>">
                    <p class="description"><?php esc_html_e('رمزنگاری‌شده ذخیره می‌شود. برای تغییر، مقدار جدید وارد کنید.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل Anthropic', 'signteb-web-chat'); ?></th>
                <td>
                    <input list="swc-models-anthropic" name="model_anthropic" value="<?php echo esc_attr($s->get('model_anthropic', 'claude-haiku-4-5-20251001')); ?>" class="regular-text">
                    <datalist id="swc-models-anthropic">
                        <option value="claude-haiku-4-5-20251001"></option>
                        <option value="claude-sonnet-5"></option>
                        <option value="claude-opus-4-8"></option>
                    </datalist>
                    <p class="description"><?php esc_html_e('پیش‌فرض: مدل سبک و کم‌هزینه Haiku. قابل تغییر دستی.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            </tbody>

            <tbody class="swc-provider-block" data-provider="openai">
            <tr>
                <th><?php esc_html_e('کلید OpenAI', 'signteb-web-chat'); ?></th>
                <td>
                    <input type="password" name="api_key_openai" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('openai') ? esc_attr__('•••••••• (ذخیره‌شده)', 'signteb-web-chat') : 'sk-…'; ?>">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل OpenAI', 'signteb-web-chat'); ?></th>
                <td>
                    <input list="swc-models-openai" name="model_openai" value="<?php echo esc_attr($s->get('model_openai', 'gpt-4o-mini')); ?>" class="regular-text">
                    <datalist id="swc-models-openai">
                        <option value="gpt-4o-mini"></option>
                        <option value="gpt-4o"></option>
                        <option value="gpt-4.1-mini"></option>
                    </datalist>
                    <p class="description"><?php esc_html_e('پیش‌فرض: gpt-4o-mini. قابل تغییر دستی.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            </tbody>

            <tr>
                <th><?php esc_html_e('لحن', 'signteb-web-chat'); ?></th>
                <td>
                    <select name="tone">
                        <option value="friendly" <?php selected($s->get('tone'), 'friendly'); ?>><?php esc_html_e('دوستانه', 'signteb-web-chat'); ?></option>
                        <option value="formal" <?php selected($s->get('tone'), 'formal'); ?>><?php esc_html_e('رسمی', 'signteb-web-chat'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('زبان پاسخ', 'signteb-web-chat'); ?></th>
                <td>
                    <select name="language">
                        <option value="auto" <?php selected($s->get('language'), 'auto'); ?>><?php esc_html_e('خودکار', 'signteb-web-chat'); ?></option>
                        <option value="fa" <?php selected($s->get('language'), 'fa'); ?>>فارسی</option>
                        <option value="ar" <?php selected($s->get('language'), 'ar'); ?>>العربية</option>
                        <option value="en" <?php selected($s->get('language'), 'en'); ?>>English</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('محدودیت پیام در دقیقه', 'signteb-web-chat'); ?></th>
                <td><input type="number" name="rate_limit_per_min" value="<?php echo esc_attr($s->get('rate_limit_per_min', 8)); ?>" min="1" max="60" class="small-text"></td>
            </tr>
        </table>

    <?php elseif ($tab === 'clinic') : ?>
        <p class="description"><?php esc_html_e('این افزونه کاملاً مستقل است؛ تمام اطلاعات زیر به‌صورت دستی وارد می‌شود و در هر گفتگو به هوش مصنوعی داده می‌شود.', 'signteb-web-chat'); ?></p>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('نام کلینیک / پزشک', 'signteb-web-chat'); ?></th><td><input type="text" name="clinic_name" value="<?php echo esc_attr($s->get('clinic_name')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('تخصص', 'signteb-web-chat'); ?></th><td><input type="text" name="specialty" value="<?php echo esc_attr($s->get('specialty')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('تلفن', 'signteb-web-chat'); ?></th><td><input type="text" name="phone" value="<?php echo esc_attr($s->get('phone')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('واتس‌اپ', 'signteb-web-chat'); ?></th><td><input type="text" name="whatsapp" value="<?php echo esc_attr($s->get('whatsapp')); ?>" class="regular-text" placeholder="989121234567"></td></tr>
            <tr><th><?php esc_html_e('آدرس', 'signteb-web-chat'); ?></th><td><input type="text" name="address" value="<?php echo esc_attr($s->get('address')); ?>" class="large-text"></td></tr>
            <tr><th><?php esc_html_e('ساعات کاری', 'signteb-web-chat'); ?></th><td><input type="text" name="business_hours" value="<?php echo esc_attr($s->get('business_hours')); ?>" placeholder="09:00-20:00" class="regular-text"><p class="description"><?php esc_html_e('خالی = همیشه باز.', 'signteb-web-chat'); ?></p></td></tr>
            <tr><th><?php esc_html_e('شماره اورژانس', 'signteb-web-chat'); ?></th><td><input type="text" name="emergency_number" value="<?php echo esc_attr($s->get('emergency_number', '115')); ?>" class="small-text"></td></tr>
            <tr><th><?php esc_html_e('لینک رزرو نوبت', 'signteb-web-chat'); ?></th><td><input type="url" name="booking_url" value="<?php echo esc_attr($s->get('booking_url')); ?>" class="large-text" placeholder="https://"><p class="description"><?php esc_html_e('لینک یا شماره خروجی برای رزرو (سیستم نوبت‌دهی داخلی وجود ندارد).', 'signteb-web-chat'); ?></p></td></tr>
            <tr>
                <th><?php esc_html_e('خدمات و قیمت‌ها', 'signteb-web-chat'); ?></th>
                <td>
                    <textarea name="manual_services" rows="6" class="large-text" placeholder="ویزیت عمومی | ۲۵۰ هزار تومان&#10;لیزر | ۵۰۰ هزار تومان"><?php echo esc_textarea($s->get('manual_services')); ?></textarea>
                    <p class="description"><?php esc_html_e('هر خط یک خدمت با فرمت: «نام | قیمت».', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
        </table>

    <?php elseif ($tab === 'appearance') : ?>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('نام چت‌بات', 'signteb-web-chat'); ?></th><td><input type="text" name="bot_name" value="<?php echo esc_attr($s->get('bot_name')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('آدرس آواتار (اختیاری)', 'signteb-web-chat'); ?></th><td><input type="url" name="avatar_url" value="<?php echo esc_attr($s->get('avatar_url')); ?>" class="large-text" placeholder="https://"></td></tr>
            <tr>
                <th><?php esc_html_e('رنگ اصلی / ثانویه', 'signteb-web-chat'); ?></th>
                <td>
                    <input type="color" name="widget_color" value="<?php echo esc_attr($s->get('widget_color', '#0f1f3d')); ?>">
                    <input type="color" name="accent_color" value="<?php echo esc_attr($s->get('accent_color', '#c8a04e')); ?>">
                    <p class="description"><?php esc_html_e('کاملاً قابل تغییر برای برندینگ خریدار (white-label).', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('جهت', 'signteb-web-chat'); ?></th>
                <td>
                    <select name="direction">
                        <option value="rtl" <?php selected($s->get('direction'), 'rtl'); ?>><?php esc_html_e('راست‌به‌چپ (فارسی/عربی)', 'signteb-web-chat'); ?></option>
                        <option value="ltr" <?php selected($s->get('direction'), 'ltr'); ?>><?php esc_html_e('چپ‌به‌راست (انگلیسی)', 'signteb-web-chat'); ?></option>
                    </select>
                </td>
            </tr>
            <tr><th><?php esc_html_e('فونت باندل‌شده Vazirmatn', 'signteb-web-chat'); ?></th><td><label><input type="checkbox" name="use_bundled_font" value="1" <?php checked($s->get('use_bundled_font', 1), 1); ?>> <?php esc_html_e('استفاده از فونت باندل‌شده (در صورت وجود فایل فونت)', 'signteb-web-chat'); ?></label></td></tr>
            <tr><th><?php esc_html_e('متن فوتر (برندینگ)', 'signteb-web-chat'); ?></th><td><input type="text" name="brand_footer" value="<?php echo esc_attr($s->get('brand_footer')); ?>" class="regular-text"><p class="description"><?php esc_html_e('خالی = بدون فوتر.', 'signteb-web-chat'); ?></p></td></tr>
            <tr><th><?php esc_html_e('پیام خوش‌آمد', 'signteb-web-chat'); ?></th><td><textarea name="welcome_message" rows="2" class="large-text"><?php echo esc_textarea($s->get('welcome_message')); ?></textarea></td></tr>
            <tr>
                <th><?php esc_html_e('دکمه‌های پاسخ سریع', 'signteb-web-chat'); ?></th>
                <td><textarea name="quick_replies" rows="3" class="large-text" placeholder="هزینه ویزیت&#10;آدرس کلینیک&#10;رزرو نوبت"><?php echo esc_textarea($s->get('quick_replies')); ?></textarea><p class="description"><?php esc_html_e('هر گزینه در یک خط.', 'signteb-web-chat'); ?></p></td>
            </tr>
            <tr><th><?php esc_html_e('پیام خارج از ساعت کاری', 'signteb-web-chat'); ?></th><td><textarea name="offhours_message" rows="2" class="large-text"><?php echo esc_textarea($s->get('offhours_message')); ?></textarea></td></tr>
        </table>

    <?php elseif ($tab === 'license') : ?>
        <?php $info = $license->info(); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('وضعیت', 'signteb-web-chat'); ?></th>
                <td>
                    <?php if ($license->is_active()) : ?>
                        <strong style="color:#2271b1"><?php esc_html_e('فعال', 'signteb-web-chat'); ?></strong>
                    <?php else : ?>
                        <strong><?php esc_html_e('نسخه آزمایشی', 'signteb-web-chat'); ?></strong>
                        — <?php printf(esc_html__('%d پیام باقی‌مانده از %d', 'signteb-web-chat'), (int) $license->trial_remaining(), (int) $license->trial_limit()); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('کلید لایسنس', 'signteb-web-chat'); ?></th>
                <td>
                    <input type="text" name="license_key" value="<?php echo esc_attr($info['key']); ?>" class="regular-text" placeholder="XXXX-XXXX-XXXX">
                    <p class="description"><?php esc_html_e('پس از پایان نسخه آزمایشی، کلید لایسنس سالانه را وارد کنید.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <p class="submit">
        <button type="submit" name="swc_settings_submit" class="button button-primary"><?php esc_html_e('ذخیره', 'signteb-web-chat'); ?></button>
    </p>
</form>

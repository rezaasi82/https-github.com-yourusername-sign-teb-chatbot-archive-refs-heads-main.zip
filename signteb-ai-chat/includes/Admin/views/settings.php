<?php
/**
 * Settings view.
 *
 * @var \STMC_Chat\Core\Settings $s
 * @var bool                     $core  Whether Medical Core is active.
 */

if (! defined('ABSPATH')) {
    exit;
}

$has_key = $s->has_api_key();
?>
<div class="wrap stmc-admin" dir="rtl">
    <h1><?php esc_html_e('SignTeb AI Chat — تنظیمات', 'signteb-ai-chat'); ?></h1>

    <?php settings_errors('stmc_chat'); ?>

    <?php if ($core) : ?>
        <div class="notice notice-info inline"><p>
            <?php esc_html_e('✅ افزونه SignTeb Medical Core شناسایی شد — پزشکان، خدمات و اطلاعات تماس به‌صورت زنده از آن خوانده می‌شوند. فیلدهای پروفایل پایین فقط در حالت Fallback استفاده می‌شوند.', 'signteb-ai-chat'); ?>
        </p></div>
    <?php else : ?>
        <div class="notice notice-warning inline"><p>
            <?php esc_html_e('ℹ️ Medical Core فعال نیست. چت‌بات از «پروفایل کسب‌وکار» زیر استفاده می‌کند. آن را کامل پر کنید.', 'signteb-ai-chat'); ?>
        </p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('stmc_chat_settings'); ?>

        <h2 class="title"><?php esc_html_e('وضعیت و هوش مصنوعی', 'signteb-ai-chat'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فعال‌سازی چت‌بات', 'signteb-ai-chat'); ?></th>
                <td><label><input type="checkbox" name="enabled" value="1" <?php checked($s->get('enabled', 1), 1); ?>> <?php esc_html_e('نمایش ویجت در سایت', 'signteb-ai-chat'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('سرویس‌دهنده AI', 'signteb-ai-chat'); ?></th>
                <td>
                    <select name="provider">
                        <option value="anthropic" <?php selected($s->get('provider'), 'anthropic'); ?>>Anthropic Claude</option>
                        <option value="openai" <?php selected($s->get('provider'), 'openai'); ?>>OpenAI (fallback)</option>
                    </select>
                    <input type="text" name="model" value="<?php echo esc_attr($s->get('model', 'claude-opus-4-8')); ?>" class="regular-text" placeholder="claude-opus-4-8">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('کلید API', 'signteb-ai-chat'); ?></th>
                <td>
                    <input type="password" name="api_key" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $has_key ? esc_attr__('•••••••• (ذخیره‌شده — برای تغییر مقدار جدید وارد کنید)', 'signteb-ai-chat') : 'sk-ant-…'; ?>">
                    <p class="description"><?php esc_html_e('کلید به‌صورت رمزنگاری‌شده در دیتابیس ذخیره می‌شود.', 'signteb-ai-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('کلید fallback (اختیاری)', 'signteb-ai-chat'); ?></th>
                <td><input type="password" name="fallback_key" value="" class="regular-text" autocomplete="new-password" placeholder="<?php esc_attr_e('کلید سرویس دوم برای زمان قطعی', 'signteb-ai-chat'); ?>"></td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('شخصیت و ظاهر', 'signteb-ai-chat'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('لحن', 'signteb-ai-chat'); ?></th>
                <td>
                    <select name="tone">
                        <option value="friendly" <?php selected($s->get('tone'), 'friendly'); ?>><?php esc_html_e('دوستانه', 'signteb-ai-chat'); ?></option>
                        <option value="formal" <?php selected($s->get('tone'), 'formal'); ?>><?php esc_html_e('رسمی', 'signteb-ai-chat'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('زبان پاسخ', 'signteb-ai-chat'); ?></th>
                <td>
                    <select name="language">
                        <option value="auto" <?php selected($s->get('language'), 'auto'); ?>><?php esc_html_e('خودکار', 'signteb-ai-chat'); ?></option>
                        <option value="fa" <?php selected($s->get('language'), 'fa'); ?>>فارسی</option>
                        <option value="ar" <?php selected($s->get('language'), 'ar'); ?>>العربية</option>
                        <option value="en" <?php selected($s->get('language'), 'en'); ?>>English</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('رنگ ویجت / تأکید', 'signteb-ai-chat'); ?></th>
                <td>
                    <input type="color" name="widget_color" value="<?php echo esc_attr($s->get('widget_color', '#0f1f3d')); ?>">
                    <input type="color" name="accent_color" value="<?php echo esc_attr($s->get('accent_color', '#c8a04e')); ?>">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('پیام خوش‌آمد', 'signteb-ai-chat'); ?></th>
                <td><textarea name="welcome_message" rows="2" class="large-text"><?php echo esc_textarea($s->get('welcome_message')); ?></textarea></td>
            </tr>
            <tr>
                <th><?php esc_html_e('دکمه‌های پاسخ سریع', 'signteb-ai-chat'); ?></th>
                <td>
                    <textarea name="quick_replies" rows="3" class="large-text" placeholder="رزرو نوبت&#10;هزینه ویزیت"><?php echo esc_textarea($s->get('quick_replies')); ?></textarea>
                    <p class="description"><?php esc_html_e('هر گزینه در یک خط.', 'signteb-ai-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('ساعات کاری', 'signteb-ai-chat'); ?></th>
                <td>
                    <input type="text" name="business_hours" value="<?php echo esc_attr($s->get('business_hours')); ?>" placeholder="09:00-20:00" class="regular-text">
                    <p class="description"><?php esc_html_e('خارج از این بازه، پیام متفاوت نمایش داده می‌شود. خالی = همیشه باز.', 'signteb-ai-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('پیام خارج از ساعت کاری', 'signteb-ai-chat'); ?></th>
                <td><textarea name="offhours_message" rows="2" class="large-text"><?php echo esc_textarea($s->get('offhours_message')); ?></textarea></td>
            </tr>
            <tr>
                <th><?php esc_html_e('محدودیت پیام در دقیقه', 'signteb-ai-chat'); ?></th>
                <td><input type="number" name="rate_limit_per_min" value="<?php echo esc_attr($s->get('rate_limit_per_min', 8)); ?>" min="1" max="60" class="small-text"></td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('پروفایل کسب‌وکار (Fallback)', 'signteb-ai-chat'); ?></h2>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('نام کلینیک', 'signteb-ai-chat'); ?></th><td><input type="text" name="clinic_name" value="<?php echo esc_attr($s->get('clinic_name')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('تخصص', 'signteb-ai-chat'); ?></th><td><input type="text" name="specialty" value="<?php echo esc_attr($s->get('specialty')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('تلفن', 'signteb-ai-chat'); ?></th><td><input type="text" name="phone" value="<?php echo esc_attr($s->get('phone')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('واتس‌اپ', 'signteb-ai-chat'); ?></th><td><input type="text" name="whatsapp" value="<?php echo esc_attr($s->get('whatsapp')); ?>" class="regular-text" placeholder="989121234567"></td></tr>
            <tr><th><?php esc_html_e('آدرس', 'signteb-ai-chat'); ?></th><td><input type="text" name="address" value="<?php echo esc_attr($s->get('address')); ?>" class="large-text"></td></tr>
            <tr><th><?php esc_html_e('شماره اورژانس', 'signteb-ai-chat'); ?></th><td><input type="text" name="emergency_number" value="<?php echo esc_attr($s->get('emergency_number', '115')); ?>" class="small-text"></td></tr>
            <tr><th><?php esc_html_e('لینک رزرو نوبت', 'signteb-ai-chat'); ?></th><td><input type="url" name="booking_url" value="<?php echo esc_attr($s->get('booking_url')); ?>" class="large-text" placeholder="https://"></td></tr>
            <tr>
                <th><?php esc_html_e('خدمات (دستی)', 'signteb-ai-chat'); ?></th>
                <td>
                    <textarea name="manual_services" rows="4" class="large-text" placeholder="ویزیت عمومی | ۲۵۰ هزار تومان&#10;لیزر | ۵۰۰ هزار تومان"><?php echo esc_textarea($s->get('manual_services')); ?></textarea>
                    <p class="description"><?php esc_html_e('هر خط: «نام خدمت | قیمت». فقط وقتی Medical Core غیرفعال است استفاده می‌شود.', 'signteb-ai-chat'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="stmc_chat_settings_submit" class="button button-primary"><?php esc_html_e('ذخیره تنظیمات', 'signteb-ai-chat'); ?></button>
        </p>
    </form>
</div>

<?php
/**
 * Settings view.
 *
 * @var array<string,mixed> $data Provided by SettingsPage::render().
 *
 * @package SignTeb\VideoHub
 */

if (! defined('ABSPATH')) {
    exit;
}

$values = $data['values'];
$secret = $data['has_secret'];
$mask   = $data['mask'];

$checkbox = static function (string $key, string $label, array $values, string $hint = ''): void {
    printf(
        '<label class="stvh-check"><input type="checkbox" name="%1$s" value="1" %2$s> <span>%3$s</span>%4$s</label>',
        esc_attr($key),
        checked(! empty($values[$key]), true, false),
        esc_html($label),
        $hint !== '' ? '<em class="stvh-hint">' . esc_html($hint) . '</em>' : ''
    );
};
?>
<div class="wrap stvh-admin">
    <h1><?php esc_html_e('تنظیمات ویدئو هاب', 'signteb-video-hub'); ?></h1>

    <?php if (! empty($data['saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('تنظیمات ذخیره شد.', 'signteb-video-hub'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php
        // Nonce field markup produced by wp_nonce_field().
        echo $data['nonce_field']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <input type="hidden" name="stvh_action" value="<?php echo esc_attr((string) $data['action']); ?>">

        <h2 class="stvh-section-title"><?php esc_html_e('عمومی', 'signteb-video-hub'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('وضعیت', 'signteb-video-hub'); ?></th>
                <td><?php $checkbox('enabled', __('افزونه فعال باشد', 'signteb-video-hub'), $values); ?></td>
            </tr>
            <tr>
                <th scope="row"><label for="cards_per_page"><?php esc_html_e('تعداد کارت در هر صفحه', 'signteb-video-hub'); ?></label></th>
                <td><input type="number" min="1" max="48" id="cards_per_page" name="cards_per_page" value="<?php echo esc_attr((string) $values['cards_per_page']); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="dark_mode"><?php esc_html_e('پوسته نمایش', 'signteb-video-hub'); ?></label></th>
                <td>
                    <select id="dark_mode" name="dark_mode">
                        <option value="auto" <?php selected($values['dark_mode'], 'auto'); ?>><?php esc_html_e('خودکار (بر اساس سیستم کاربر)', 'signteb-video-hub'); ?></option>
                        <option value="light" <?php selected($values['dark_mode'], 'light'); ?>><?php esc_html_e('روشن', 'signteb-video-hub'); ?></option>
                        <option value="dark" <?php selected($values['dark_mode'], 'dark'); ?>><?php esc_html_e('تیره', 'signteb-video-hub'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="accent_color"><?php esc_html_e('رنگ اصلی', 'signteb-video-hub'); ?></label></th>
                <td><input type="color" id="accent_color" name="accent_color" value="<?php echo esc_attr((string) $values['accent_color']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="booking_url"><?php esc_html_e('لینک رزرو نوبت', 'signteb-video-hub'); ?></label></th>
                <td><input type="url" id="booking_url" name="booking_url" value="<?php echo esc_attr((string) $values['booking_url']); ?>" class="regular-text" dir="ltr"></td>
            </tr>
        </table>

        <h2 class="stvh-section-title"><?php esc_html_e('منابع ویدئو', 'signteb-video-hub'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="aparat_username"><?php esc_html_e('شناسه کانال آپارات', 'signteb-video-hub'); ?></label></th>
                <td>
                    <input type="text" id="aparat_username" name="aparat_username" value="<?php echo esc_attr((string) $values['aparat_username']); ?>" class="regular-text" dir="ltr" placeholder="drhamedzamani">
                    <button type="button" class="button" data-stvh-test="aparat"><?php esc_html_e('تست اتصال', 'signteb-video-hub'); ?></button>
                    <p class="description"><?php esc_html_e('فقط نام کاربری کانال کافی است؛ آدرس کامل هم پذیرفته می‌شود.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aparat_playlist"><?php esc_html_e('فقط یک فهرست آپارات', 'signteb-video-hub'); ?></label></th>
                <td>
                    <select id="aparat_playlist" name="aparat_playlist" data-stvh-playlist-select="aparat">
                        <option value=""><?php esc_html_e('کل کانال', 'signteb-video-hub'); ?></option>
                        <?php if ((string) $values['aparat_playlist'] !== '') : ?>
                            <option value="<?php echo esc_attr((string) $values['aparat_playlist']); ?>" selected>
                                <?php
                                printf(
                                    /* translators: %s: stored playlist id */
                                    esc_html__('فهرست ذخیره‌شده (%s)', 'signteb-video-hub'),
                                    esc_html((string) $values['aparat_playlist'])
                                );
                                ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <button type="button" class="button" data-stvh-playlists="aparat"><?php esc_html_e('بارگذاری فهرست‌ها', 'signteb-video-hub'); ?></button>
                    <p class="description"><?php esc_html_e('اگر «کل کانال» بماند، همه ویدئوهای کانال وارد می‌شوند. برای دیدن فهرست‌ها اول شناسه کانال را ذخیره کنید.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="youtube_channel"><?php esc_html_e('کانال یوتیوب', 'signteb-video-hub'); ?></label></th>
                <td>
                    <input type="text" id="youtube_channel" name="youtube_channel" value="<?php echo esc_attr((string) $values['youtube_channel']); ?>" class="regular-text" dir="ltr" placeholder="UC… یا ‎@handle">
                    <button type="button" class="button" data-stvh-test="youtube"><?php esc_html_e('تست اتصال', 'signteb-video-hub'); ?></button>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="youtube_playlist"><?php esc_html_e('فقط یک پلی‌لیست یوتیوب', 'signteb-video-hub'); ?></label></th>
                <td>
                    <select id="youtube_playlist" name="youtube_playlist" data-stvh-playlist-select="youtube">
                        <option value=""><?php esc_html_e('کل کانال', 'signteb-video-hub'); ?></option>
                        <?php if ((string) $values['youtube_playlist'] !== '') : ?>
                            <option value="<?php echo esc_attr((string) $values['youtube_playlist']); ?>" selected>
                                <?php
                                printf(
                                    /* translators: %s: stored playlist id */
                                    esc_html__('پلی‌لیست ذخیره‌شده (%s)', 'signteb-video-hub'),
                                    esc_html((string) $values['youtube_playlist'])
                                );
                                ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <button type="button" class="button" data-stvh-playlists="youtube"><?php esc_html_e('بارگذاری پلی‌لیست‌ها', 'signteb-video-hub'); ?></button>
                    <p class="description"><?php esc_html_e('برای بارگذاری، کانال و کلید API باید ذخیره شده باشند.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="youtube_api_key"><?php esc_html_e('کلید API یوتیوب', 'signteb-video-hub'); ?></label></th>
                <td>
                    <input type="password" id="youtube_api_key" name="youtube_api_key" value="<?php echo $secret['youtube_api_key'] ? esc_attr($mask) : ''; ?>" class="regular-text" dir="ltr" autocomplete="off">
                    <p class="description"><?php esc_html_e('برای حذف کلید، فیلد را خالی کنید.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sync_interval"><?php esc_html_e('دوره همگام‌سازی', 'signteb-video-hub'); ?></label></th>
                <td>
                    <select id="sync_interval" name="sync_interval">
                        <?php foreach ($data['intervals'] as $slug => $interval) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($values['sync_interval'], $slug); ?>>
                                <?php echo esc_html($interval[1]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sync_limit"><?php esc_html_e('تعداد ویدئو در هر همگام‌سازی', 'signteb-video-hub'); ?></label></th>
                <td><input type="number" min="1" max="100" id="sync_limit" name="sync_limit" value="<?php echo esc_attr((string) $values['sync_limit']); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('انتشار', 'signteb-video-hub'); ?></th>
                <td>
                    <?php $checkbox('auto_publish', __('ویدئوهای جدید مستقیماً منتشر شوند', 'signteb-video-hub'), $values, __('در غیر این صورت به‌صورت پیش‌نویس ذخیره می‌شوند.', 'signteb-video-hub')); ?>
                    <?php $checkbox('import_thumbnails', __('تصویر ویدئو در کتابخانه رسانه ذخیره و به‌عنوان تصویر شاخص ست شود', 'signteb-video-hub'), $values, __('آپارات تصاویر را با بررسی Referer محافظت می‌کند؛ بدون این گزینه تصویر روی سایت شما بارگذاری نمی‌شود.', 'signteb-video-hub')); ?>
                </td>
            </tr>
        </table>

        <h2 class="stvh-section-title"><?php esc_html_e('هوش مصنوعی', 'signteb-video-hub'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('وضعیت', 'signteb-video-hub'); ?></th>
                <td>
                    <?php $checkbox('ai_enabled', __('تولید محتوای هوشمند فعال باشد', 'signteb-video-hub'), $values); ?>
                    <button type="button" class="button" data-stvh-test="ai"><?php esc_html_e('تست اتصال', 'signteb-video-hub'); ?></button>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ai_provider"><?php esc_html_e('سرویس‌دهنده', 'signteb-video-hub'); ?></label></th>
                <td>
                    <select id="ai_provider" name="ai_provider">
                        <option value="gapgpt" <?php selected($values['ai_provider'], 'gapgpt'); ?>><?php esc_html_e('GapGPT — پیشنهادی برای هاست ایران', 'signteb-video-hub'); ?></option>
                        <option value="anthropic" <?php selected($values['ai_provider'], 'anthropic'); ?>>Anthropic</option>
                        <option value="openai" <?php selected($values['ai_provider'], 'openai'); ?>><?php esc_html_e('سازگار با OpenAI', 'signteb-video-hub'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('روی بیشتر هاست‌های ایران، api.openai.com و api.anthropic.com در دسترس نیستند و اتصال مستقیم شکست می‌خورد. GapGPT درگاه سازگاری است که از ایران کار می‌کند و هم مدل‌های GPT و هم Claude را سرو می‌کند.', 'signteb-video-hub'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ai_api_key"><?php esc_html_e('کلید API', 'signteb-video-hub'); ?></label></th>
                <td><input type="password" id="ai_api_key" name="ai_api_key" value="<?php echo $secret['ai_api_key'] ? esc_attr($mask) : ''; ?>" class="regular-text" dir="ltr" autocomplete="off"></td>
            </tr>
            <tr>
                <th scope="row"><label for="ai_model"><?php esc_html_e('نام مدل', 'signteb-video-hub'); ?></label></th>
                <td>
                    <input type="text" id="ai_model" name="ai_model" value="<?php echo esc_attr((string) $values['ai_model']); ?>" class="regular-text" dir="ltr" placeholder="<?php esc_attr_e('خالی = مدل پیش‌فرض', 'signteb-video-hub'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ai_base_url"><?php esc_html_e('آدرس پایه سرویس', 'signteb-video-hub'); ?></label></th>
                <td>
                    <input type="url" id="ai_base_url" name="ai_base_url" value="<?php echo esc_attr((string) $values['ai_base_url']); ?>" class="regular-text" dir="ltr" placeholder="<?php echo esc_attr(\SignTeb\VideoHub\Ai\Providers\GapGptProvider::BASE_URL); ?>">
                    <p class="description"><?php esc_html_e('برای GapGPT خالی بگذارید — آدرس پیش‌فرض خودکار استفاده می‌شود. فقط اگر از درگاه سازگار دیگری استفاده می‌کنید آدرس پایه را وارد کنید.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('تولید خودکار', 'signteb-video-hub'); ?></th>
                <td>
                    <?php $checkbox('ai_auto_summary', __('خلاصه، نکات مهم و سوالات متداول', 'signteb-video-hub'), $values); ?>
                    <?php $checkbox('ai_auto_links', __('لینک‌سازی داخلی', 'signteb-video-hub'), $values); ?>
                    <?php $checkbox('ai_auto_article', __('پیشنهاد مقاله ۸۰۰ کلمه‌ای', 'signteb-video-hub'), $values, __('مقاله همیشه به صورت پیش‌نویس ذخیره می‌شود.', 'signteb-video-hub')); ?>
                    <?php $checkbox('ai_write_content', __('خلاصه و سوالات در متن نوشته ذخیره شود', 'signteb-video-hub'), $values, __('لازم است تا افزونه‌های سئو محتوایی برای تحلیل داشته باشند. متنی که خودتان نوشته باشید هرگز بازنویسی نمی‌شود.', 'signteb-video-hub')); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ai_batch_size"><?php esc_html_e('تعداد کار در هر اجرا', 'signteb-video-hub'); ?></label></th>
                <td><input type="number" min="1" max="10" id="ai_batch_size" name="ai_batch_size" value="<?php echo esc_attr((string) $values['ai_batch_size']); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="internal_link_map"><?php esc_html_e('نقشه لینک داخلی', 'signteb-video-hub'); ?></label></th>
                <td>
                    <textarea id="internal_link_map" name="internal_link_map" rows="6" class="large-text" dir="ltr" placeholder="/fibroscan/ | فیبرواسکن کبد"><?php echo esc_textarea((string) $values['internal_link_map']); ?></textarea>
                    <p class="description"><?php esc_html_e('هر خط یک آدرس و عنوان، جدا شده با |. هوش مصنوعی فقط از همین فهرست و صفحات موجود سایت لینک انتخاب می‌کند.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="stvh-section-title"><?php esc_html_e('سئو و اسکیما', 'signteb-video-hub'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('خروجی‌ها', 'signteb-video-hub'); ?></th>
                <td>
                    <?php $checkbox('schema_enabled', __('تولید اسکیمای ساختاریافته', 'signteb-video-hub'), $values); ?>
                    <?php $checkbox('social_meta', __('تگ‌های OpenGraph و توییتر', 'signteb-video-hub'), $values); ?>
                    <?php $checkbox('sitemap_enabled', __('نقشه سایت ویدئویی', 'signteb-video-hub'), $values); ?>
                    <?php $checkbox('hub_enabled', __('بلوک مرکز هوشمند پزشکی', 'signteb-video-hub'), $values); ?>
                    <p class="description">
                        <?php esc_html_e('نقشه سایت:', 'signteb-video-hub'); ?>
                        <a href="<?php echo esc_url((string) $data['sitemap_url']); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html((string) $data['sitemap_url']); ?></a>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="physician_name"><?php esc_html_e('نام پزشک', 'signteb-video-hub'); ?></label></th>
                <td><input type="text" id="physician_name" name="physician_name" value="<?php echo esc_attr((string) $values['physician_name']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="physician_specialty"><?php esc_html_e('تخصص', 'signteb-video-hub'); ?></label></th>
                <td><input type="text" id="physician_specialty" name="physician_specialty" value="<?php echo esc_attr((string) $values['physician_specialty']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="physician_url"><?php esc_html_e('صفحه پزشک', 'signteb-video-hub'); ?></label></th>
                <td><input type="url" id="physician_url" name="physician_url" value="<?php echo esc_attr((string) $values['physician_url']); ?>" class="regular-text" dir="ltr"></td>
            </tr>
            <tr>
                <th scope="row"><label for="clinic_name"><?php esc_html_e('نام مرکز درمانی', 'signteb-video-hub'); ?></label></th>
                <td><input type="text" id="clinic_name" name="clinic_name" value="<?php echo esc_attr((string) $values['clinic_name']); ?>" class="regular-text"></td>
            </tr>
        </table>

        <h2 class="stvh-section-title"><?php esc_html_e('ایندکس گوگل و کش', 'signteb-video-hub'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Indexing API', 'signteb-video-hub'); ?></th>
                <td>
                    <?php $checkbox('google_indexing', __('ارسال خودکار درخواست ایندکس', 'signteb-video-hub'), $values); ?>
                    <button type="button" class="button" data-stvh-test="google"><?php esc_html_e('تست اتصال', 'signteb-video-hub'); ?></button>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="google_service_json"><?php esc_html_e('فایل JSON سرویس‌اکانت', 'signteb-video-hub'); ?></label></th>
                <td>
                    <textarea id="google_service_json" name="google_service_json" rows="4" class="large-text" dir="ltr" autocomplete="off" placeholder='{"client_email":"…","private_key":"…"}'><?php echo $secret['google_service_json'] ? esc_textarea($mask) : ''; ?></textarea>
                    <p class="description"><?php esc_html_e('سرویس‌اکانت باید در سرچ کنسول به‌عنوان مالک سایت اضافه شده باشد.', 'signteb-video-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('پاک‌سازی کش', 'signteb-video-hub'); ?></th>
                <td><?php $checkbox('cache_purge', __('پس از تغییر ویدئو، کش افزونه‌های کشینگ پاک شود', 'signteb-video-hub'), $values); ?></td>
            </tr>
            <tr>
                <th scope="row"><label for="cloudflare_zone"><?php esc_html_e('Cloudflare Zone ID', 'signteb-video-hub'); ?></label></th>
                <td><input type="text" id="cloudflare_zone" name="cloudflare_zone" value="<?php echo esc_attr((string) $values['cloudflare_zone']); ?>" class="regular-text" dir="ltr"></td>
            </tr>
            <tr>
                <th scope="row"><label for="cloudflare_token"><?php esc_html_e('Cloudflare API Token', 'signteb-video-hub'); ?></label></th>
                <td><input type="password" id="cloudflare_token" name="cloudflare_token" value="<?php echo $secret['cloudflare_token'] ? esc_attr($mask) : ''; ?>" class="regular-text" dir="ltr" autocomplete="off"></td>
            </tr>
        </table>

        <h2 class="stvh-section-title"><?php esc_html_e('آمار', 'signteb-video-hub'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('جمع‌آوری آمار', 'signteb-video-hub'); ?></th>
                <td><?php $checkbox('analytics_enabled', __('ثبت نمایش، کلیک، پخش و مدت تماشا', 'signteb-video-hub'), $values, __('هیچ IP یا شناسه کاربری ذخیره نمی‌شود.', 'signteb-video-hub')); ?></td>
            </tr>
            <tr>
                <th scope="row"><label for="analytics_retention"><?php esc_html_e('نگهداری داده (روز)', 'signteb-video-hub'); ?></label></th>
                <td><input type="number" min="7" max="730" id="analytics_retention" name="analytics_retention" value="<?php echo esc_attr((string) $values['analytics_retention']); ?>" class="small-text"></td>
            </tr>
        </table>

        <?php // Test-connection results render inline next to their own button. ?>
        <?php submit_button(__('ذخیره تنظیمات', 'signteb-video-hub')); ?>
    </form>
</div>

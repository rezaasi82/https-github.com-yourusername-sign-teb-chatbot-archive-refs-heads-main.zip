<?php
/**
 * Tabbed settings content (provider | clinic | appearance | integrations).
 *
 * @var Settings        $s
 * @var string              $tab
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders the model picker for one provider: a dropdown of that provider's
 * models plus a manual field for any id the dropdown does not list yet.
 */
$mdr_model_field = static function (\Medora\Core\Settings $s, string $provider): void {
    $models  = \Medora\Ai\ProviderFactory::models_for($provider);
    $default = \Medora\Ai\ProviderFactory::default_model_for($provider);
    $current = trim((string) $s->get('model_' . $provider, $default));

    if ($current === '') {
        $current = $default;
    }
    $is_custom = ! isset($models[$current]);
    ?>
    <select name="model_<?php echo esc_attr($provider); ?>" class="mdr-model-select" data-provider="<?php echo esc_attr($provider); ?>">
        <?php foreach ($models as $id => $label) : ?>
            <option value="<?php echo esc_attr($id); ?>" <?php selected($current, $id); ?>>
                <?php
                echo esc_html($label);
                if ($id === $default) {
                    echo ' — ' . esc_html__('پیش‌فرض', 'medora');
                }
                ?>
            </option>
        <?php endforeach; ?>
        <option value="__custom__" <?php selected($is_custom, true); ?>>
            <?php esc_html_e('مدل دیگر (وارد کردن دستی)', 'medora'); ?>
        </option>
    </select>

    <p class="mdr-model-custom" data-provider="<?php echo esc_attr($provider); ?>" <?php echo $is_custom ? '' : 'style="display:none"'; ?>>
        <input type="text" dir="ltr" class="regular-text"
               name="model_<?php echo esc_attr($provider); ?>_custom"
               value="<?php echo $is_custom ? esc_attr($current) : ''; ?>"
               placeholder="<?php echo esc_attr($default); ?>">
        <span class="description">
            <?php esc_html_e('شناسهٔ مدل را دقیقاً همان‌طور که سرویس‌دهنده اعلام کرده وارد کنید.', 'medora'); ?>
        </span>
    </p>
    <?php
};
?>
<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=mdr-chat&tab=' . $tab)); ?>">
    <?php wp_nonce_field('mdr_settings'); ?>
    <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">

    <?php if ($tab === 'provider') : ?>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('نمایش ویجت در سایت', 'medora'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="float_enabled" value="1" <?php checked($s->is_float_enabled()); ?>>
                        <?php esc_html_e('ویجت شناور روی همهٔ صفحه‌های سایت نمایش داده شود', 'medora'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('آیکون گفتگو در گوشهٔ همهٔ صفحه‌ها ظاهر می‌شود.', 'medora'); ?></p>

                    <label style="margin-top:12px;display:inline-block">
                        <input type="checkbox" name="shortcode_enabled" value="1" <?php checked($s->is_shortcode_enabled()); ?>>
                        <?php esc_html_e('شورت‌کد فعال باشد', 'medora'); ?>
                    </label>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: the shortcode tag. */
                            esc_html__('با %s گفتگو را داخل یک برگه، نوشته یا سایدبار قرار می‌دهید. مستقل از ویجت شناور کار می‌کند.', 'medora'),
                            '<code dir="ltr">[medora_chat]</code>'
                        );
                        ?>
                    </p>

                    <p class="description">
                        <?php esc_html_e('اگر هر دو گزینه خاموش باشند، چت در سایت نمایش داده نمی‌شود و پاسخ‌گویی هوش مصنوعی هم متوقف می‌ماند.', 'medora'); ?>
                    </p>
                </td>
            </tr>
            <?php
            $mdr_last_error = get_option(\Medora\Ai\AiManager::LAST_ERROR_OPTION, []);
            if (is_array($mdr_last_error) && ! empty($mdr_last_error['reason'])) :
                ?>
                <tr>
                    <th></th>
                    <td>
                        <div class="notice notice-error inline" style="margin:0;padding:10px 12px">
                            <p style="margin:0 0 6px">
                                <strong><?php esc_html_e('آخرین درخواست به سرویس هوش مصنوعی ناموفق بود.', 'medora'); ?></strong>
                                <?php esc_html_e('تا وقتی این خطا برطرف نشود، ویجت به جای پاسخ، پیام «فعلاً امکان پاسخ‌گویی نیست» را نشان می‌دهد.', 'medora'); ?>
                            </p>
                            <p style="margin:0" dir="ltr">
                                <code><?php echo esc_html((string) $mdr_last_error['reason']); ?></code>
                            </p>
                            <p style="margin:6px 0 0">
                                <?php
                                printf(
                                    /* translators: 1: provider id, 2: model id, 3: human-readable time difference. */
                                    esc_html__('سرویس‌دهنده %1$s · مدل %2$s · %3$s پیش', 'medora'),
                                    '<code>' . esc_html((string) ($mdr_last_error['provider'] ?? '')) . '</code>',
                                    '<code>' . esc_html((string) ($mdr_last_error['model'] ?? '')) . '</code>',
                                    esc_html(human_time_diff((int) ($mdr_last_error['at'] ?? time())))
                                );
                                ?>
                            </p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <tr>
                <th><?php esc_html_e('سرویس‌دهنده AI', 'medora'); ?></th>
                <td>
                    <select name="provider" id="mdr-provider">
                        <option value="anthropic" <?php selected($s->active_provider(), 'anthropic'); ?>>Anthropic Claude</option>
                        <option value="openai" <?php selected($s->active_provider(), 'openai'); ?>>OpenAI</option>
                        <option value="gapgpt" <?php selected($s->active_provider(), 'gapgpt'); ?>>GapGPT (گیت‌وی ایران‌پسند)</option>
                        <option value="gemini" <?php selected($s->active_provider(), 'gemini'); ?>>Google Gemini</option>
                    </select>
                    <p class="description"><?php esc_html_e('فیلدهای کلید و مدلِ همان سرویس در پایین نمایش داده می‌شوند.', 'medora'); ?></p>
                </td>
            </tr>

            <tbody class="mdr-provider-block" data-provider="anthropic">
            <tr>
                <th><?php esc_html_e('کلید Anthropic', 'medora'); ?></th>
                <td>
                    <input type="password" name="api_key_anthropic" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('anthropic') ? esc_attr__('•••••••• (ذخیره‌شده)', 'medora') : 'sk-ant-…'; ?>">
                    <p class="description"><?php esc_html_e('رمزنگاری‌شده ذخیره می‌شود. برای تغییر، مقدار جدید وارد کنید.', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل Anthropic', 'medora'); ?></th>
                <td>
                    <?php $mdr_model_field($s, 'anthropic'); ?>
                    <p class="description"><?php esc_html_e('هرچه مدل سنگین‌تر باشد پاسخ دقیق‌تر و هزینهٔ هر گفتگو بیشتر است.', 'medora'); ?></p>
                </td>
            </tr>
            </tbody>

            <tbody class="mdr-provider-block" data-provider="openai">
            <tr>
                <th><?php esc_html_e('کلید OpenAI', 'medora'); ?></th>
                <td>
                    <input type="password" name="api_key_openai" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('openai') ? esc_attr__('•••••••• (ذخیره‌شده)', 'medora') : 'sk-…'; ?>">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل OpenAI', 'medora'); ?></th>
                <td>
                    <?php $mdr_model_field($s, 'openai'); ?>
                    <p class="description"><?php esc_html_e('هرچه مدل سنگین‌تر باشد پاسخ دقیق‌تر و هزینهٔ هر گفتگو بیشتر است.', 'medora'); ?></p>
                </td>
            </tr>
            </tbody>

            <tbody class="mdr-provider-block" data-provider="gapgpt">
            <tr>
                <th><?php esc_html_e('کلید GapGPT', 'medora'); ?></th>
                <td>
                    <input type="password" name="api_key_gapgpt" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('gapgpt') ? esc_attr__('•••••••• (ذخیره‌شده)', 'medora') : 'sk-…'; ?>">
                    <p class="description"><?php esc_html_e('گیت‌وی سازگار با OpenAI و در دسترس از داخل ایران (api.gapgpt.app). از داشبورد GapGPT کلید بگیرید.', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل GapGPT', 'medora'); ?></th>
                <td>
                    <?php $mdr_model_field($s, 'gapgpt'); ?>
                    <p class="description"><?php esc_html_e('GapGPT هم مدل‌های GPT و هم Claude و Gemini را ارائه می‌دهد.', 'medora'); ?></p>
                </td>
            </tr>
            </tbody>

            <tbody class="mdr-provider-block" data-provider="gemini">
            <tr>
                <th><?php esc_html_e('کلید Google Gemini', 'medora'); ?></th>
                <td>
                    <input type="password" name="api_key_gemini" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('gemini') ? esc_attr__('•••••••• (ذخیره‌شده)', 'medora') : 'AIza…'; ?>">
                    <p class="description"><?php esc_html_e('کلید را از Google AI Studio بگیرید. رمزنگاری‌شده ذخیره می‌شود؛ برای تغییر، مقدار جدید وارد کنید.', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل Gemini', 'medora'); ?></th>
                <td>
                    <?php $mdr_model_field($s, 'gemini'); ?>
                    <p class="description"><?php esc_html_e('هرچه مدل سنگین‌تر باشد پاسخ دقیق‌تر و هزینهٔ هر گفتگو بیشتر است.', 'medora'); ?></p>
                </td>
            </tr>
            </tbody>

            <tr>
                <th><?php esc_html_e('بررسی اتصال', 'medora'); ?></th>
                <td>
                    <button type="button" class="button mdr-ai-diag-btn"><?php esc_html_e('تست ارتباط با سرویس هوش مصنوعی', 'medora'); ?></button>
                    <pre class="mdr-ai-diag-out" style="display:none"></pre>
                    <p class="description"><?php esc_html_e('ابتدا تنظیمات را ذخیره کنید. این دکمه یک درخواست کوچک واقعی می‌فرستد و عین پاسخ سرویس را نشان می‌دهد — اگر کلید، مدل یا دسترسی هاست مشکل داشته باشد، همین‌جا معلوم می‌شود.', 'medora'); ?></p>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e('لحن', 'medora'); ?></th>
                <td>
                    <select name="tone">
                        <option value="friendly" <?php selected($s->get('tone'), 'friendly'); ?>><?php esc_html_e('دوستانه', 'medora'); ?></option>
                        <option value="formal" <?php selected($s->get('tone'), 'formal'); ?>><?php esc_html_e('رسمی', 'medora'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('زبان پاسخ', 'medora'); ?></th>
                <td>
                    <select name="language">
                        <option value="auto" <?php selected($s->get('language'), 'auto'); ?>><?php esc_html_e('خودکار', 'medora'); ?></option>
                        <option value="fa" <?php selected($s->get('language'), 'fa'); ?>>فارسی</option>
                        <option value="ar" <?php selected($s->get('language'), 'ar'); ?>>العربية</option>
                        <option value="en" <?php selected($s->get('language'), 'en'); ?>>English</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('محدودیت پیام در دقیقه', 'medora'); ?></th>
                <td><input type="number" name="rate_limit_per_min" value="<?php echo esc_attr($s->get('rate_limit_per_min', 8)); ?>" min="1" max="60" class="small-text"></td>
            </tr>
        </table>

    <?php elseif ($tab === 'clinic') : ?>
        <p class="description"><?php esc_html_e('این افزونه کاملاً مستقل است؛ تمام اطلاعات زیر به‌صورت دستی وارد می‌شود و در هر گفتگو به هوش مصنوعی داده می‌شود.', 'medora'); ?></p>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('نام کلینیک / پزشک', 'medora'); ?></th><td><input type="text" name="clinic_name" value="<?php echo esc_attr($s->get('clinic_name')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('تخصص', 'medora'); ?></th><td><input type="text" name="specialty" value="<?php echo esc_attr($s->get('specialty')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('تلفن', 'medora'); ?></th><td><input type="text" name="phone" value="<?php echo esc_attr($s->get('phone')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('واتس‌اپ', 'medora'); ?></th><td><input type="text" name="whatsapp" value="<?php echo esc_attr($s->get('whatsapp')); ?>" class="regular-text" placeholder="989121234567"></td></tr>
            <tr><th><?php esc_html_e('آدرس', 'medora'); ?></th><td><input type="text" name="address" value="<?php echo esc_attr($s->get('address')); ?>" class="large-text"></td></tr>
            <tr><th><?php esc_html_e('ساعات کاری', 'medora'); ?></th><td><input type="text" name="business_hours" value="<?php echo esc_attr($s->get('business_hours')); ?>" placeholder="09:00-20:00" class="regular-text"><p class="description"><?php esc_html_e('خالی = همیشه باز.', 'medora'); ?></p></td></tr>
            <tr><th><?php esc_html_e('شماره اورژانس', 'medora'); ?></th><td><input type="text" name="emergency_number" value="<?php echo esc_attr($s->get('emergency_number', '115')); ?>" class="small-text"></td></tr>
            <tr><th><?php esc_html_e('میانگین قیمت هر خدمت (تومان)', 'medora'); ?></th><td><input type="number" name="avg_service_price" value="<?php echo esc_attr($s->get('avg_service_price', 0)); ?>" min="0" step="10000" class="regular-text"><p class="description"><?php esc_html_e('برای محاسبه‌ی برآورد درآمد در داشبورد استفاده می‌شود.', 'medora'); ?></p></td></tr>
            <tr><th><?php esc_html_e('لینک رزرو نوبت', 'medora'); ?></th><td><input type="url" name="booking_url" value="<?php echo esc_attr($s->get('booking_url')); ?>" class="large-text" placeholder="https://"><p class="description"><?php esc_html_e('لینک یا شماره خروجی برای رزرو (سیستم نوبت‌دهی داخلی وجود ندارد).', 'medora'); ?></p></td></tr>
            <tr><th><?php esc_html_e('لینک پیام‌رسان بله', 'medora'); ?></th><td><input type="url" name="bale_url" value="<?php echo esc_attr($s->get('bale_url')); ?>" class="large-text" placeholder="https://ble.ir/…"></td></tr>
            <tr>
                <th><?php esc_html_e('خدمات و قیمت‌ها', 'medora'); ?></th>
                <td>
                    <textarea name="manual_services" rows="6" class="large-text" placeholder="ویزیت عمومی | ۲۵۰ هزار تومان&#10;لیزر | ۵۰۰ هزار تومان"><?php echo esc_textarea($s->get('manual_services')); ?></textarea>
                    <p class="description"><?php esc_html_e('هر خط یک خدمت با فرمت: «نام | قیمت».', 'medora'); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('جذب لید و کانال‌های ارتباطی', 'medora'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فرم جذب لید', 'medora'); ?></th>
                <td><label><input type="checkbox" name="lead_capture" value="1" <?php checked($s->get('lead_capture', 1), 1); ?>> <?php esc_html_e('پیش از شروع گفتگو، نام و شماره موبایل بیمار دریافت شود.', 'medora'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('دکمه‌های ارتباطی', 'medora'); ?></th>
                <td>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_booking" value="1" <?php checked($s->get('ch_booking', 1), 1); ?>> 📅 <?php esc_html_e('رزرو نوبت', 'medora'); ?></label>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_whatsapp" value="1" <?php checked($s->get('ch_whatsapp', 1), 1); ?>> 💬 <?php esc_html_e('واتساپ', 'medora'); ?></label>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_call" value="1" <?php checked($s->get('ch_call', 1), 1); ?>> 📞 <?php esc_html_e('تماس با مطب', 'medora'); ?></label>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_bale" value="1" <?php checked($s->get('ch_bale', 0), 1); ?>> 🟦 <?php esc_html_e('پیام‌رسان بله', 'medora'); ?></label>
                    <p class="description"><?php esc_html_e('هر دکمه فقط وقتی نمایش داده می‌شود که هم فعال باشد و هم مقدار مربوطه (لینک/شماره) وارد شده باشد.', 'medora'); ?></p>
                </td>
            </tr>
        </table>

    <?php elseif ($tab === 'appearance') : ?>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('نام چت‌بات', 'medora'); ?></th><td><input type="text" name="bot_name" value="<?php echo esc_attr($s->get('bot_name')); ?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('آدرس آواتار (اختیاری)', 'medora'); ?></th><td><input type="url" name="avatar_url" value="<?php echo esc_attr($s->get('avatar_url')); ?>" class="large-text" placeholder="https://"></td></tr>
            <tr>
                <th><?php esc_html_e('رنگ اصلی / ثانویه', 'medora'); ?></th>
                <td>
                    <input type="color" name="widget_color" value="<?php echo esc_attr($s->get('widget_color', '#0f1f3d')); ?>">
                    <input type="color" name="accent_color" value="<?php echo esc_attr($s->get('accent_color', '#c8a04e')); ?>">
                    <p class="description"><?php esc_html_e('کاملاً قابل تغییر برای برندینگ خریدار (white-label).', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('جهت', 'medora'); ?></th>
                <td>
                    <select name="direction">
                        <option value="rtl" <?php selected($s->get('direction'), 'rtl'); ?>><?php esc_html_e('راست‌به‌چپ (فارسی/عربی)', 'medora'); ?></option>
                        <option value="ltr" <?php selected($s->get('direction'), 'ltr'); ?>><?php esc_html_e('چپ‌به‌راست (انگلیسی)', 'medora'); ?></option>
                    </select>
                </td>
            </tr>
            <tr><th><?php esc_html_e('فونت باندل‌شده Vazirmatn', 'medora'); ?></th><td><label><input type="checkbox" name="use_bundled_font" value="1" <?php checked($s->get('use_bundled_font', 1), 1); ?>> <?php esc_html_e('استفاده از فونت باندل‌شده (در صورت وجود فایل فونت)', 'medora'); ?></label></td></tr>
            <tr><th><?php esc_html_e('متن فوتر (برندینگ)', 'medora'); ?></th><td><input type="text" name="brand_footer" value="<?php echo esc_attr($s->get('brand_footer')); ?>" class="regular-text"><p class="description"><?php esc_html_e('خالی = بدون فوتر.', 'medora'); ?></p></td></tr>
            <tr><th><?php esc_html_e('پیام خوش‌آمد', 'medora'); ?></th><td><textarea name="welcome_message" rows="2" class="large-text"><?php echo esc_textarea($s->get('welcome_message')); ?></textarea></td></tr>
            <tr>
                <th><?php esc_html_e('دکمه‌های پاسخ سریع', 'medora'); ?></th>
                <td><textarea name="quick_replies" rows="3" class="large-text" placeholder="هزینه ویزیت&#10;آدرس کلینیک&#10;رزرو نوبت"><?php echo esc_textarea($s->get('quick_replies')); ?></textarea><p class="description"><?php esc_html_e('هر گزینه در یک خط.', 'medora'); ?></p></td>
            </tr>
            <tr><th><?php esc_html_e('پیام خارج از ساعت کاری', 'medora'); ?></th><td><textarea name="offhours_message" rows="2" class="large-text"><?php echo esc_textarea($s->get('offhours_message')); ?></textarea></td></tr>
            <tr>
                <th><?php esc_html_e('پیام دعوت‌کننده (Teaser)', 'medora'); ?></th>
                <td>
                    <textarea name="teaser_message" rows="2" class="large-text" placeholder="<?php esc_attr_e('سلام! من اینجام تا اگه سوالی داری کمکت کنم 👋', 'medora'); ?>"><?php echo esc_textarea($s->get('teaser_message')); ?></textarea>
                    <p class="description"><?php esc_html_e('حبابی که کنار آیکون چت ظاهر می‌شود تا بازدیدکننده متوجه دستیار شود. خالی = نمایش داده نشود.', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('تأخیر نمایش Teaser (ثانیه)', 'medora'); ?></th>
                <td><input type="number" name="teaser_delay" min="0" max="120" value="<?php echo esc_attr((string) (int) $s->get('teaser_delay', 3)); ?>" class="small-text"><p class="description"><?php esc_html_e('چند ثانیه بعد از باز شدن صفحه، پیام دعوت‌کننده نمایش داده شود.', 'medora'); ?></p></td>
            </tr>
            <tr>
                <th><?php esc_html_e('صدای اعلان', 'medora'); ?></th>
                <td>
                    <label><input type="checkbox" name="teaser_sound" value="1" <?php checked($s->get('teaser_sound', 1), 1); ?>> <?php esc_html_e('پخش یک صدای کوتاه و جذاب هنگام نمایش پیام دعوت‌کننده', 'medora'); ?></label>
                    <p class="description"><?php esc_html_e('به دلیل سیاست مرورگرها، صدا فقط پس از اولین تعامل بازدیدکننده با صفحه (حرکت ماوس/اسکرول/کلیک) پخش می‌شود.', 'medora'); ?></p>
                </td>
            </tr>
        </table>

    <?php elseif ($tab === 'integrations') : ?>
        <?php
        $webhook = new \Medora\Export\WebhookManager();
        $gsheet  = new \Medora\Export\GoogleSheets();
        ?>
        <h2 class="title"><?php esc_html_e('Webhook (n8n / Make / Zapier / CRM)', 'medora'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فعال‌سازی', 'medora'); ?></th>
                <td><label><input type="checkbox" name="webhook_enabled" value="1" <?php checked($s->get('webhook_enabled', 0), 1); ?>> <?php esc_html_e('ارسال خودکار لید به Webhook', 'medora'); ?></label></td>
            </tr>
            <tr><th><?php esc_html_e('آدرس Webhook', 'medora'); ?></th><td><input type="url" name="webhook_url" value="<?php echo esc_attr($s->get('webhook_url')); ?>" class="large-text" placeholder="https://"></td></tr>
            <tr>
                <th><?php esc_html_e('کلید امنیتی (Secret)', 'medora'); ?></th>
                <td>
                    <input type="password" name="webhook_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $webhook->secret() !== '' ? '••••••••' : esc_attr__('برای امضای HMAC', 'medora'); ?>">
                    <p class="description"><?php esc_html_e('اگر تنظیم شود، هدر X-Medora-Signature با امضای HMAC-SHA256 ارسال می‌شود.', 'medora'); ?></p>
                </td>
            </tr>
            <tr><th><?php esc_html_e('رویدادها', 'medora'); ?></th><td><input type="text" name="webhook_events" value="<?php echo esc_attr($s->get('webhook_events')); ?>" class="regular-text" placeholder="lead_created,pdf_generated"><p class="description"><?php esc_html_e('خالی = همه رویدادها. مقادیر: lead_created, chat_finished, pdf_generated, manual', 'medora'); ?></p></td></tr>
            <tr><th><?php esc_html_e('تلاش مجدد', 'medora'); ?></th><td><label><input type="checkbox" name="webhook_retry" value="1" <?php checked($s->get('webhook_retry', 1), 1); ?>> <?php esc_html_e('در صورت خطا تا ۳ بار دوباره تلاش کن', 'medora'); ?></label></td></tr>
            <tr><th></th><td><button type="button" class="button mdr-test-btn" data-target="webhook"><?php esc_html_e('تست اتصال', 'medora'); ?></button> <span class="mdr-test-result" data-for="webhook"></span></td></tr>
        </table>

        <h2 class="title"><?php esc_html_e('Google Sheets', 'medora'); ?></h2>
        <p class="description"><?php esc_html_e('برای افزودن ردیف به Google Sheets، یک Google Apps Script Web App مستقر کنید (بدون OAuth). راهنما در README.', 'medora'); ?></p>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('فعال‌سازی', 'medora'); ?></th><td><label><input type="checkbox" name="gsheet_enabled" value="1" <?php checked($s->get('gsheet_enabled', 0), 1); ?>> <?php esc_html_e('همگام‌سازی با Google Sheets', 'medora'); ?></label></td></tr>
            <tr><th><?php esc_html_e('همگام‌سازی خودکار', 'medora'); ?></th><td><label><input type="checkbox" name="gsheet_auto" value="1" <?php checked($s->get('gsheet_auto', 0), 1); ?>> <?php esc_html_e('لید جدید به‌صورت خودکار افزوده شود', 'medora'); ?></label></td></tr>
            <tr><th><?php esc_html_e('آدرس Web App', 'medora'); ?></th><td><input type="url" name="gsheet_webapp_url" value="<?php echo esc_attr($s->get('gsheet_webapp_url')); ?>" class="large-text" placeholder="https://script.google.com/macros/s/…/exec"></td></tr>
            <tr><th><?php esc_html_e('کلید امنیتی', 'medora'); ?></th><td><input type="password" name="gsheet_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $gsheet->secret() !== '' ? '••••••••' : ''; ?>"></td></tr>
            <tr><th><?php esc_html_e('نام شیت', 'medora'); ?></th><td><input type="text" name="gsheet_name" value="<?php echo esc_attr($s->get('gsheet_name', 'Leads')); ?>" class="regular-text"></td></tr>
            <tr><th></th><td><button type="button" class="button mdr-test-btn" data-target="gsheet"><?php esc_html_e('تست اتصال', 'medora'); ?></button> <span class="mdr-test-result" data-for="gsheet"></span></td></tr>
        </table>

        <h2 class="title"><?php esc_html_e('Medora Cloud (اختیاری)', 'medora'); ?></h2>
        <p class="description"><?php esc_html_e('اتصال به پلتفرم ابری برای مانیتورینگ و لایسنس. فقط شمارنده‌های فنی و هش دامنه ارسال می‌شود؛ هیچ داده‌ی بیمار ارسال نمی‌گردد. پیش‌فرض: خاموش.', 'medora'); ?></p>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('فعال‌سازی', 'medora'); ?></th><td><label><input type="checkbox" name="cloud_enabled" value="1" <?php checked($s->get('cloud_enabled', 0), 1); ?>> <?php esc_html_e('ارسال heartbeat روزانه به Medora Cloud', 'medora'); ?></label></td></tr>
            <tr><th><?php esc_html_e('آدرس Cloud', 'medora'); ?></th><td><input type="url" name="cloud_endpoint" value="<?php echo esc_attr($s->get('cloud_endpoint')); ?>" class="large-text" placeholder="https://example.com/v1/heartbeat"></td></tr>
            <tr><th><?php esc_html_e('کلید امنیتی', 'medora'); ?></th><td><input type="password" name="cloud_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo (new \Medora\Cloud\CloudClient())->secret() !== '' ? '••••••••' : ''; ?>"></td></tr>
        </table>

        <?php $sms = new \Medora\Notifications\SmsManager(); $sms_active = $sms->active_id(); ?>
        <h2 class="title"><?php esc_html_e('پنل پیامک و پیام‌رسان', 'medora'); ?></h2>
        <p class="description"><?php esc_html_e('اتصال به پنل‌های پیامکی ایرانی یا سرویس خارجی. فقط کافی است سرویس را انتخاب و «کد فعال‌سازی/کلید API» پنل خود را وارد کنید.', 'medora'); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فعال‌سازی', 'medora'); ?></th>
                <td><label><input type="checkbox" name="sms_enabled" value="1" <?php checked($s->get('sms_enabled', 0), 1); ?>> <?php esc_html_e('ارسال پیامک از طریق پنل انتخاب‌شده', 'medora'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('سرویس پیامک', 'medora'); ?></th>
                <td>
                    <select name="sms_provider" class="mdr-sms-provider">
                        <?php foreach ($sms->providers() as $pid => $plabel) : ?>
                            <option value="<?php echo esc_attr($pid); ?>" <?php selected($sms_active, $pid); ?>><?php echo esc_html($plabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description mdr-sms-hint" data-for="melipayamak"><?php esc_html_e('ملی‌پیامک: نام کاربری پنل (معمولاً شماره موبایل) را در فیلد APIKey و «APIKey وب‌سرویس» (کد مانند xxxxxxxx-xxxx-… از بخش تنظیمات وبسرویس پنل) را در فیلد «رمز عبور» وارد کنید — طبق راهنمای خود پنل، این کد جایگزین رمز عبور می‌شود. (اگر از توکن کنسول جدید استفاده می‌کنید، آن را در APIKey بگذارید و رمز را خالی کنید.)', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('APIKey (کد فعال‌سازی وب‌سرویس)', 'medora'); ?></th>
                <td>
                    <input type="password" name="sms_key" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo \Medora\Notifications\SmsManager::key() !== '' ? '•••••••• (ذخیره شده)' : esc_attr__('APIKey دریافتی از پنل پیامکی', 'medora'); ?>">
                    <p class="description"><?php esc_html_e('همان کلید وب‌سرویس که پنل پیامکی در بخش «وب‌سرویس/توسعه‌دهندگان» می‌دهد. رمزنگاری‌شده ذخیره می‌شود و فقط با وارد کردن مقدار جدید تغییر می‌کند.', 'medora'); ?></p>
                </td>
            </tr>
            <tr class="mdr-sms-secret-row">
                <th><?php esc_html_e('رمز عبور (ملی‌پیامک — حالت نام‌کاربری/رمز)', 'medora'); ?></th>
                <td><input type="password" name="sms_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo \Medora\Notifications\SmsManager::secret() !== '' ? '••••••••' : ''; ?>"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('شماره فرستنده (خط) — اختیاری', 'medora'); ?></th>
                <td>
                    <input type="text" name="sms_sender" value="<?php echo esc_attr($s->get('sms_sender')); ?>" class="regular-text" placeholder="10008663 / +1...">
                    <p class="description"><?php esc_html_e('اگر از خط خدماتی اشتراکی استفاده می‌کنید این فیلد را خالی بگذارید — در ارسال الگویی (کد الگو)، خود پنل خط را انتخاب می‌کند و تغییر شماره خط هیچ مشکلی ایجاد نمی‌کند. این شماره فقط برای ارسال متن آزاد با خط اختصاصی لازم است.', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('متن لغو (خط خدماتی)', 'medora'); ?></th>
                <td>
                    <input type="text" name="sms_optout" value="<?php echo esc_attr($s->get('sms_optout')); ?>" class="large-text" placeholder="لغو ۱۱ / لغو11 https://example.com">
                    <p class="description"><?php esc_html_e('طبق مقررات، پیامک خط خدماتی باید متن لغو داشته باشد. این متن با متغیر {optout} به انتهای قالب‌ها اضافه می‌شود.', 'medora'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('شماره همکاران (دریافت ارجاع)', 'medora'); ?></th>
                <td>
                    <textarea name="sms_staff_numbers" rows="3" class="large-text" placeholder="دکتر رضایی,09121112233,dr.rezaei@example.com&#10;پذیرش,09124445566"><?php echo esc_textarea($s->get('sms_staff_numbers')); ?></textarea>
                    <p class="description"><?php esc_html_e('هر خط یک همکار: «نام,شماره,ایمیل» (ایمیل اختیاری). در صفحه‌ی هر لید، با انتخاب همکار شماره و ایمیل مقصد خودکار پر می‌شود.', 'medora'); ?></p>
                </td>
            </tr>
        </table>

        <div class="mdr-sms-custom" <?php echo $sms_active === 'custom' ? '' : 'style="display:none"'; ?>>
            <h3><?php esc_html_e('تنظیمات سرویس سفارشی / خارجی', 'medora'); ?></h3>
            <p class="description"><?php esc_html_e('برای سرویس‌هایی مانند Twilio یا هر API دلخواه. متغیرها: {to} {text} {key} {secret} {sender}', 'medora'); ?></p>
            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('آدرس (URL)', 'medora'); ?></th><td><input type="text" name="sms_custom_url" value="<?php echo esc_attr($s->get('sms_custom_url')); ?>" class="large-text" placeholder="https://api.example.com/send"></td></tr>
                <tr><th><?php esc_html_e('متد', 'medora'); ?></th><td>
                    <select name="sms_custom_method">
                        <?php foreach (['POST', 'GET', 'PUT'] as $mth) : ?>
                            <option value="<?php echo esc_attr($mth); ?>" <?php selected(strtoupper((string) $s->get('sms_custom_method', 'POST')), $mth); ?>><?php echo esc_html($mth); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
                <tr><th><?php esc_html_e('هدرها (هر خط: Key: Value)', 'medora'); ?></th><td><textarea name="sms_custom_headers" rows="3" class="large-text" placeholder="Authorization: Bearer {key}&#10;Content-Type: application/json"><?php echo esc_textarea($s->get('sms_custom_headers')); ?></textarea></td></tr>
                <tr><th><?php esc_html_e('بدنه درخواست', 'medora'); ?></th><td><textarea name="sms_custom_body" rows="3" class="large-text" placeholder='{"to":"{to}","text":"{text}"}'><?php echo esc_textarea($s->get('sms_custom_body')); ?></textarea></td></tr>
            </table>
        </div>

        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('بررسی اتصال', 'medora'); ?></th><td>
                <button type="button" class="button mdr-sms-diag-btn"><?php esc_html_e('بررسی اتصال پنل (بدون ارسال پیامک)', 'medora'); ?></button>
                <pre class="mdr-sms-diag-out" style="display:none"></pre>
                <p class="description"><?php esc_html_e('وضعیت تنظیمات ذخیره‌شده و پاسخ مستقیم پنل را نشان می‌دهد — اگر مشکلی هست، دقیقاً معلوم می‌شود کجاست.', 'medora'); ?></p>
            </td></tr>
            <tr><th><?php esc_html_e('تست ارسال', 'medora'); ?></th><td>
                <input type="tel" class="regular-text mdr-sms-test-to" placeholder="<?php esc_attr_e('شماره موبایل برای تست', 'medora'); ?>">
                <button type="button" class="button mdr-sms-test-btn"><?php esc_html_e('ارسال پیامک تست', 'medora'); ?></button>
                <span class="mdr-test-result" data-for="sms"></span>
                <p class="description"><?php esc_html_e('ابتدا تنظیمات را ذخیره کنید، سپس یک پیامک آزمایشی بفرستید.', 'medora'); ?></p>
            </td></tr>
        </table>

        <h3><?php esc_html_e('قالب‌های پیام (قابل ویرایش)', 'medora'); ?></h3>
        <p class="description"><?php esc_html_e('متن را دقیقاً مطابق الگوی تأییدشده‌ی پنل بنویسید — با جای‌گذاری عددی {0} {1} {2} (فرمت مورد تأیید ملی‌پیامک). معنی هر شماره زیر هر قالب نوشته شده است. جای‌گذاری نامی ({name} {clinic} …) هم پشتیبانی می‌شود.', 'medora'); ?></p>
        <p class="description"><?php esc_html_e('«کد الگو» کدِ خصوصی پترن ثبت‌شده در پنل شماست — پیش‌فرض ندارد و همراه افزونه منتشر نمی‌شود؛ فقط رمزگذاری‌نشده در دیتابیس همین سایت می‌ماند. اگر خالی باشد، پیام به‌صورت متن عادی ارسال می‌شود.', 'medora'); ?></p>
        <?php
        $tpl_codes  = (array) $s->get('sms_template_codes', []);
        $var_labels = [
            'name'    => __('نام بیمار', 'medora'),
            'phone'   => __('موبایل بیمار', 'medora'),
            'score'   => __('امتیاز لید', 'medora'),
            'status'  => __('وضعیت', 'medora'),
            'clinic'  => __('نام کلینیک', 'medora'),
            'summary' => __('خلاصه گفتگو', 'medora'),
        ];
        ?>
        <table class="form-table" role="presentation">
            <?php foreach ($sms->templates() as $tkey => $tpl) : ?>
                <tr>
                    <th><?php echo esc_html($tpl['label']); ?></th>
                    <td>
                        <textarea name="sms_templates[<?php echo esc_attr($tkey); ?>]" rows="2" class="large-text"><?php echo esc_textarea($tpl['text']); ?></textarea>
                        <p class="description">
                            <?php
                            $legend = [];
                            foreach ($sms->template_vars($tkey) as $i => $vname) {
                                $legend[] = '{' . $i . '} = ' . ($var_labels[$vname] ?? $vname);
                            }
                            echo esc_html(implode(' · ', $legend));
                            ?>
                        </p>
                        <label class="mdr-tpl-code">
                            <span><?php esc_html_e('کد الگو (خط خدماتی):', 'medora'); ?></span>
                            <input type="text" name="sms_template_codes[<?php echo esc_attr($tkey); ?>]" value="<?php echo esc_attr((string) ($tpl_codes[$tkey] ?? '')); ?>" class="regular-text" placeholder="<?php esc_attr_e('کد پترن پنل شما', 'medora'); ?>" autocomplete="off">
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h2 class="title"><?php esc_html_e('اعلان لید در پیام‌رسان (بله / تلگرام)', 'medora'); ?></h2>
        <p class="description"><?php esc_html_e('با هر لید جدید، یک اعلان فوری به گروه یا کانال کلینیک شما در بله/تلگرام ارسال می‌شود. کافی است یک ربات بسازید و توکن + شناسه چت را وارد کنید.', 'medora'); ?></p>
        <?php $msgr = new \Medora\Notifications\MessengerNotifier(); ?>
        <table class="form-table" role="presentation">
            <?php foreach ($msgr->channels() as $ch => $cdef) : ?>
                <tr>
                    <th><?php echo esc_html($cdef['label']); ?></th>
                    <td>
                        <label><input type="checkbox" name="msgr_<?php echo esc_attr($ch); ?>_enabled" value="1" <?php checked($s->get('msgr_' . $ch . '_enabled', 0), 1); ?>> <?php esc_html_e('فعال', 'medora'); ?></label>
                        <br>
                        <input type="password" name="msgr_<?php echo esc_attr($ch); ?>_token" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo \Medora\Notifications\MessengerNotifier::token($ch) !== '' ? '•••••••• (توکن ربات)' : esc_attr__('توکن ربات', 'medora'); ?>" style="margin:4px 0">
                        <input type="text" name="msgr_<?php echo esc_attr($ch); ?>_chat" value="<?php echo esc_attr($s->get('msgr_' . $ch . '_chat')); ?>" class="regular-text" placeholder="<?php esc_attr_e('شناسه چت (chat_id)', 'medora'); ?>" style="margin:4px 0">
                        <button type="button" class="button mdr-msgr-test-btn" data-channel="<?php echo esc_attr($ch); ?>"><?php esc_html_e('تست', 'medora'); ?></button>
                        <span class="mdr-test-result" data-for="msgr-<?php echo esc_attr($ch); ?>"></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

    <?php endif; ?>

    <p class="submit">
        <button type="submit" name="mdr_settings_submit" class="button button-primary"><?php esc_html_e('ذخیره', 'medora'); ?></button>
    </p>
</form>

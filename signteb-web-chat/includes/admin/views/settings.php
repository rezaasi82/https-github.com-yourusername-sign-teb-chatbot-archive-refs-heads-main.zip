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
                        <option value="gapgpt" <?php selected($s->active_provider(), 'gapgpt'); ?>>GapGPT (گیت‌وی ایران‌پسند)</option>
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

            <tbody class="swc-provider-block" data-provider="gapgpt">
            <tr>
                <th><?php esc_html_e('کلید GapGPT', 'signteb-web-chat'); ?></th>
                <td>
                    <input type="password" name="api_key_gapgpt" value="" class="regular-text" autocomplete="new-password"
                           placeholder="<?php echo $s->has_api_key('gapgpt') ? esc_attr__('•••••••• (ذخیره‌شده)', 'signteb-web-chat') : 'sk-…'; ?>">
                    <p class="description"><?php esc_html_e('گیت‌وی سازگار با OpenAI و در دسترس از داخل ایران (api.gapgpt.app). از داشبورد GapGPT کلید بگیرید.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدل GapGPT', 'signteb-web-chat'); ?></th>
                <td>
                    <input list="swc-models-gapgpt" name="model_gapgpt" value="<?php echo esc_attr($s->get('model_gapgpt', 'gpt-4o-mini')); ?>" class="regular-text">
                    <datalist id="swc-models-gapgpt">
                        <option value="gpt-4o-mini"></option>
                        <option value="gpt-4o"></option>
                        <option value="claude-haiku-4-5-20251001"></option>
                        <option value="claude-sonnet-5"></option>
                        <option value="gemini-2.0-flash"></option>
                    </datalist>
                    <p class="description"><?php esc_html_e('GapGPT هم مدل‌های GPT و هم Claude را ارائه می‌دهد. پیش‌فرض: gpt-4o-mini.', 'signteb-web-chat'); ?></p>
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
            <tr><th><?php esc_html_e('میانگین قیمت هر خدمت (تومان)', 'signteb-web-chat'); ?></th><td><input type="number" name="avg_service_price" value="<?php echo esc_attr($s->get('avg_service_price', 0)); ?>" min="0" step="10000" class="regular-text"><p class="description"><?php esc_html_e('برای محاسبه‌ی برآورد درآمد در داشبورد استفاده می‌شود.', 'signteb-web-chat'); ?></p></td></tr>
            <tr><th><?php esc_html_e('لینک رزرو نوبت', 'signteb-web-chat'); ?></th><td><input type="url" name="booking_url" value="<?php echo esc_attr($s->get('booking_url')); ?>" class="large-text" placeholder="https://"><p class="description"><?php esc_html_e('لینک یا شماره خروجی برای رزرو (سیستم نوبت‌دهی داخلی وجود ندارد).', 'signteb-web-chat'); ?></p></td></tr>
            <tr><th><?php esc_html_e('لینک پیام‌رسان بله', 'signteb-web-chat'); ?></th><td><input type="url" name="bale_url" value="<?php echo esc_attr($s->get('bale_url')); ?>" class="large-text" placeholder="https://ble.ir/…"></td></tr>
            <tr>
                <th><?php esc_html_e('خدمات و قیمت‌ها', 'signteb-web-chat'); ?></th>
                <td>
                    <textarea name="manual_services" rows="6" class="large-text" placeholder="ویزیت عمومی | ۲۵۰ هزار تومان&#10;لیزر | ۵۰۰ هزار تومان"><?php echo esc_textarea($s->get('manual_services')); ?></textarea>
                    <p class="description"><?php esc_html_e('هر خط یک خدمت با فرمت: «نام | قیمت».', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('جذب لید و کانال‌های ارتباطی', 'signteb-web-chat'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فرم جذب لید', 'signteb-web-chat'); ?></th>
                <td><label><input type="checkbox" name="lead_capture" value="1" <?php checked($s->get('lead_capture', 1), 1); ?>> <?php esc_html_e('پیش از شروع گفتگو، نام و شماره موبایل بیمار دریافت شود.', 'signteb-web-chat'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('دکمه‌های ارتباطی', 'signteb-web-chat'); ?></th>
                <td>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_booking" value="1" <?php checked($s->get('ch_booking', 1), 1); ?>> 📅 <?php esc_html_e('رزرو نوبت', 'signteb-web-chat'); ?></label>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_whatsapp" value="1" <?php checked($s->get('ch_whatsapp', 1), 1); ?>> 💬 <?php esc_html_e('واتساپ', 'signteb-web-chat'); ?></label>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_call" value="1" <?php checked($s->get('ch_call', 1), 1); ?>> 📞 <?php esc_html_e('تماس با مطب', 'signteb-web-chat'); ?></label>
                    <label style="display:block;margin:4px 0"><input type="checkbox" name="ch_bale" value="1" <?php checked($s->get('ch_bale', 0), 1); ?>> 🟦 <?php esc_html_e('پیام‌رسان بله', 'signteb-web-chat'); ?></label>
                    <p class="description"><?php esc_html_e('هر دکمه فقط وقتی نمایش داده می‌شود که هم فعال باشد و هم مقدار مربوطه (لینک/شماره) وارد شده باشد.', 'signteb-web-chat'); ?></p>
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
            <tr>
                <th><?php esc_html_e('پیام دعوت‌کننده (Teaser)', 'signteb-web-chat'); ?></th>
                <td>
                    <textarea name="teaser_message" rows="2" class="large-text" placeholder="<?php esc_attr_e('سلام! من اینجام تا اگه سوالی داری کمکت کنم 👋', 'signteb-web-chat'); ?>"><?php echo esc_textarea($s->get('teaser_message')); ?></textarea>
                    <p class="description"><?php esc_html_e('حبابی که کنار آیکون چت ظاهر می‌شود تا بازدیدکننده متوجه دستیار شود. خالی = نمایش داده نشود.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('تأخیر نمایش Teaser (ثانیه)', 'signteb-web-chat'); ?></th>
                <td><input type="number" name="teaser_delay" min="0" max="120" value="<?php echo esc_attr((string) (int) $s->get('teaser_delay', 3)); ?>" class="small-text"><p class="description"><?php esc_html_e('چند ثانیه بعد از باز شدن صفحه، پیام دعوت‌کننده نمایش داده شود.', 'signteb-web-chat'); ?></p></td>
            </tr>
        </table>

    <?php elseif ($tab === 'integrations') : ?>
        <?php
        $webhook = new SWC_Webhook_Manager();
        $gsheet  = new SWC_Google_Sheets();
        ?>
        <h2 class="title"><?php esc_html_e('Webhook (n8n / Make / Zapier / CRM)', 'signteb-web-chat'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('فعال‌سازی', 'signteb-web-chat'); ?></th>
                <td><label><input type="checkbox" name="webhook_enabled" value="1" <?php checked($s->get('webhook_enabled', 0), 1); ?>> <?php esc_html_e('ارسال خودکار لید به Webhook', 'signteb-web-chat'); ?></label></td>
            </tr>
            <tr><th><?php esc_html_e('آدرس Webhook', 'signteb-web-chat'); ?></th><td><input type="url" name="webhook_url" value="<?php echo esc_attr($s->get('webhook_url')); ?>" class="large-text" placeholder="https://"></td></tr>
            <tr>
                <th><?php esc_html_e('کلید امنیتی (Secret)', 'signteb-web-chat'); ?></th>
                <td>
                    <input type="password" name="webhook_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $webhook->secret() !== '' ? '••••••••' : esc_attr__('برای امضای HMAC', 'signteb-web-chat'); ?>">
                    <p class="description"><?php esc_html_e('اگر تنظیم شود، هدر X-Medora-Signature با امضای HMAC-SHA256 ارسال می‌شود.', 'signteb-web-chat'); ?></p>
                </td>
            </tr>
            <tr><th><?php esc_html_e('رویدادها', 'signteb-web-chat'); ?></th><td><input type="text" name="webhook_events" value="<?php echo esc_attr($s->get('webhook_events')); ?>" class="regular-text" placeholder="lead_created,pdf_generated"><p class="description"><?php esc_html_e('خالی = همه رویدادها. مقادیر: lead_created, chat_finished, pdf_generated, manual', 'signteb-web-chat'); ?></p></td></tr>
            <tr><th><?php esc_html_e('تلاش مجدد', 'signteb-web-chat'); ?></th><td><label><input type="checkbox" name="webhook_retry" value="1" <?php checked($s->get('webhook_retry', 1), 1); ?>> <?php esc_html_e('در صورت خطا تا ۳ بار دوباره تلاش کن', 'signteb-web-chat'); ?></label></td></tr>
            <tr><th></th><td><button type="button" class="button swc-test-btn" data-target="webhook"><?php esc_html_e('تست اتصال', 'signteb-web-chat'); ?></button> <span class="swc-test-result" data-for="webhook"></span></td></tr>
        </table>

        <h2 class="title"><?php esc_html_e('Google Sheets', 'signteb-web-chat'); ?></h2>
        <p class="description"><?php esc_html_e('برای افزودن ردیف به Google Sheets، یک Google Apps Script Web App مستقر کنید (بدون OAuth). راهنما در README.', 'signteb-web-chat'); ?></p>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('فعال‌سازی', 'signteb-web-chat'); ?></th><td><label><input type="checkbox" name="gsheet_enabled" value="1" <?php checked($s->get('gsheet_enabled', 0), 1); ?>> <?php esc_html_e('همگام‌سازی با Google Sheets', 'signteb-web-chat'); ?></label></td></tr>
            <tr><th><?php esc_html_e('همگام‌سازی خودکار', 'signteb-web-chat'); ?></th><td><label><input type="checkbox" name="gsheet_auto" value="1" <?php checked($s->get('gsheet_auto', 0), 1); ?>> <?php esc_html_e('لید جدید به‌صورت خودکار افزوده شود', 'signteb-web-chat'); ?></label></td></tr>
            <tr><th><?php esc_html_e('آدرس Web App', 'signteb-web-chat'); ?></th><td><input type="url" name="gsheet_webapp_url" value="<?php echo esc_attr($s->get('gsheet_webapp_url')); ?>" class="large-text" placeholder="https://script.google.com/macros/s/…/exec"></td></tr>
            <tr><th><?php esc_html_e('کلید امنیتی', 'signteb-web-chat'); ?></th><td><input type="password" name="gsheet_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $gsheet->secret() !== '' ? '••••••••' : ''; ?>"></td></tr>
            <tr><th><?php esc_html_e('نام شیت', 'signteb-web-chat'); ?></th><td><input type="text" name="gsheet_name" value="<?php echo esc_attr($s->get('gsheet_name', 'Leads')); ?>" class="regular-text"></td></tr>
            <tr><th></th><td><button type="button" class="button swc-test-btn" data-target="gsheet"><?php esc_html_e('تست اتصال', 'signteb-web-chat'); ?></button> <span class="swc-test-result" data-for="gsheet"></span></td></tr>
        </table>

        <h2 class="title"><?php esc_html_e('Medora Cloud (اختیاری)', 'signteb-web-chat'); ?></h2>
        <p class="description"><?php esc_html_e('اتصال به پلتفرم ابری برای مانیتورینگ و لایسنس. فقط شمارنده‌های فنی و هش دامنه ارسال می‌شود؛ هیچ داده‌ی بیمار ارسال نمی‌گردد. پیش‌فرض: خاموش.', 'signteb-web-chat'); ?></p>
        <table class="form-table" role="presentation">
            <tr><th><?php esc_html_e('فعال‌سازی', 'signteb-web-chat'); ?></th><td><label><input type="checkbox" name="cloud_enabled" value="1" <?php checked($s->get('cloud_enabled', 0), 1); ?>> <?php esc_html_e('ارسال heartbeat روزانه به Medora Cloud', 'signteb-web-chat'); ?></label></td></tr>
            <tr><th><?php esc_html_e('آدرس Cloud', 'signteb-web-chat'); ?></th><td><input type="url" name="cloud_endpoint" value="<?php echo esc_attr($s->get('cloud_endpoint')); ?>" class="large-text" placeholder="https://cloud.medora.ai/v1/heartbeat"></td></tr>
            <tr><th><?php esc_html_e('کلید امنیتی', 'signteb-web-chat'); ?></th><td><input type="password" name="cloud_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo (new SWC_Cloud_Client())->secret() !== '' ? '••••••••' : ''; ?>"></td></tr>
            <tr><th><?php esc_html_e('آدرس فید به‌روزرسانی', 'signteb-web-chat'); ?></th><td><input type="url" name="update_feed_url" value="<?php echo esc_attr($s->get('update_feed_url')); ?>" class="large-text" placeholder="https://cloud.medora.ai/v1/update/latest"><p class="description"><?php esc_html_e('خالی = به‌صورت خودکار از آدرس Cloud استخراج می‌شود.', 'signteb-web-chat'); ?></p></td></tr>
        </table>

    <?php elseif ($tab === 'license') : ?>
        <?php
        $info    = $license->info();
        $state   = $license->state();
        $labels  = ['active' => __('فعال', 'signteb-web-chat'), 'grace' => __('مهلت تمدید', 'signteb-web-chat'), 'locked' => __('منقضی/معلق', 'signteb-web-chat'), 'trial' => __('نسخه آزمایشی', 'signteb-web-chat')];
        $colors  = ['active' => '#1a7f37', 'grace' => '#8a6d1b', 'locked' => '#d63638', 'trial' => '#50607a'];
        $days    = $license->days_left();
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('وضعیت', 'signteb-web-chat'); ?></th>
                <td>
                    <strong style="color:<?php echo esc_attr($colors[$state] ?? '#50607a'); ?>"><?php echo esc_html($labels[$state] ?? $state); ?></strong>
                    <?php if ($state === 'trial') : ?>
                        — <?php printf(esc_html__('%d پیام باقی‌مانده از %d', 'signteb-web-chat'), (int) $license->trial_remaining(), (int) $license->trial_limit()); ?>
                    <?php else : ?>
                        · <?php esc_html_e('پلن:', 'signteb-web-chat'); ?> <?php echo esc_html($license->plan()); ?>
                        <?php if ($days !== null) : ?> · <?php printf(esc_html__('%d روز باقی‌مانده', 'signteb-web-chat'), (int) $days); ?><?php endif; ?>
                    <?php endif; ?>
                    <p class="description"><?php esc_html_e('اعتبارسنجی روزانه از Medora Cloud انجام می‌شود. ذخیره‌ی این فرم بلافاصله یک بررسی مجدد اجرا می‌کند.', 'signteb-web-chat'); ?></p>
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

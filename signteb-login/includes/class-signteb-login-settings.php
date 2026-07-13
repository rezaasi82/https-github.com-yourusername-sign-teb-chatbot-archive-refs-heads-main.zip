<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings > SignTeb Login: per-site configuration for the branded login
 * screen — enable/disable, custom login URL, color scheme, logo (via the
 * media library), and the agency credit toggle.
 */
class SignTeb_Login_Settings
{
    private const PAGE_SLUG = 'signteb-login';

    private ?string $notice = null;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_picker']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('صفحه ورود SignTeb', 'signteb-login'),
            __('صفحه ورود SignTeb', 'signteb-login'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function enqueue_media_picker(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_media();

        $js = <<<'JS'
document.addEventListener('click', function (e) {
    if (! e.target.classList || ! e.target.classList.contains('signteb-media-btn')) {
        return;
    }
    e.preventDefault();
    var frame = wp.media({ multiple: false, library: { type: 'image' } });
    frame.on('select', function () {
        document.getElementById('signteb_login_logo_url').value =
            frame.state().get('selection').first().toJSON().url;
    });
    frame.open();
});
JS;
        wp_add_inline_script('media-editor', $js);
    }

    public function handle_save(): void
    {
        if (! isset($_POST['signteb_login_settings'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('signteb_login_save', 'signteb_login_nonce')) {
            return;
        }

        update_option('signteb_login_enabled', empty($_POST['enabled']) ? '0' : '1');
        update_option('signteb_login_show_credit', empty($_POST['show_credit']) ? '0' : '1');

        update_option(
            SignTeb_Login_License::OPTION,
            strtoupper(sanitize_text_field(wp_unslash($_POST['license_key'] ?? '')))
        );

        $slug = sanitize_title(wp_unslash($_POST['login_slug'] ?? ''));
        update_option('signteb_login_slug', $slug);

        $accent = sanitize_hex_color(wp_unslash($_POST['accent'] ?? ''));
        update_option('signteb_login_accent', $accent ?: '');

        $bright = sanitize_hex_color(wp_unslash($_POST['accent_bright'] ?? ''));
        update_option('signteb_login_accent_bright', $bright ?: '');

        update_option('signteb_login_logo_url', esc_url_raw(wp_unslash($_POST['logo_url'] ?? '')));

        $logo_height = (int) ($_POST['logo_height'] ?? 96);
        update_option('signteb_login_logo_height', max(40, min(320, $logo_height ?: 96)));

        $this->notice = __('تنظیمات ذخیره شد.', 'signteb-login');

        if ($slug !== '') {
            $this->notice .= ' ' . sprintf(
                /* translators: %s: new login URL. */
                __('آدرس جدید صفحه ورود: %s — این آدرس را یادداشت کنید؛ wp-login.php دیگر در دسترس نیست.', 'signteb-login'),
                home_url('/' . $slug)
            );
        }
    }

    public function render(): void
    {
        $enabled     = get_option('signteb_login_enabled', '1') === '1';
        $show_credit = get_option('signteb_login_show_credit', '1') === '1';
        $slug        = (string) get_option('signteb_login_slug', '');
        $accent      = sanitize_hex_color(get_option('signteb_login_accent', '')) ?: SignTeb_Login::DEFAULT_ACCENT;
        $bright      = sanitize_hex_color(get_option('signteb_login_accent_bright', '')) ?: SignTeb_Login::DEFAULT_ACCENT_BRIGHT;
        $logo_url    = (string) get_option('signteb_login_logo_url', '');
        $logo_height = (int) get_option('signteb_login_logo_height', 96);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('صفحه ورود SignTeb', 'signteb-login'); ?></h1>

            <?php if ($this->notice) : ?>
                <div class="notice notice-success"><p><?php echo esc_html($this->notice); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('signteb_login_save', 'signteb_login_nonce'); ?>
                <input type="hidden" name="signteb_login_settings" value="1">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="signteb_login_license_key"><?php esc_html_e('کد فعال‌سازی', 'signteb-login'); ?></label></th>
                        <td>
                            <input type="text" id="signteb_login_license_key" name="license_key" value="<?php echo esc_attr(get_option(SignTeb_Login_License::OPTION, '')); ?>" class="regular-text" dir="ltr" placeholder="XXXX-XXXX-XXXX-XXXX">
                            <?php if (SignTeb_Login_License::is_valid()) : ?>
                                <span style="color:#00a32a;font-weight:600;"><?php esc_html_e('معتبر ✓', 'signteb-login'); ?></span>
                            <?php else : ?>
                                <span style="color:#d63638;font-weight:600;"><?php esc_html_e('نامعتبر ✗', 'signteb-login'); ?></span>
                            <?php endif; ?>
                            <p class="description">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: site host name. */
                                    __('کد صادرشده برای دامنه %s را وارد کنید. بدون کد معتبر، طراحی صفحه ورود و تغییر آدرس ورود غیرفعال می‌مانند.', 'signteb-login'),
                                    SignTeb_Login_License::host()
                                ));
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('فعال', 'signteb-login'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                                <?php esc_html_e('طراحی اختصاصی صفحه ورود فعال باشد', 'signteb-login'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="signteb_login_slug"><?php esc_html_e('آدرس صفحه ورود', 'signteb-login'); ?></label></th>
                        <td>
                            <code><?php echo esc_html(home_url('/')); ?></code>
                            <input type="text" id="signteb_login_slug" name="login_slug" value="<?php echo esc_attr($slug); ?>" class="regular-text" dir="ltr" placeholder="vip-login">
                            <p class="description"><?php esc_html_e('در صورت تنظیم، صفحه ورود از این آدرس در دسترس خواهد بود و wp-login.php پاسخ ۴۰۴ می‌دهد. برای غیرفعال‌سازی خالی بگذارید. آدرس جدید را حتماً یادداشت کنید.', 'signteb-login'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="signteb_login_accent"><?php esc_html_e('رنگ اصلی', 'signteb-login'); ?></label></th>
                        <td>
                            <input type="color" id="signteb_login_accent" name="accent" value="<?php echo esc_attr($accent); ?>">
                            <p class="description"><?php esc_html_e('رنگ تاکید طرح (حاشیه فوکوس، هاله و سایه‌ها). پیش‌فرض: طلایی.', 'signteb-login'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="signteb_login_accent_bright"><?php esc_html_e('رنگ روشن', 'signteb-login'); ?></label></th>
                        <td>
                            <input type="color" id="signteb_login_accent_bright" name="accent_bright" value="<?php echo esc_attr($bright); ?>">
                            <p class="description"><?php esc_html_e('رنگ دکمه ورود، لوگو و هاور لینک‌ها. پیش‌فرض: طلایی روشن.', 'signteb-login'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="signteb_login_logo_url"><?php esc_html_e('لوگو', 'signteb-login'); ?></label></th>
                        <td>
                            <input type="url" id="signteb_login_logo_url" name="logo_url" value="<?php echo esc_attr($logo_url); ?>" class="regular-text" dir="ltr">
                            <button type="button" class="button signteb-media-btn"><?php esc_html_e('انتخاب از کتابخانه پرونده‌ها', 'signteb-login'); ?></button>
                            <p class="description"><?php esc_html_e('تصویر لوگوی مشتری (PNG/SVG با پس‌زمینه شفاف پیشنهاد می‌شود). خالی بماند تا نشان پیش‌فرض SignTeb نمایش داده شود.', 'signteb-login'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="signteb_login_logo_height"><?php esc_html_e('ارتفاع لوگو', 'signteb-login'); ?></label></th>
                        <td>
                            <input type="number" id="signteb_login_logo_height" name="logo_height" value="<?php echo esc_attr($logo_height); ?>" min="40" max="320" step="4" class="small-text" dir="ltr"> px
                            <p class="description"><?php esc_html_e('ارتفاع نمایش لوگوی سفارشی در صفحه ورود (پیش‌فرض ۹۶). عرض به‌صورت خودکار و متناسب محاسبه می‌شود.', 'signteb-login'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('امضای SignTeb', 'signteb-login'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_credit" value="1" <?php checked($show_credit); ?>>
                                <?php esc_html_e('نمایش «طراحی و توسعه: SignTeb.com» زیر فرم ورود', 'signteb-login'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('ذخیره تنظیمات', 'signteb-login')); ?>
            </form>
        </div>
        <?php
    }
}

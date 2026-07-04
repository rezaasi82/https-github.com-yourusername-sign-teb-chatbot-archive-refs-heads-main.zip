<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// اضافه کردن منو به پیشخوان وردپرس
function qrgen_admin_menu() {
    add_options_page(
        'تنظیمات QRGen',
        'QRGen',
        'manage_options',
        'qrgen-settings',
        'qrgen_settings_page'
    );
}
add_action('admin_menu', 'qrgen_admin_menu');

// صفحه تنظیمات
function qrgen_settings_page() {
    ?>
    <div class="wrap">
        <h1>تنظیمات QRGen for WordPress</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('qrgen_settings');
            do_settings_sections('qrgen-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// ثبت تنظیمات
function qrgen_register_settings() {
    register_setting('qrgen_settings', 'qrgen_default_size', array(
        'sanitize_callback' => 'qrgen_sanitize_default_size',
        'default' => '300',
    ));
    register_setting('qrgen_settings', 'qrgen_default_color_dark', array(
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#000000',
    ));
    register_setting('qrgen_settings', 'qrgen_default_color_light', array(
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#ffffff',
    ));

    add_settings_section(
        'qrgen_defaults_section',
        'تنظیمات پیش‌فرض',
        'qrgen_defaults_section_callback',
        'qrgen-settings'
    );
    
    add_settings_field(
        'qrgen_default_size',
        'سایز پیش‌فرض',
        'qrgen_default_size_callback',
        'qrgen-settings',
        'qrgen_defaults_section'
    );
    
    add_settings_field(
        'qrgen_default_color_dark',
        'رنگ پیش‌فرض QR',
        'qrgen_default_color_dark_callback',
        'qrgen-settings',
        'qrgen_defaults_section'
    );
    
    add_settings_field(
        'qrgen_default_color_light',
        'رنگ پیش‌فرض پس‌زمینه',
        'qrgen_default_color_light_callback',
        'qrgen-settings',
        'qrgen_defaults_section'
    );
}
add_action('admin_init', 'qrgen_register_settings');

// توابع مربوط به فیلدهای تنظیمات
function qrgen_defaults_section_callback() {
    echo '<p>تنظیمات پیش‌فرض برای تولید QR Code</p>';
}

function qrgen_sanitize_default_size($size) {
    $allowed = array('200', '300', '400', '500');
    $size = (string) absint($size);
    return in_array($size, $allowed, true) ? $size : '300';
}

function qrgen_default_size_callback() {
    $size = get_option('qrgen_default_size', '300');
    $options = array(
        '200' => 'کوچک (200x200)',
        '300' => 'متوسط (300x300)',
        '400' => 'بزرگ (400x400)',
        '500' => 'خیلی بزرگ (500x500)',
    );
    echo '<select name="qrgen_default_size">';
    foreach ($options as $value => $label) {
        printf(
            '<option value="%1$s"%2$s>%3$s</option>',
            esc_attr($value),
            selected($size, $value, false),
            esc_html($label)
        );
    }
    echo '</select>';
}

function qrgen_default_color_dark_callback() {
    $color = get_option('qrgen_default_color_dark', '#000000');
    echo '<input type="color" name="qrgen_default_color_dark" value="' . esc_attr($color) . '" />';
}

function qrgen_default_color_light_callback() {
    $color = get_option('qrgen_default_color_light', '#ffffff');
    echo '<input type="color" name="qrgen_default_color_light" value="' . esc_attr($color) . '" />';
}
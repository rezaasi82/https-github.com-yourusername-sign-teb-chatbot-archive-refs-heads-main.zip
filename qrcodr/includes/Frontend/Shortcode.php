<?php

namespace QRCODR\Frontend;

use QRCODR\Codes\CodeRepository;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    const TAG = 'qrcodr';
    const ALLOWED_SIZES = array(200, 300, 400, 500);

    public static function init()
    {
        add_shortcode(self::TAG, array(self::class, 'render'));
        add_action('wp_enqueue_scripts', array(self::class, 'maybe_enqueue_assets'));
    }

    public static function maybe_enqueue_assets()
    {
        if (is_singular() && !self::current_post_has_shortcode()) {
            return;
        }

        wp_enqueue_style('qrcodr-frontend', QRCODR_PLUGIN_URL . 'assets/css/frontend.css', array(), QRCODR_VERSION);
        wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true);
        wp_enqueue_script('qrcodr-frontend', QRCODR_PLUGIN_URL . 'assets/js/frontend.js', array('qrcode-js'), QRCODR_VERSION, true);
    }

    private static function current_post_has_shortcode()
    {
        global $post;
        if (!($post instanceof \WP_Post)) {
            return false;
        }
        return has_shortcode($post->post_content, self::TAG);
    }

    public static function render($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
            'code' => '',
            'size' => '',
            'download' => 'yes',
        ), $atts, self::TAG);

        $repository = new CodeRepository();
        $item = null;

        if (!empty($atts['id'])) {
            $item = $repository->find_by_id(absint($atts['id']));
        } elseif (!empty($atts['code'])) {
            $item = $repository->find_by_short_code(sanitize_text_field($atts['code']));
        }

        if (!$item || $item->status !== 'active') {
            return self::render_missing_notice();
        }

        $style = $item->style_json ? json_decode($item->style_json, true) : array();
        $size = !empty($atts['size']) ? absint($atts['size']) : (isset($style['size']) ? (int) $style['size'] : 300);
        if (!in_array($size, self::ALLOWED_SIZES, true)) {
            $size = 300;
        }
        $color_dark = isset($style['color_dark']) ? $style['color_dark'] : '#000000';
        $color_light = isset($style['color_light']) ? $style['color_light'] : '#ffffff';
        $short_url = home_url('qr/' . $item->short_code);
        $show_download = $atts['download'] !== 'no';

        ob_start();
        ?>
        <div class="qrcodr-frontend"
             data-short-url="<?php echo esc_attr($short_url); ?>"
             data-size="<?php echo esc_attr($size); ?>"
             data-color-dark="<?php echo esc_attr($color_dark); ?>"
             data-color-light="<?php echo esc_attr($color_light); ?>"
             data-download="<?php echo $show_download ? '1' : '0'; ?>">
            <div class="qrcodr-frontend-canvas"></div>
            <?php if ($show_download) : ?>
                <button type="button" class="qrcodr-frontend-download" style="display:none;">
                    <?php esc_html_e('دانلود QR Code', 'qrcodr'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_missing_notice()
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<p class="qrcodr-frontend-error">' .
            esc_html__('QRCODR: کد مشخص‌شده پیدا نشد یا غیرفعال است. (این پیام فقط به مدیر سایت نمایش داده می‌شود)', 'qrcodr') .
            '</p>';
    }
}

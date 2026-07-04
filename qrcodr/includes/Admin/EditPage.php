<?php

namespace QRCODR\Admin;

use QRCODR\Codes\CodeRepository;

if (!defined('ABSPATH')) {
    exit;
}

class EditPage
{
    public static function render()
    {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $repository = new CodeRepository();
        $code = $id ? $repository->find_by_id($id) : null;

        $title = $code ? $code->title : '';
        $destination_url = $code ? $code->destination_url : '';
        $style = $code && $code->style_json ? json_decode($code->style_json, true) : array();
        $size = isset($style['size']) ? (int) $style['size'] : 300;
        $color_dark = isset($style['color_dark']) ? $style['color_dark'] : '#000000';
        $color_light = isset($style['color_light']) ? $style['color_light'] : '#ffffff';
        $short_url = $code ? home_url('qr/' . $code->short_code) : '';
        $base_url = admin_url('admin.php?page=' . Menu::SLUG);
        ?>
        <div class="wrap qrcodr-wrap">
            <h1><?php echo $code ? esc_html__('ویرایش QR Code', 'qrcodr') : esc_html__('افزودن QR Code جدید', 'qrcodr'); ?></h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="qrcodr-form">
                <input type="hidden" name="action" value="qrcodr_save_code">
                <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('qrcodr_save_code'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="qrcodr-title"><?php esc_html_e('عنوان', 'qrcodr'); ?></label></th>
                        <td><input type="text" id="qrcodr-title" name="title" class="regular-text" required value="<?php echo esc_attr($title); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="qrcodr-destination"><?php esc_html_e('آدرس مقصد (قابل تغییر در آینده)', 'qrcodr'); ?></label></th>
                        <td>
                            <input type="url" id="qrcodr-destination" name="destination_url" class="regular-text" required
                                   placeholder="https://example.com" value="<?php echo esc_attr($destination_url); ?>">
                            <p class="description"><?php esc_html_e('این مقصد را هر زمان بخواهید می‌توانید بدون تغییر خود QR Code تغییر دهید.', 'qrcodr'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qrcodr-size"><?php esc_html_e('سایز', 'qrcodr'); ?></label></th>
                        <td>
                            <select id="qrcodr-size" name="size">
                                <?php foreach (array(200, 300, 400, 500) as $option) : ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($size, $option); ?>><?php echo esc_html($option . 'x' . $option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('رنگ‌بندی', 'qrcodr'); ?></th>
                        <td>
                            <label><?php esc_html_e('رنگ QR', 'qrcodr'); ?> <input type="color" id="qrcodr-color-dark" name="color_dark" value="<?php echo esc_attr($color_dark); ?>"></label>
                            &nbsp;&nbsp;
                            <label><?php esc_html_e('رنگ پس‌زمینه', 'qrcodr'); ?> <input type="color" id="qrcodr-color-light" name="color_light" value="<?php echo esc_attr($color_light); ?>"></label>
                        </td>
                    </tr>
                    <?php if ($code) : ?>
                    <tr>
                        <th><label for="qrcodr-status"><?php esc_html_e('وضعیت', 'qrcodr'); ?></label></th>
                        <td>
                            <select id="qrcodr-status" name="status">
                                <option value="active" <?php selected($code->status, 'active'); ?>><?php esc_html_e('فعال', 'qrcodr'); ?></option>
                                <option value="paused" <?php selected($code->status, 'paused'); ?>><?php esc_html_e('متوقف', 'qrcodr'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('لینک کوتاه (ثابت)', 'qrcodr'); ?></th>
                        <td><code id="qrcodr-short-url" data-short-url="<?php echo esc_attr($short_url); ?>"><?php echo esc_html($short_url); ?></code></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <div id="qrcodr-preview" class="qrcodr-preview">
                    <div id="qrcodr-canvas-target"></div>
                    <button type="button" class="button" id="qrcodr-download-btn" style="display:none;"><?php esc_html_e('دانلود پیش‌نمایش', 'qrcodr'); ?></button>
                    <p class="description">
                        <?php echo $code
                            ? esc_html__('پیش‌نمایش QR Code بر اساس لینک کوتاه بالا.', 'qrcodr')
                            : esc_html__('بعد از ذخیره، لینک کوتاه ساخته می‌شود و QR واقعی قابل دانلود خواهد بود.', 'qrcodr'); ?>
                    </p>
                </div>

                <?php submit_button($code ? __('ذخیره تغییرات', 'qrcodr') : __('ایجاد QR Code', 'qrcodr')); ?>
            </form>

            <p><a href="<?php echo esc_url($base_url); ?>">&larr; <?php esc_html_e('بازگشت به لیست', 'qrcodr'); ?></a></p>
        </div>
        <?php
    }

    public static function save()
    {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $destination_url = isset($_POST['destination_url']) ? esc_url_raw(wp_unslash($_POST['destination_url'])) : '';
        $status = isset($_POST['status']) && $_POST['status'] === 'paused' ? 'paused' : 'active';

        $size = isset($_POST['size']) ? absint($_POST['size']) : 300;
        if (!in_array($size, array(200, 300, 400, 500), true)) {
            $size = 300;
        }

        $color_dark = isset($_POST['color_dark']) ? sanitize_hex_color(wp_unslash($_POST['color_dark'])) : '#000000';
        $color_light = isset($_POST['color_light']) ? sanitize_hex_color(wp_unslash($_POST['color_light'])) : '#ffffff';

        $style = array(
            'size' => $size,
            'color_dark' => $color_dark ?: '#000000',
            'color_light' => $color_light ?: '#ffffff',
        );

        $repository = new CodeRepository();

        if (empty($title) || empty($destination_url)) {
            wp_safe_redirect(add_query_arg(
                array('page' => Menu::SLUG, 'action' => $id ? 'edit' : 'new', 'id' => $id, 'qrcodr_error' => 'invalid'),
                admin_url('admin.php')
            ));
            exit;
        }

        if ($id) {
            $repository->update($id, $title, $destination_url, $style, $status);
        } else {
            $id = $repository->insert($title, $destination_url, $style);
        }

        wp_safe_redirect(add_query_arg(
            array('page' => Menu::SLUG, 'qrcodr_notice' => 'saved'),
            admin_url('admin.php')
        ));
        exit;
    }
}

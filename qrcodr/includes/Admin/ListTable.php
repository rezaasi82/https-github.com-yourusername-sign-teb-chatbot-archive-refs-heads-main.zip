<?php

namespace QRCODR\Admin;

use QRCODR\Codes\CodeRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ListTable
{
    public static function render()
    {
        $repository = new CodeRepository();
        $codes = $repository->all_with_scan_counts();
        $base_url = admin_url('admin.php?page=' . Menu::SLUG);
        ?>
        <div class="wrap qrcodr-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('QR Code های داینامیک', 'qrcodr'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg('action', 'new', $base_url)); ?>" class="page-title-action">
                <?php esc_html_e('افزودن QR Code جدید', 'qrcodr'); ?>
            </a>

            <?php if (isset($_GET['qrcodr_notice']) && $_GET['qrcodr_notice'] === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('ذخیره شد.', 'qrcodr'); ?></p></div>
            <?php elseif (isset($_GET['qrcodr_notice']) && $_GET['qrcodr_notice'] === 'deleted') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('حذف شد.', 'qrcodr'); ?></p></div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('عنوان', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('لینک کوتاه', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('مقصد فعلی', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('تعداد اسکن', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('عملیات', 'qrcodr'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)) : ?>
                        <tr><td colspan="6"><?php esc_html_e('هنوز QR Code ای ساخته نشده است.', 'qrcodr'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($codes as $code) : ?>
                            <?php $short_url = home_url('qr/' . $code->short_code); ?>
                            <tr>
                                <td><strong><?php echo esc_html($code->title); ?></strong></td>
                                <td><a href="<?php echo esc_url($short_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($short_url); ?></a></td>
                                <td><?php echo esc_html($code->destination_url); ?></td>
                                <td><?php echo $code->status === 'active' ? esc_html__('فعال', 'qrcodr') : esc_html__('متوقف', 'qrcodr'); ?></td>
                                <td><?php echo esc_html($code->scan_count); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $code->id), $base_url)); ?>"><?php esc_html_e('ویرایش', 'qrcodr'); ?></a>
                                    |
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'analytics', 'id' => $code->id), $base_url)); ?>"><?php esc_html_e('آمار', 'qrcodr'); ?></a>
                                    |
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=qrcodr_delete_code&id=' . $code->id), 'qrcodr_delete_code_' . $code->id)); ?>"
                                       onclick="return confirm('<?php echo esc_js(__('این QR Code و تمام آمار اسکن آن حذف خواهد شد. ادامه می‌دهید؟', 'qrcodr')); ?>');">
                                        <?php esc_html_e('حذف', 'qrcodr'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function delete($id)
    {
        $repository = new CodeRepository();
        $repository->delete($id);

        wp_safe_redirect(add_query_arg('qrcodr_notice', 'deleted', admin_url('admin.php?page=' . Menu::SLUG)));
        exit;
    }
}

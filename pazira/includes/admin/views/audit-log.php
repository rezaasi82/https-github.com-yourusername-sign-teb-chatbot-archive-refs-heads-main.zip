<?php
/**
 * Security audit log viewer.
 *
 * @var array<int,object> $rows
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

$sev_color = ['info' => '#50607a', 'warning' => '#8a6d1b', 'critical' => '#d63638'];
?>
<div class="wrap pzr-admin" dir="rtl">
    <?php \Pazira\Admin\PageHeader::render(
        __('لاگ امنیت و رویدادها', 'pazira'),
        __('رویدادهای حساس افزونه: ورود ناموفق، تغییر تنظیمات و ارجاع لید.', 'pazira')
    ); ?>
    <p class="description"><?php esc_html_e('رویدادهای امنیتی و مدیریتی (تغییر تنظیمات، لایسنس، خروجی، تلاش‌های ناموفق و قفل‌ها). نگهداری ۹۰ روز.', 'pazira'); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('زمان', 'pazira'); ?></th>
                <th><?php esc_html_e('رویداد', 'pazira'); ?></th>
                <th><?php esc_html_e('مورد', 'pazira'); ?></th>
                <th><?php esc_html_e('کاربر', 'pazira'); ?></th>
                <th><?php esc_html_e('IP', 'pazira'); ?></th>
                <th><?php esc_html_e('شدت', 'pazira'); ?></th>
                <th><?php esc_html_e('جزئیات', 'pazira'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)) : ?>
            <tr><td colspan="7"><?php esc_html_e('رویدادی ثبت نشده است.', 'pazira'); ?></td></tr>
        <?php else : ?>
            <?php foreach ($rows as $r) :
                $user = $r->user_id ? get_userdata((int) $r->user_id) : null;
                ?>
                <tr>
                    <td><?php echo esc_html(mysql2date('Y/m/d H:i', $r->created_at)); ?></td>
                    <td><code><?php echo esc_html($r->action); ?></code></td>
                    <td><?php echo esc_html($r->object ?: '—'); ?></td>
                    <td><?php echo esc_html($user ? $user->user_login : ($r->user_id ? '#' . $r->user_id : '—')); ?></td>
                    <td style="direction:ltr"><?php echo esc_html($r->ip ?: '—'); ?></td>
                    <td><span style="color:<?php echo esc_attr($sev_color[$r->severity] ?? '#50607a'); ?>;font-weight:700"><?php echo esc_html($r->severity); ?></span></td>
                    <td><?php echo esc_html($r->detail ?: '—'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

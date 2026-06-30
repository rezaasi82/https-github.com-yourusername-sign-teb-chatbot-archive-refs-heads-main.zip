<?php
/**
 * Stats dashboard (rendered inside the Stats tab).
 *
 * @var array                $stats
 * @var array<int,object>    $top
 * @var SWC_License_Manager  $license
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<p class="description"><?php esc_html_e('آمار ۳۰ روز اخیر', 'signteb-web-chat'); ?></p>

<div class="swc-cards">
    <div class="swc-card">
        <span class="swc-card-num"><?php echo esc_html(number_format_i18n($stats['conversations'])); ?></span>
        <span class="swc-card-label"><?php esc_html_e('مکالمه', 'signteb-web-chat'); ?></span>
    </div>
    <div class="swc-card swc-card-accent">
        <span class="swc-card-num"><?php echo esc_html(number_format_i18n($stats['leads'])); ?></span>
        <span class="swc-card-label"><?php esc_html_e('لید / CTA', 'signteb-web-chat'); ?></span>
    </div>
    <div class="swc-card">
        <span class="swc-card-num"><?php echo esc_html($stats['conversion_rate']); ?>٪</span>
        <span class="swc-card-label"><?php esc_html_e('نرخ تبدیل', 'signteb-web-chat'); ?></span>
    </div>
    <div class="swc-card">
        <span class="swc-card-num"><?php echo $license->is_active() ? '∞' : esc_html(number_format_i18n($license->trial_remaining())); ?></span>
        <span class="swc-card-label"><?php esc_html_e('پیام آزمایشی باقی‌مانده', 'signteb-web-chat'); ?></span>
    </div>
</div>

<h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'signteb-web-chat'); ?></h2>
<p class="description"><?php esc_html_e('برای کشف شکاف محتوایی و ایده‌ی سئو', 'signteb-web-chat'); ?></p>
<table class="widefat striped">
    <thead><tr><th><?php esc_html_e('پرسش', 'signteb-web-chat'); ?></th><th style="width:90px"><?php esc_html_e('تعداد', 'signteb-web-chat'); ?></th></tr></thead>
    <tbody>
    <?php if (empty($top)) : ?>
        <tr><td colspan="2"><?php esc_html_e('هنوز داده‌ای ثبت نشده است.', 'signteb-web-chat'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($top as $row) : ?>
            <tr><td><?php echo esc_html($row->q); ?></td><td><?php echo esc_html(number_format_i18n($row->c)); ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

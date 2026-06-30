<?php
/**
 * Dashboard view.
 *
 * @var array               $stats
 * @var array<int,object>   $top
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap stmc-admin" dir="rtl">
    <h1><?php esc_html_e('SignTeb AI Chat — داشبورد', 'signteb-ai-chat'); ?></h1>
    <p class="description"><?php esc_html_e('آمار ۳۰ روز اخیر', 'signteb-ai-chat'); ?></p>

    <div class="stmc-cards">
        <div class="stmc-card">
            <span class="stmc-card-num"><?php echo esc_html(number_format_i18n($stats['conversations'])); ?></span>
            <span class="stmc-card-label"><?php esc_html_e('مکالمه', 'signteb-ai-chat'); ?></span>
        </div>
        <div class="stmc-card stmc-card-accent">
            <span class="stmc-card-num"><?php echo esc_html(number_format_i18n($stats['leads'])); ?></span>
            <span class="stmc-card-label"><?php esc_html_e('لید / CTA', 'signteb-ai-chat'); ?></span>
        </div>
        <div class="stmc-card">
            <span class="stmc-card-num"><?php echo esc_html($stats['conversion_rate']); ?>٪</span>
            <span class="stmc-card-label"><?php esc_html_e('نرخ تبدیل', 'signteb-ai-chat'); ?></span>
        </div>
    </div>

    <h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'signteb-ai-chat'); ?></h2>
    <p class="description"><?php esc_html_e('برای کشف شکاف محتوایی و ایده‌ی سئو', 'signteb-ai-chat'); ?></p>
    <table class="widefat striped">
        <thead><tr><th><?php esc_html_e('پرسش', 'signteb-ai-chat'); ?></th><th style="width:90px"><?php esc_html_e('تعداد', 'signteb-ai-chat'); ?></th></tr></thead>
        <tbody>
        <?php if (empty($top)) : ?>
            <tr><td colspan="2"><?php esc_html_e('هنوز داده‌ای ثبت نشده است.', 'signteb-ai-chat'); ?></td></tr>
        <?php else : ?>
            <?php foreach ($top as $row) : ?>
                <tr><td><?php echo esc_html($row->q); ?></td><td><?php echo esc_html(number_format_i18n($row->c)); ?></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

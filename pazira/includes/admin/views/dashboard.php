<?php
/**
 * Professional analytics dashboard (Stats tab).
 *
 * @var array               $stats
 * @var array<string,int>   $clicks
 * @var array<string,int>   $daily     Daily conversation counts.
 * @var array<string,int>   $daily_c   Daily click counts.
 * @var array<int,object>   $top
 * @var array<string,int>   $demand   Most-requested services.
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render a CSP-safe bar chart from a date=>value map (no external JS).
 */
$render_bars = static function (array $series, string $color): void {
    $max = max(1, max($series ?: [0]));
    echo '<div class="pzr-chart">';
    foreach ($series as $day => $val) {
        $h     = (int) round(($val / $max) * 100);
        $label = wp_date('m/d', strtotime($day . ' 00:00:00'));
        printf(
            '<div class="pzr-bar-col"><span class="pzr-bar-val">%s</span><span class="pzr-bar" style="height:%d%%;background:%s"></span><span class="pzr-bar-x">%s</span></div>',
            esc_html(number_format_i18n($val)),
            $h,
            esc_attr($color),
            esc_html($label)
        );
    }
    echo '</div>';
};
?>
<p class="description"><?php esc_html_e('آمار ۳۰ روز اخیر', 'pazira'); ?></p>

<div class="pzr-cards">
    <div class="pzr-card"><span class="pzr-card-num"><?php echo esc_html(number_format_i18n($stats['conversations'])); ?></span><span class="pzr-card-label"><?php esc_html_e('گفتگو', 'pazira'); ?></span></div>
    <div class="pzr-card pzr-card-accent"><span class="pzr-card-num"><?php echo esc_html(number_format_i18n($stats['leads'])); ?></span><span class="pzr-card-label"><?php esc_html_e('لید', 'pazira'); ?></span></div>
    <div class="pzr-card"><span class="pzr-card-num">🟢 <?php echo esc_html(number_format_i18n($stats['hot_leads'])); ?></span><span class="pzr-card-label"><?php esc_html_e('لید داغ', 'pazira'); ?></span></div>
    <div class="pzr-card"><span class="pzr-card-num"><?php echo esc_html($stats['conversion_rate']); ?>٪</span><span class="pzr-card-label"><?php esc_html_e('نرخ تبدیل', 'pazira'); ?></span></div>
    <div class="pzr-card"><span class="pzr-card-num"><?php echo esc_html(number_format_i18n($stats['booked'])); ?></span><span class="pzr-card-label"><?php esc_html_e('کلیک رزرو', 'pazira'); ?></span></div>
</div>

<h2><?php esc_html_e('کلیک کانال‌های ارتباطی', 'pazira'); ?></h2>
<div class="pzr-cards">
    <div class="pzr-card"><span class="pzr-card-num">📅 <?php echo esc_html(number_format_i18n($clicks['booking'])); ?></span><span class="pzr-card-label"><?php esc_html_e('رزرو نوبت', 'pazira'); ?></span></div>
    <div class="pzr-card"><span class="pzr-card-num">💬 <?php echo esc_html(number_format_i18n($clicks['whatsapp'])); ?></span><span class="pzr-card-label"><?php esc_html_e('واتساپ', 'pazira'); ?></span></div>
    <div class="pzr-card"><span class="pzr-card-num">📞 <?php echo esc_html(number_format_i18n($clicks['call'])); ?></span><span class="pzr-card-label"><?php esc_html_e('تماس', 'pazira'); ?></span></div>
    <div class="pzr-card"><span class="pzr-card-num">🟦 <?php echo esc_html(number_format_i18n($clicks['bale'])); ?></span><span class="pzr-card-label"><?php esc_html_e('بله', 'pazira'); ?></span></div>
</div>

<h2><?php esc_html_e('روند گفتگوها (۱۴ روز اخیر)', 'pazira'); ?></h2>
<?php $render_bars($daily, '#22468a'); ?>

<h2><?php esc_html_e('روند کلیک‌ها (۱۴ روز اخیر)', 'pazira'); ?></h2>
<?php $render_bars($daily_c, '#c8a04e'); ?>

<h2><?php esc_html_e('پرتقاضاترین خدمات', 'pazira'); ?></h2>
<p class="description"><?php esc_html_e('خدماتی که بیماران در گفتگوها بیشتر درباره‌شان پرسیده‌اند (سیگنال یادگیری برای تمرکز تبلیغات و محتوا).', 'pazira'); ?></p>
<table class="widefat striped" style="max-width:520px">
    <thead><tr><th><?php esc_html_e('خدمت', 'pazira'); ?></th><th style="width:90px"><?php esc_html_e('درخواست', 'pazira'); ?></th></tr></thead>
    <tbody>
    <?php if (empty($demand)) : ?>
        <tr><td colspan="2"><?php esc_html_e('هنوز داده‌ای ثبت نشده است.', 'pazira'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($demand as $name => $count) : ?>
            <tr><td><?php echo esc_html($name); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'pazira'); ?></h2>
<p class="description"><?php esc_html_e('برای کشف شکاف محتوایی و ایده‌ی سئو', 'pazira'); ?></p>
<table class="widefat striped">
    <thead><tr><th><?php esc_html_e('پرسش', 'pazira'); ?></th><th style="width:90px"><?php esc_html_e('تعداد', 'pazira'); ?></th></tr></thead>
    <tbody>
    <?php if (empty($top)) : ?>
        <tr><td colspan="2"><?php esc_html_e('هنوز داده‌ای ثبت نشده است.', 'pazira'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($top as $row) : ?>
            <tr><td><?php echo esc_html($row->q); ?></td><td><?php echo esc_html(number_format_i18n($row->c)); ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

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
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render a CSP-safe bar chart from a date=>value map (no external JS).
 */
$render_bars = static function (array $series, string $color): void {
    $max = max(1, max($series ?: [0]));
    echo '<div class="pzk-chart">';
    foreach ($series as $day => $val) {
        $h     = (int) round(($val / $max) * 100);
        $label = wp_date('m/d', strtotime($day . ' 00:00:00'));
        printf(
            '<div class="pzk-bar-col"><span class="pzk-bar-val">%s</span><span class="pzk-bar" style="height:%d%%;background:%s"></span><span class="pzk-bar-x">%s</span></div>',
            esc_html(number_format_i18n($val)),
            $h,
            esc_attr($color),
            esc_html($label)
        );
    }
    echo '</div>';
};
?>
<p class="description"><?php esc_html_e('آمار ۳۰ روز اخیر', 'pezhkam'); ?></p>

<div class="pzk-cards">
    <div class="pzk-card"><span class="pzk-card-num"><?php echo esc_html(number_format_i18n($stats['conversations'])); ?></span><span class="pzk-card-label"><?php esc_html_e('گفتگو', 'pezhkam'); ?></span></div>
    <div class="pzk-card pzk-card-accent"><span class="pzk-card-num"><?php echo esc_html(number_format_i18n($stats['leads'])); ?></span><span class="pzk-card-label"><?php esc_html_e('لید', 'pezhkam'); ?></span></div>
    <div class="pzk-card"><span class="pzk-card-num"><span class="pzk-dot pzk-dot-hot"><i aria-hidden="true"></i></span><?php echo esc_html(number_format_i18n($stats['hot_leads'])); ?></span><span class="pzk-card-label"><?php esc_html_e('لید داغ', 'pezhkam'); ?></span></div>
    <div class="pzk-card"><span class="pzk-card-num"><?php echo esc_html($stats['conversion_rate']); ?>٪</span><span class="pzk-card-label"><?php esc_html_e('نرخ تبدیل', 'pezhkam'); ?></span></div>
    <div class="pzk-card"><span class="pzk-card-num"><?php echo esc_html(number_format_i18n($stats['booked'])); ?></span><span class="pzk-card-label"><?php esc_html_e('کلیک رزرو', 'pezhkam'); ?></span></div>
</div>

<h2><?php esc_html_e('کلیک کانال‌های ارتباطی', 'pezhkam'); ?></h2>
<div class="pzk-cards">
    <div class="pzk-card"><span class="pzk-card-num"><?php echo \Pezhkam\Admin\Icon::svg('calendar'); ?><?php echo esc_html(number_format_i18n($clicks['booking'])); ?></span><span class="pzk-card-label"><?php esc_html_e('رزرو نوبت', 'pezhkam'); ?></span></div>
    <div class="pzk-card"><span class="pzk-card-num"><?php echo \Pezhkam\Admin\Icon::svg('chat'); ?><?php echo esc_html(number_format_i18n($clicks['whatsapp'])); ?></span><span class="pzk-card-label"><?php esc_html_e('واتساپ', 'pezhkam'); ?></span></div>
    <div class="pzk-card"><span class="pzk-card-num"><?php echo \Pezhkam\Admin\Icon::svg('phone'); ?><?php echo esc_html(number_format_i18n($clicks['call'])); ?></span><span class="pzk-card-label"><?php esc_html_e('تماس', 'pezhkam'); ?></span></div>
    <div class="pzk-card"><span class="pzk-card-num"><?php echo \Pezhkam\Admin\Icon::svg('send'); ?><?php echo esc_html(number_format_i18n($clicks['bale'])); ?></span><span class="pzk-card-label"><?php esc_html_e('بله', 'pezhkam'); ?></span></div>
</div>

<h2><?php esc_html_e('روند گفتگوها (۱۴ روز اخیر)', 'pezhkam'); ?></h2>
<?php $render_bars($daily, '#22468a'); ?>

<h2><?php esc_html_e('روند کلیک‌ها (۱۴ روز اخیر)', 'pezhkam'); ?></h2>
<?php $render_bars($daily_c, '#c8a04e'); ?>

<h2><?php esc_html_e('پرتقاضاترین خدمات', 'pezhkam'); ?></h2>
<p class="description"><?php esc_html_e('خدماتی که بیماران در گفتگوها بیشتر درباره‌شان پرسیده‌اند (سیگنال یادگیری برای تمرکز تبلیغات و محتوا).', 'pezhkam'); ?></p>
<table class="widefat striped" style="max-width:520px">
    <thead><tr><th><?php esc_html_e('خدمت', 'pezhkam'); ?></th><th style="width:90px"><?php esc_html_e('درخواست', 'pezhkam'); ?></th></tr></thead>
    <tbody>
    <?php if (empty($demand)) : ?>
        <tr><td colspan="2"><?php esc_html_e('هنوز داده‌ای ثبت نشده است.', 'pezhkam'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($demand as $name => $count) : ?>
            <tr><td><?php echo esc_html($name); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'pezhkam'); ?></h2>
<p class="description"><?php esc_html_e('برای کشف شکاف محتوایی و ایده‌ی سئو', 'pezhkam'); ?></p>
<table class="widefat striped">
    <thead><tr><th><?php esc_html_e('پرسش', 'pezhkam'); ?></th><th style="width:90px"><?php esc_html_e('تعداد', 'pezhkam'); ?></th></tr></thead>
    <tbody>
    <?php if (empty($top)) : ?>
        <tr><td colspan="2"><?php esc_html_e('هنوز داده‌ای ثبت نشده است.', 'pezhkam'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($top as $row) : ?>
            <tr><td><?php echo esc_html($row->q); ?></td><td><?php echo esc_html(number_format_i18n($row->c)); ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

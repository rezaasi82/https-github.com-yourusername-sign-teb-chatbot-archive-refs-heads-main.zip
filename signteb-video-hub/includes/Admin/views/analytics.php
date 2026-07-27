<?php
/**
 * Analytics view.
 *
 * @var array<string,mixed> $data Provided by AnalyticsPage::render().
 *
 * @package SignTeb\VideoHub
 */

if (! defined('ABSPATH')) {
    exit;
}

use SignTeb\VideoHub\Helpers\Format;

$totals = $data['totals'];
$trend  = $data['trend'];
$peak   = $trend === [] ? 0 : max(array_map('intval', $trend));

$table = static function (array $rows, string $metric_label, string $metric_key): void {
    if ($rows === []) {
        echo '<p class="stvh-muted">' . esc_html__('داده‌ای ثبت نشده است.', 'signteb-video-hub') . '</p>';
        return;
    }
    ?>
    <table class="widefat striped">
        <thead>
        <tr>
            <th><?php esc_html_e('ویدئو', 'signteb-video-hub'); ?></th>
            <th><?php echo esc_html($metric_label); ?></th>
            <th><?php esc_html_e('نمایش', 'signteb-video-hub'); ?></th>
            <th><?php esc_html_e('کلیک', 'signteb-video-hub'); ?></th>
            <th><?php esc_html_e('CTR', 'signteb-video-hub'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url((string) get_edit_post_link($row['video_id'])); ?>">
                        <?php echo esc_html((string) get_the_title($row['video_id'])); ?>
                    </a>
                </td>
                <td>
                    <?php
                    echo esc_html(
                        $metric_key === 'watch_seconds'
                            ? Format::duration((int) $row[$metric_key])
                            : number_format_i18n((int) $row[$metric_key])
                    );
                    ?>
                </td>
                <td><?php echo esc_html(number_format_i18n($row['impressions'])); ?></td>
                <td><?php echo esc_html(number_format_i18n($row['clicks'])); ?></td>
                <td><?php echo esc_html(number_format_i18n($row['ctr'], 1)); ?>٪</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
};
?>
<div class="wrap stvh-admin">
    <h1><?php esc_html_e('آمار و تحلیل ویدئو', 'signteb-video-hub'); ?></h1>

    <?php if (! $data['enabled']) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('جمع‌آوری آمار در تنظیمات غیرفعال است؛ اعداد زیر فقط داده‌های قبلی را نشان می‌دهند.', 'signteb-video-hub'); ?></p>
        </div>
    <?php endif; ?>

    <ul class="stvh-range">
        <?php foreach ([7, 30, 90] as $range) : ?>
            <li>
                <a class="button <?php echo (int) $data['days'] === $range ? 'button-primary' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg(['page' => 'stvh-analytics', 'range' => $range], admin_url('admin.php'))); ?>">
                    <?php
                    printf(
                        /* translators: %s: number of days */
                        esc_html__('%s روز', 'signteb-video-hub'),
                        esc_html(number_format_i18n($range))
                    );
                    ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="stvh-tiles">
        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('نمایش کارت', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($totals['impressions'])); ?></span>
        </div>
        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('کلیک', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($totals['clicks'])); ?></span>
            <span class="stvh-tile__hint">CTR <?php echo esc_html(number_format_i18n($totals['ctr'], 1)); ?>٪</span>
        </div>
        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('پخش', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($totals['plays'])); ?></span>
            <span class="stvh-tile__hint">
                <?php
                printf(
                    /* translators: %s: play rate percentage */
                    esc_html__('نرخ پخش %s٪', 'signteb-video-hub'),
                    esc_html(number_format_i18n($totals['play_rate'], 1))
                );
                ?>
            </span>
        </div>
        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('مدت تماشا', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value stvh-tile__value--sm"><?php echo esc_html(Format::duration($totals['watch_seconds'])); ?></span>
            <span class="stvh-tile__hint">
                <?php
                printf(
                    /* translators: %s: average watch time per play */
                    esc_html__('میانگین هر پخش: %s', 'signteb-video-hub'),
                    esc_html(Format::duration($totals['avg_watch']))
                );
                ?>
            </span>
        </div>
    </div>

    <section class="stvh-panel">
        <h2><?php esc_html_e('روند پخش ۱۴ روز اخیر', 'signteb-video-hub'); ?></h2>
        <div class="stvh-spark">
            <?php foreach ($trend as $date => $count) : ?>
                <div class="stvh-spark__bar"
                     style="height: <?php echo esc_attr((string) ($peak > 0 ? max(4, (int) round(($count / $peak) * 100)) : 4)); ?>%"
                     title="<?php echo esc_attr($date . ' — ' . number_format_i18n($count)); ?>">
                    <span class="screen-reader-text"><?php echo esc_html($date . ': ' . number_format_i18n($count)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="stvh-panel">
        <h2><?php esc_html_e('بیشترین پخش', 'signteb-video-hub'); ?></h2>
        <?php $table($data['by_plays'], __('پخش', 'signteb-video-hub'), 'plays'); ?>
    </section>

    <section class="stvh-panel">
        <h2><?php esc_html_e('بیشترین نرخ کلیک', 'signteb-video-hub'); ?></h2>
        <?php $table($data['by_ctr'], __('پخش', 'signteb-video-hub'), 'plays'); ?>
    </section>

    <section class="stvh-panel">
        <h2><?php esc_html_e('بیشترین مدت تماشا', 'signteb-video-hub'); ?></h2>
        <?php $table($data['by_watch'], __('مدت تماشا', 'signteb-video-hub'), 'watch_seconds'); ?>
    </section>
</div>

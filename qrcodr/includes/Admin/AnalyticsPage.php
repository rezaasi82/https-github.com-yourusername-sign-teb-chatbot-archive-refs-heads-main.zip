<?php

namespace QRCODR\Admin;

use QRCODR\Codes\CodeRepository;
use QRCODR\Scans\ScanRepository;

if (!defined('ABSPATH')) {
    exit;
}

class AnalyticsPage
{
    public static function render()
    {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $code_repository = new CodeRepository();
        $code = $code_repository->find_by_id($id);

        if (!$code) {
            wp_die(esc_html__('این QR Code پیدا نشد.', 'qrcodr'));
        }

        $scans = new ScanRepository();
        $total = $scans->total_scans($id);
        $unique = $scans->unique_scans($id);
        $devices = $scans->breakdown_by($id, 'device_type');
        $browsers = $scans->breakdown_by($id, 'browser');
        $trend = $scans->daily_trend($id, 30);
        $recent = $scans->recent($id, 20);

        $max_trend = 1;
        foreach ($trend as $day) {
            $max_trend = max($max_trend, (int) $day->total);
        }

        $base_url = admin_url('admin.php?page=' . Menu::SLUG);
        ?>
        <div class="wrap qrcodr-wrap">
            <h1><?php
                /* translators: %s: QR code title */
                printf(esc_html__('آمار اسکن: %s', 'qrcodr'), esc_html($code->title));
            ?></h1>

            <div class="qrcodr-kpi-row">
                <div class="qrcodr-kpi"><span class="qrcodr-kpi-value"><?php echo esc_html($total); ?></span><span class="qrcodr-kpi-label"><?php esc_html_e('کل اسکن‌ها', 'qrcodr'); ?></span></div>
                <div class="qrcodr-kpi"><span class="qrcodr-kpi-value"><?php echo esc_html($unique); ?></span><span class="qrcodr-kpi-label"><?php esc_html_e('اسکن‌های یکتا', 'qrcodr'); ?></span></div>
            </div>

            <h2><?php esc_html_e('روند ۳۰ روز اخیر', 'qrcodr'); ?></h2>
            <?php if (empty($trend)) : ?>
                <p><?php esc_html_e('هنوز اسکنی ثبت نشده است.', 'qrcodr'); ?></p>
            <?php else : ?>
                <div class="qrcodr-trend-chart">
                    <?php foreach ($trend as $day) : ?>
                        <div class="qrcodr-trend-bar" style="height: <?php echo esc_attr((int) round(($day->total / $max_trend) * 100)); ?>%;"
                             title="<?php echo esc_attr($day->day . ': ' . $day->total); ?>"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="qrcodr-breakdown-row">
                <div>
                    <h2><?php esc_html_e('نوع دستگاه', 'qrcodr'); ?></h2>
                    <?php self::render_breakdown_table($devices); ?>
                </div>
                <div>
                    <h2><?php esc_html_e('مرورگر', 'qrcodr'); ?></h2>
                    <?php self::render_breakdown_table($browsers); ?>
                </div>
            </div>

            <h2><?php esc_html_e('اسکن‌های اخیر', 'qrcodr'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('زمان', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('دستگاه', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('مرورگر', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('سیستم‌عامل', 'qrcodr'); ?></th>
                        <th><?php esc_html_e('ریفرر', 'qrcodr'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('هنوز اسکنی ثبت نشده است.', 'qrcodr'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($recent as $scan) : ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date('Y-m-d H:i', $scan->scanned_at)); ?></td>
                                <td><?php echo esc_html($scan->device_type); ?></td>
                                <td><?php echo esc_html($scan->browser); ?></td>
                                <td><?php echo esc_html($scan->os); ?></td>
                                <td><?php echo $scan->referrer ? esc_html(wp_parse_url($scan->referrer, PHP_URL_HOST)) : esc_html__('مستقیم / بدون ریفرر', 'qrcodr'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p><a href="<?php echo esc_url($base_url); ?>">&larr; <?php esc_html_e('بازگشت به لیست', 'qrcodr'); ?></a></p>
        </div>
        <?php
    }

    private static function render_breakdown_table($rows)
    {
        if (empty($rows)) {
            echo '<p>' . esc_html__('داده‌ای موجود نیست.', 'qrcodr') . '</p>';
            return;
        }
        echo '<table class="wp-list-table widefat fixed striped">';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($row->label ?: __('نامشخص', 'qrcodr')) . '</td><td>' . esc_html($row->total) . '</td></tr>';
        }
        echo '</table>';
    }
}

<?php
/**
 * Dashboard view.
 *
 * @var array<string,mixed> $data Provided by DashboardPage::render().
 *
 * @package SignTeb\VideoHub
 */

if (! defined('ABSPATH')) {
    exit;
}

$counts    = $data['counts'];
$totals    = $data['totals'];
$next_runs = $data['next_runs'];

$format_time = static function (int $timestamp): string {
    return $timestamp > 0
        ? wp_date('Y/m/d H:i', $timestamp)
        : __('زمان‌بندی نشده', 'signteb-video-hub');
};
?>
<div class="wrap stvh-admin">
    <h1 class="stvh-admin__title">
        <?php esc_html_e('ویدئو هاب ساین‌طب', 'signteb-video-hub'); ?>
        <span class="stvh-version"><?php echo esc_html(STVH_VERSION); ?></span>
    </h1>

    <div class="stvh-actions">
        <button type="button" class="button button-primary" data-stvh-action="sync">
            <?php esc_html_e('همگام‌سازی دستی', 'signteb-video-hub'); ?>
        </button>
        <button type="button" class="button" data-stvh-action="ai-queue">
            <?php esc_html_e('پردازش صف هوش مصنوعی', 'signteb-video-hub'); ?>
        </button>
        <button type="button" class="button" data-stvh-action="repair">
            <?php esc_html_e('بازسازی ویدئوهای موجود', 'signteb-video-hub'); ?>
        </button>
        <button type="button" class="button" data-stvh-action="purge-cache">
            <?php esc_html_e('پاک‌سازی کش', 'signteb-video-hub'); ?>
        </button>
        <button type="button" class="button" data-stvh-action="index-ping">
            <?php esc_html_e('ارسال صف ایندکس گوگل', 'signteb-video-hub'); ?>
        </button>
        <span class="stvh-feedback" data-stvh-feedback role="status" aria-live="polite"></span>
    </div>

    <div class="stvh-tiles">
        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('ویدئوهای منتشرشده', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($counts['published'])); ?></span>
            <?php if ($counts['draft'] > 0) : ?>
                <span class="stvh-tile__hint">
                    <?php
                    printf(
                        /* translators: %s: number of drafts */
                        esc_html__('%s پیش‌نویس', 'signteb-video-hub'),
                        esc_html(number_format_i18n($counts['draft']))
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('دارای محتوای هوش مصنوعی', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($counts['with_ai'])); ?></span>
            <span class="stvh-tile__hint">
                <?php
                printf(
                    /* translators: %s: pending AI jobs */
                    esc_html__('%s کار در صف', 'signteb-video-hub'),
                    esc_html(number_format_i18n($data['ai_queue']['pending']))
                );
                ?>
            </span>
        </div>

        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('پخش (۳۰ روز)', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($totals['plays'])); ?></span>
            <span class="stvh-tile__hint">
                <?php
                printf(
                    /* translators: %s: click-through rate */
                    esc_html__('نرخ کلیک %s٪', 'signteb-video-hub'),
                    esc_html(number_format_i18n($totals['ctr'], 1))
                );
                ?>
            </span>
        </div>

        <div class="stvh-tile">
            <span class="stvh-tile__label"><?php esc_html_e('آخرین همگام‌سازی', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value stvh-tile__value--sm">
                <?php
                echo esc_html(
                    $data['last_sync'] !== ''
                        ? wp_date('Y/m/d H:i', (int) strtotime($data['last_sync']))
                        : __('انجام نشده', 'signteb-video-hub')
                );
                ?>
            </span>
            <span class="stvh-tile__hint">
                <?php
                printf(
                    /* translators: %s: next scheduled sync time */
                    esc_html__('بعدی: %s', 'signteb-video-hub'),
                    esc_html($format_time($next_runs['sync']))
                );
                ?>
            </span>
        </div>
    </div>

    <div class="stvh-panels">
        <section class="stvh-panel">
            <h2><?php esc_html_e('وضعیت API', 'signteb-video-hub'); ?></h2>
            <table class="widefat striped">
                <tbody>
                <?php foreach ($data['sources'] as $source) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($source['label']); ?></strong></td>
                        <td>
                            <?php if (! $source['configured']) : ?>
                                <span class="stvh-badge"><?php esc_html_e('تنظیم نشده', 'signteb-video-hub'); ?></span>
                            <?php elseif ($source['ok']) : ?>
                                <span class="stvh-badge stvh-badge--ok"><?php esc_html_e('متصل', 'signteb-video-hub'); ?></span>
                            <?php else : ?>
                                <span class="stvh-badge stvh-badge--err"><?php esc_html_e('خطا', 'signteb-video-hub'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="stvh-muted"><?php echo esc_html($source['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><strong><?php esc_html_e('ایندکس گوگل', 'signteb-video-hub'); ?></strong></td>
                    <td>
                        <?php if ($data['indexing']['configured']) : ?>
                            <span class="stvh-badge stvh-badge--ok"><?php esc_html_e('فعال', 'signteb-video-hub'); ?></span>
                        <?php else : ?>
                            <span class="stvh-badge"><?php esc_html_e('غیرفعال', 'signteb-video-hub'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="stvh-muted">
                        <?php
                        printf(
                            /* translators: 1: queued URLs, 2: last ping time */
                            esc_html__('%1$s آدرس در صف — آخرین ارسال: %2$s', 'signteb-video-hub'),
                            esc_html(number_format_i18n($data['indexing']['queued'])),
                            esc_html($data['indexing']['last_ping'] !== '' ? $data['indexing']['last_ping'] : '—')
                        );
                        ?>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="stvh-panel">
            <h2><?php esc_html_e('کش و سازگاری', 'signteb-video-hub'); ?></h2>
            <p class="stvh-muted"><?php esc_html_e('افزونه‌های شناسایی‌شده که هنگام تغییر ویدئو پاک‌سازی می‌شوند:', 'signteb-video-hub'); ?></p>
            <ul class="stvh-list">
                <?php foreach (array_merge($data['caches'], $data['seo_plugins']) as $name => $active) : ?>
                    <li>
                        <span class="stvh-badge <?php echo $active ? 'stvh-badge--ok' : ''; ?>">
                            <?php echo $active ? esc_html__('فعال', 'signteb-video-hub') : esc_html__('غیرفعال', 'signteb-video-hub'); ?>
                        </span>
                        <?php echo esc_html($name); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <a href="<?php echo esc_url($data['sitemap_url']); ?>" target="_blank" rel="noopener">
                    <?php esc_html_e('مشاهده video-sitemap.xml', 'signteb-video-hub'); ?>
                </a>
                <span class="stvh-muted">
                    (<?php
                    printf(
                        /* translators: %s: number of sitemap-eligible videos */
                        esc_html__('%s ویدئوی واجد شرایط', 'signteb-video-hub'),
                        esc_html(number_format_i18n($counts['sitemap']))
                    );
                    ?>)
                </span>
            </p>
        </section>

        <section class="stvh-panel">
            <h2><?php esc_html_e('تاریخچه همگام‌سازی', 'signteb-video-hub'); ?></h2>
            <?php if ($data['sync_log'] === []) : ?>
                <p class="stvh-muted"><?php esc_html_e('هنوز همگام‌سازی انجام نشده است.', 'signteb-video-hub'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('زمان', 'signteb-video-hub'); ?></th>
                        <th><?php esc_html_e('منبع', 'signteb-video-hub'); ?></th>
                        <th><?php esc_html_e('جدید', 'signteb-video-hub'); ?></th>
                        <th><?php esc_html_e('به‌روز', 'signteb-video-hub'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'signteb-video-hub'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data['sync_log'] as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['created_at']); ?></td>
                            <td><?php echo esc_html((string) $row['source']); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row['imported'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row['updated'])); ?></td>
                            <td>
                                <span class="stvh-badge <?php echo $row['status'] === 'success' ? 'stvh-badge--ok' : 'stvh-badge--err'; ?>">
                                    <?php echo esc_html((string) $row['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="stvh-panel">
            <h2><?php esc_html_e('آخرین خطاها', 'signteb-video-hub'); ?></h2>
            <?php if ($data['errors'] === []) : ?>
                <p class="stvh-muted"><?php esc_html_e('خطایی ثبت نشده است.', 'signteb-video-hub'); ?></p>
            <?php else : ?>
                <ul class="stvh-log">
                    <?php foreach ($data['errors'] as $entry) : ?>
                        <li class="stvh-log__item stvh-log__item--<?php echo esc_attr((string) $entry['level']); ?>">
                            <span class="stvh-log__time"><?php echo esc_html((string) $entry['time']); ?></span>
                            <span class="stvh-log__channel"><?php echo esc_html((string) $entry['channel']); ?></span>
                            <span class="stvh-log__message"><?php echo esc_html((string) $entry['message']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

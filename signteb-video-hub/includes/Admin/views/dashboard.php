<?php
/**
 * Dashboard view.
 *
 * Ordered by the questions an operator actually asks, in order: is it working,
 * what do I do next, how much is there, what can I press, and only then the
 * detail panels.
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
$health    = $data['health'];
$setup     = $data['setup'];
$links     = $data['links'];

$format_time = static function (int $timestamp): string {
    return $timestamp > 0
        ? wp_date('Y/m/d H:i', $timestamp)
        : __('زمان‌بندی نشده', 'signteb-video-hub');
};

/** Maintenance actions, each explaining when it is the right button to press. */
$actions = [
    [
        'id'    => 'sync',
        'icon'  => 'update',
        'title' => __('همگام‌سازی دستی', 'signteb-video-hub'),
        'desc'  => __('ویدئوهای جدید کانال را همین حالا می‌آورد، بدون منتظر ماندن برای اجرای خودکار.', 'signteb-video-hub'),
        'primary' => true,
    ],
    [
        'id'    => 'ai-queue',
        'icon'  => 'lightbulb',
        'title' => __('پردازش صف هوش مصنوعی', 'signteb-video-hub'),
        'desc'  => __('خلاصه، پرسش‌وپاسخ و لینک‌های داخلی ویدئوهایی که هنوز در صف مانده‌اند را می‌سازد.', 'signteb-video-hub'),
        'primary' => false,
    ],
    [
        'id'    => 'repair',
        'icon'  => 'admin-tools',
        'title' => __('بازسازی ویدئوهای موجود', 'signteb-video-hub'),
        'desc'  => __('نامک، تصویر شاخص و متن ویدئوهایی که قبلاً وارد شده‌اند را اصلاح می‌کند — دسته‌ای ۱۰تایی.', 'signteb-video-hub'),
        'primary' => false,
    ],
    [
        'id'    => 'purge-cache',
        'icon'  => 'trash',
        'title' => __('پاک‌سازی کش', 'signteb-video-hub'),
        'desc'  => __('اگر تغییری دادید و در سایت دیده نمی‌شود، این را بزنید.', 'signteb-video-hub'),
        'primary' => false,
    ],
    [
        'id'    => 'index-ping',
        'icon'  => 'admin-site-alt3',
        'title' => __('ارسال صف ایندکس گوگل', 'signteb-video-hub'),
        'desc'  => __('آدرس ویدئوهای تازه را به گوگل اعلام می‌کند تا زودتر ایندکس شوند.', 'signteb-video-hub'),
        'primary' => false,
    ],
];
?>
<div class="wrap stvh-admin">
    <h1 class="stvh-admin__title">
        <?php esc_html_e('ویدئو هاب ساین‌طب', 'signteb-video-hub'); ?>
        <span class="stvh-version"><?php echo esc_html(STVH_VERSION); ?></span>
    </h1>

    <div class="stvh-health stvh-health--<?php echo esc_attr((string) $health['level']); ?>">
        <span class="stvh-health__dot" aria-hidden="true"></span>
        <div class="stvh-health__text">
            <strong class="stvh-health__title"><?php echo esc_html((string) $health['title']); ?></strong>
            <?php if ((string) $health['detail'] !== '') : ?>
                <span class="stvh-health__detail"><?php echo esc_html((string) $health['detail']); ?></span>
            <?php endif; ?>
        </div>
        <div class="stvh-health__action">
            <?php if ((string) $health['link'] !== '') : ?>
                <a class="button button-primary" href="<?php echo esc_url((string) $health['link']); ?>">
                    <?php echo esc_html((string) $health['action_label']); ?>
                </a>
            <?php elseif ((string) $health['action'] !== '') : ?>
                <button type="button" class="button button-primary" data-stvh-action="<?php echo esc_attr((string) $health['action']); ?>">
                    <?php echo esc_html((string) $health['action_label']); ?>
                </button>
                <span class="stvh-feedback" data-stvh-feedback role="status" aria-live="polite"></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($setup['done'] < $setup['total']) : ?>
        <section class="stvh-panel stvh-setup">
            <h2>
                <?php esc_html_e('راه‌اندازی', 'signteb-video-hub'); ?>
                <span class="stvh-badge">
                    <?php
                    printf(
                        /* translators: 1: completed steps, 2: total steps */
                        esc_html__('%1$s از %2$s', 'signteb-video-hub'),
                        esc_html(number_format_i18n($setup['done'])),
                        esc_html(number_format_i18n($setup['total']))
                    );
                    ?>
                </span>
            </h2>
            <div class="stvh-progress" role="progressbar"
                 aria-valuenow="<?php echo esc_attr((string) $setup['done']); ?>"
                 aria-valuemin="0" aria-valuemax="<?php echo esc_attr((string) $setup['total']); ?>">
                <span style="width: <?php echo esc_attr((string) round($setup['done'] / max(1, $setup['total']) * 100)); ?>%"></span>
            </div>
            <ol class="stvh-steps">
                <?php foreach ($setup['steps'] as $step) : ?>
                    <li class="stvh-steps__item<?php echo $step['done'] ? ' is-done' : ''; ?>">
                        <span class="stvh-steps__mark" aria-hidden="true"><?php echo $step['done'] ? '✓' : ''; ?></span>
                        <span class="stvh-steps__label"><?php echo esc_html((string) $step['label']); ?></span>
                        <span class="stvh-steps__hint"><?php echo esc_html((string) $step['hint']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <div class="stvh-tiles">
        <a class="stvh-tile" href="<?php echo esc_url($links['videos']); ?>">
            <span class="stvh-tile__label"><?php esc_html_e('ویدئوهای منتشرشده', 'signteb-video-hub'); ?></span>
            <span class="stvh-tile__value"><?php echo esc_html(number_format_i18n($counts['published'])); ?></span>
            <span class="stvh-tile__hint">
                <?php if ($counts['draft'] > 0) : ?>
                    <?php
                    printf(
                        /* translators: %s: number of drafts */
                        esc_html__('%s پیش‌نویس در انتظار', 'signteb-video-hub'),
                        esc_html(number_format_i18n($counts['draft']))
                    );
                    ?>
                <?php else : ?>
                    <?php esc_html_e('بدون پیش‌نویس', 'signteb-video-hub'); ?>
                <?php endif; ?>
            </span>
        </a>

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

        <a class="stvh-tile" href="<?php echo esc_url($links['reports']); ?>">
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
        </a>

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

    <section class="stvh-panel stvh-panel--wide">
        <h2><?php esc_html_e('کارها', 'signteb-video-hub'); ?></h2>
        <div class="stvh-cards">
            <?php foreach ($actions as $action) : ?>
                <div class="stvh-card<?php echo $action['primary'] ? ' stvh-card--primary' : ''; ?>">
                    <h3 class="stvh-card__title">
                        <span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                        <?php echo esc_html($action['title']); ?>
                    </h3>
                    <p class="stvh-card__desc"><?php echo esc_html($action['desc']); ?></p>
                    <div class="stvh-card__foot">
                        <?php // Five buttons all reading "اجرا" are indistinguishable to a
                              // screen reader, which never sees the heading above them. ?>
                        <button type="button"
                                class="button <?php echo $action['primary'] ? 'button-primary' : ''; ?>"
                                data-stvh-action="<?php echo esc_attr($action['id']); ?>"
                                aria-label="<?php echo esc_attr($action['title']); ?>">
                            <?php esc_html_e('اجرا', 'signteb-video-hub'); ?>
                        </button>
                        <span class="stvh-feedback" data-stvh-feedback role="status" aria-live="polite"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="stvh-panels">
        <?php if ($data['errors'] !== []) : ?>
            <section class="stvh-panel stvh-panel--alert">
                <h2><?php esc_html_e('آخرین خطاها', 'signteb-video-hub'); ?></h2>
                <ul class="stvh-log">
                    <?php foreach ($data['errors'] as $entry) : ?>
                        <li class="stvh-log__item stvh-log__item--<?php echo esc_attr((string) $entry['level']); ?>">
                            <span class="stvh-log__meta">
                                <span class="stvh-log__time"><?php echo esc_html((string) $entry['time']); ?></span>
                                <span class="stvh-log__channel"><?php echo esc_html((string) $entry['channel']); ?></span>
                            </span>
                            <span class="stvh-log__message"><?php echo esc_html((string) $entry['message']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

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
                        <?php $ok = (string) $row['status'] === 'success'; ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['created_at']); ?></td>
                            <td><?php echo esc_html((string) $row['source']); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row['imported'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row['updated'])); ?></td>
                            <td>
                                <span class="stvh-badge <?php echo $ok ? 'stvh-badge--ok' : 'stvh-badge--err'; ?>">
                                    <?php
                                    // The raw column value is an English enum; an
                                    // operations screen in Persian should not leak it.
                                    echo esc_html(
                                        $ok
                                            ? __('موفق', 'signteb-video-hub')
                                            : __('ناموفق', 'signteb-video-hub')
                                    );
                                    ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="stvh-panel">
            <h2><?php esc_html_e('کش و سازگاری', 'signteb-video-hub'); ?></h2>
            <p class="stvh-muted"><?php esc_html_e('افزونه‌های شناسایی‌شده که هنگام تغییر ویدئو پاک‌سازی می‌شوند:', 'signteb-video-hub'); ?></p>
            <ul class="stvh-list stvh-list--chips">
                <?php foreach (array_merge($data['caches'], $data['seo_plugins']) as $name => $active) : ?>
                    <li>
                        <span class="stvh-badge <?php echo $active ? 'stvh-badge--ok' : ''; ?>">
                            <?php echo $active ? esc_html__('فعال', 'signteb-video-hub') : esc_html__('غیرفعال', 'signteb-video-hub'); ?>
                        </span>
                        <?php echo esc_html($name); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="stvh-panel__foot">
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
    </div>
</div>

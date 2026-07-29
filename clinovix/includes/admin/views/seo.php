<?php
/**
 * SEO Intelligence Center view.
 *
 * @var array<int,object>   $questions
 * @var array<string,int>   $keywords
 * @var array<string,int>   $topics
 * @var string              $nonce
 * @var string|false        $cached
 *
 * @package Clinovix
 */

if (! defined('ABSPATH')) {
    exit;
}

$kw_max = $keywords ? max($keywords) : 1;
?>
<div class="wrap clx-admin" dir="rtl">
    <?php \Clinovix\Admin\PageHeader::render(
        __('مرکز هوش سئو', 'clinovix'),
        __('تحلیل خودکار گفتگوهای بیماران برای کشف فرصت‌های محتوایی.', 'clinovix')
    ); ?>

    <div class="clx-seo-ai" data-nonce="<?php echo esc_attr($nonce); ?>">
        <div class="clx-seo-ai-head">
            <div>
                <strong><?php esc_html_e('تولید ایده با هوش مصنوعی', 'clinovix'); ?></strong>
                <p class="description"><?php esc_html_e('عنوان مقاله، سؤالات متداول و توصیه‌های سئو بر اساس همین داده‌ها.', 'clinovix'); ?></p>
            </div>
            <button type="button" class="button button-primary" id="clx-seo-gen"><?php esc_html_e('تولید ایده', 'clinovix'); ?></button>
        </div>
        <pre class="clx-seo-ideas" id="clx-seo-ideas"><?php echo $cached ? esc_html((string) $cached) : ''; ?></pre>
    </div>

    <div class="clx-seo-grid">
        <div class="clx-seo-col">
            <h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'clinovix'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('پرسش', 'clinovix'); ?></th><th style="width:70px"><?php esc_html_e('تعداد', 'clinovix'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($questions)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('داده‌ای نیست.', 'clinovix'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($questions as $q) : ?>
                        <tr><td><?php echo esc_html($q->q); ?></td><td><?php echo esc_html(number_format_i18n($q->c)); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('موضوعات داغ (خدمات)', 'clinovix'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('خدمت', 'clinovix'); ?></th><th style="width:70px"><?php esc_html_e('اشاره', 'clinovix'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($topics)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('داده‌ای نیست.', 'clinovix'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($topics as $name => $count) : ?>
                        <tr><td><?php echo esc_html($name); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="clx-seo-col">
            <h2><?php esc_html_e('کلمات کلیدی پرتکرار', 'clinovix'); ?></h2>
            <p class="description"><?php esc_html_e('اندازه‌ی هر کلمه با میزان تکرار آن متناسب است — سرنخ محتوای هدف.', 'clinovix'); ?></p>
            <div class="clx-kw-cloud">
                <?php if (empty($keywords)) : ?>
                    <span class="description"><?php esc_html_e('هنوز کلمه‌ی کلیدی کافی استخراج نشده است.', 'clinovix'); ?></span>
                <?php else : ?>
                    <?php foreach ($keywords as $term => $count) :
                        $size = 12 + (int) round(($count / $kw_max) * 16);
                        ?>
                        <span class="clx-kw" style="font-size:<?php echo esc_attr($size); ?>px" title="<?php echo esc_attr($count); ?>"><?php echo esc_html($term); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

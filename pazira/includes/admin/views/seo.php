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
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

$kw_max = $keywords ? max($keywords) : 1;
?>
<div class="wrap pzr-admin" dir="rtl">
    <?php \Pazira\Admin\PageHeader::render(
        __('مرکز هوش سئو', 'pazira'),
        __('تحلیل خودکار گفتگوهای بیماران برای کشف فرصت‌های محتوایی.', 'pazira')
    ); ?>

    <div class="pzr-seo-ai" data-nonce="<?php echo esc_attr($nonce); ?>">
        <div class="pzr-seo-ai-head">
            <div>
                <strong><?php esc_html_e('تولید ایده با هوش مصنوعی', 'pazira'); ?></strong>
                <p class="description"><?php esc_html_e('عنوان مقاله، سؤالات متداول و توصیه‌های سئو بر اساس همین داده‌ها.', 'pazira'); ?></p>
            </div>
            <button type="button" class="button button-primary" id="pzr-seo-gen"><?php esc_html_e('تولید ایده', 'pazira'); ?></button>
        </div>
        <pre class="pzr-seo-ideas" id="pzr-seo-ideas"><?php echo $cached ? esc_html((string) $cached) : ''; ?></pre>
    </div>

    <div class="pzr-seo-grid">
        <div class="pzr-seo-col">
            <h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'pazira'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('پرسش', 'pazira'); ?></th><th style="width:70px"><?php esc_html_e('تعداد', 'pazira'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($questions)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('داده‌ای نیست.', 'pazira'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($questions as $q) : ?>
                        <tr><td><?php echo esc_html($q->q); ?></td><td><?php echo esc_html(number_format_i18n($q->c)); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('موضوعات داغ (خدمات)', 'pazira'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('خدمت', 'pazira'); ?></th><th style="width:70px"><?php esc_html_e('اشاره', 'pazira'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($topics)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('داده‌ای نیست.', 'pazira'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($topics as $name => $count) : ?>
                        <tr><td><?php echo esc_html($name); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pzr-seo-col">
            <h2><?php esc_html_e('کلمات کلیدی پرتکرار', 'pazira'); ?></h2>
            <p class="description"><?php esc_html_e('اندازه‌ی هر کلمه با میزان تکرار آن متناسب است — سرنخ محتوای هدف.', 'pazira'); ?></p>
            <div class="pzr-kw-cloud">
                <?php if (empty($keywords)) : ?>
                    <span class="description"><?php esc_html_e('هنوز کلمه‌ی کلیدی کافی استخراج نشده است.', 'pazira'); ?></span>
                <?php else : ?>
                    <?php foreach ($keywords as $term => $count) :
                        $size = 12 + (int) round(($count / $kw_max) * 16);
                        ?>
                        <span class="pzr-kw" style="font-size:<?php echo esc_attr($size); ?>px" title="<?php echo esc_attr($count); ?>"><?php echo esc_html($term); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

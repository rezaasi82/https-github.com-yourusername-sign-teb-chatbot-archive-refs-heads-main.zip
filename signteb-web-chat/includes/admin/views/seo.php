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
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$kw_max = $keywords ? max($keywords) : 1;
?>
<div class="wrap swc-admin" dir="rtl">
    <h1><?php esc_html_e('مرکز هوش سئو', 'signteb-web-chat'); ?></h1>
    <p class="description"><?php esc_html_e('تحلیل خودکار گفتگوهای بیماران برای کشف فرصت‌های محتوایی و سئو (۳۰ روز اخیر).', 'signteb-web-chat'); ?></p>

    <div class="swc-seo-ai" data-nonce="<?php echo esc_attr($nonce); ?>">
        <div class="swc-seo-ai-head">
            <div>
                <strong><?php esc_html_e('تولید ایده با هوش مصنوعی', 'signteb-web-chat'); ?></strong>
                <p class="description"><?php esc_html_e('عنوان مقاله، سؤالات متداول و توصیه‌های سئو بر اساس همین داده‌ها.', 'signteb-web-chat'); ?></p>
            </div>
            <button type="button" class="button button-primary" id="swc-seo-gen"><?php esc_html_e('تولید ایده', 'signteb-web-chat'); ?></button>
        </div>
        <pre class="swc-seo-ideas" id="swc-seo-ideas"><?php echo $cached ? esc_html((string) $cached) : ''; ?></pre>
    </div>

    <div class="swc-seo-grid">
        <div class="swc-seo-col">
            <h2><?php esc_html_e('پرتکرارترین پرسش‌ها', 'signteb-web-chat'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('پرسش', 'signteb-web-chat'); ?></th><th style="width:70px"><?php esc_html_e('تعداد', 'signteb-web-chat'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($questions)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('داده‌ای نیست.', 'signteb-web-chat'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($questions as $q) : ?>
                        <tr><td><?php echo esc_html($q->q); ?></td><td><?php echo esc_html(number_format_i18n($q->c)); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('موضوعات داغ (خدمات)', 'signteb-web-chat'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('خدمت', 'signteb-web-chat'); ?></th><th style="width:70px"><?php esc_html_e('اشاره', 'signteb-web-chat'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($topics)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('داده‌ای نیست.', 'signteb-web-chat'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($topics as $name => $count) : ?>
                        <tr><td><?php echo esc_html($name); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="swc-seo-col">
            <h2><?php esc_html_e('کلمات کلیدی پرتکرار', 'signteb-web-chat'); ?></h2>
            <p class="description"><?php esc_html_e('اندازه‌ی هر کلمه با میزان تکرار آن متناسب است — سرنخ محتوای هدف.', 'signteb-web-chat'); ?></p>
            <div class="swc-kw-cloud">
                <?php if (empty($keywords)) : ?>
                    <span class="description"><?php esc_html_e('هنوز کلمه‌ی کلیدی کافی استخراج نشده است.', 'signteb-web-chat'); ?></span>
                <?php else : ?>
                    <?php foreach ($keywords as $term => $count) :
                        $size = 12 + (int) round(($count / $kw_max) * 16);
                        ?>
                        <span class="swc-kw" style="font-size:<?php echo esc_attr($size); ?>px" title="<?php echo esc_attr($count); ?>"><?php echo esc_html($term); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Per-video editor panel.
 *
 * @var array<string,mixed> $data Provided by VideoMetaBox::render().
 *
 * @package SignTeb\VideoHub
 */

if (! defined('ABSPATH')) {
    exit;
}

$post_id = (int) $data['post_id'];
?>
<div class="stvh-metabox" data-video-id="<?php echo esc_attr((string) $post_id); ?>">

    <div class="stvh-metabox__row">
        <?php if ($data['thumbnail'] !== '') : ?>
            <img class="stvh-metabox__thumb" src="<?php echo esc_url((string) $data['thumbnail']); ?>" alt="" referrerpolicy="no-referrer" width="200" height="113">
        <?php endif; ?>

        <table class="stvh-metabox__table">
            <tbody>
            <tr>
                <th><?php esc_html_e('منبع', 'signteb-video-hub'); ?></th>
                <td>
                    <?php echo esc_html($data['source'] !== '' ? (string) $data['source'] : '—'); ?>
                    <?php if ($data['source_url'] !== '') : ?>
                        — <a href="<?php echo esc_url((string) $data['source_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('مشاهده در منبع', 'signteb-video-hub'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('شناسه', 'signteb-video-hub'); ?></th>
                <td dir="ltr"><code><?php echo esc_html((string) $data['source_id']); ?></code></td>
            </tr>
            <tr>
                <th><?php esc_html_e('مدت', 'signteb-video-hub'); ?></th>
                <td><?php echo esc_html($data['duration'] !== '' ? (string) $data['duration'] : '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('کد نمایش', 'signteb-video-hub'); ?></th>
                <td dir="ltr"><code><?php echo esc_html((string) $data['embed']); ?></code></td>
            </tr>
            <?php if ($data['indexed_at'] !== '') : ?>
                <tr>
                    <th><?php esc_html_e('آخرین ارسال به گوگل', 'signteb-video-hub'); ?></th>
                    <td><?php echo esc_html((string) $data['indexed_at']); ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="stvh-metabox__actions">
        <?php if (! $data['ai_ready']) : ?>
            <p class="stvh-muted">
                <?php esc_html_e('برای تولید محتوا، هوش مصنوعی را در تنظیمات فعال کرده و کلید API را وارد کنید.', 'signteb-video-hub'); ?>
            </p>
        <?php else : ?>
            <button type="button" class="button" data-stvh-generate="summary"><?php esc_html_e('تولید خلاصه و سوالات', 'signteb-video-hub'); ?></button>
            <button type="button" class="button" data-stvh-generate="links"><?php esc_html_e('تولید لینک داخلی', 'signteb-video-hub'); ?></button>
            <button type="button" class="button" data-stvh-generate="article"><?php esc_html_e('پیشنهاد مقاله', 'signteb-video-hub'); ?></button>
        <?php endif; ?>

        <?php if ($data['article'] !== '') : ?>
            <button type="button" class="button button-secondary" data-stvh-publish-article><?php esc_html_e('ساخت پیش‌نویس مقاله', 'signteb-video-hub'); ?></button>
        <?php endif; ?>

        <button type="button" class="button" data-stvh-repair><?php esc_html_e('بازسازی نامک، تصویر و متن', 'signteb-video-hub'); ?></button>
        <button type="button" class="button" data-stvh-index-ping><?php esc_html_e('ارسال به ایندکس گوگل', 'signteb-video-hub'); ?></button>

        <span class="stvh-feedback" data-stvh-feedback role="status" aria-live="polite"></span>
    </div>

    <?php if ($data['summary'] !== '') : ?>
        <div class="stvh-metabox__section">
            <h4><?php esc_html_e('خلاصه', 'signteb-video-hub'); ?></h4>
            <p><?php echo esc_html((string) $data['summary']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($data['keypoints'] !== []) : ?>
        <div class="stvh-metabox__section">
            <h4><?php esc_html_e('نکات مهم', 'signteb-video-hub'); ?></h4>
            <ul>
                <?php foreach ($data['keypoints'] as $point) : ?>
                    <li><?php echo esc_html((string) $point); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($data['faq'] !== []) : ?>
        <div class="stvh-metabox__section">
            <h4><?php esc_html_e('سوالات متداول', 'signteb-video-hub'); ?></h4>
            <?php foreach ($data['faq'] as $item) : ?>
                <p><strong><?php echo esc_html((string) $item['q']); ?></strong><br><?php echo esc_html((string) $item['a']); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($data['links'] !== []) : ?>
        <div class="stvh-metabox__section">
            <h4><?php esc_html_e('لینک‌های داخلی', 'signteb-video-hub'); ?></h4>
            <ul>
                <?php foreach ($data['links'] as $link) : ?>
                    <li><a href="<?php echo esc_url((string) $link['url']); ?>"><?php echo esc_html((string) $link['anchor']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($data['article'] !== '') : ?>
        <div class="stvh-metabox__section">
            <h4><?php esc_html_e('پیش‌نویس مقاله پیشنهادی', 'signteb-video-hub'); ?></h4>
            <div class="stvh-metabox__article">
                <?php echo wp_kses_post((string) $data['article']); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

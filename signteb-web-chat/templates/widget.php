<?php
/**
 * Frontend widget markup. White-label: title, avatar, colors, footer all come
 * from settings, with no hardcoded branding.
 *
 * @var array $config Provided by SWC_Widget::render().
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$welcome = $config['within_hours'] ? $config['welcome'] : ($config['offhours'] ?: $config['welcome']);
$title   = $config['bot_name'] !== '' ? $config['bot_name'] : __('دستیار هوشمند', 'signteb-web-chat');
?>
<div id="swc-root"
     class="swc-root"
     dir="<?php echo esc_attr($config['direction']); ?>"
     style="--swc-bg: <?php echo esc_attr($config['widget_color']); ?>; --swc-accent: <?php echo esc_attr($config['accent_color']); ?>;"
     data-booking-url="<?php echo esc_url($config['booking_url']); ?>"
     data-whatsapp="<?php echo esc_attr($config['whatsapp']); ?>"
     data-phone="<?php echo esc_attr($config['phone']); ?>">

    <button type="button" class="swc-launcher" aria-label="<?php esc_attr_e('باز کردن گفتگو', 'signteb-web-chat'); ?>">
        <?php if ($config['avatar_url'] !== '') : ?>
            <img class="swc-launcher-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
        <?php else : ?>
            <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 3C6.48 3 2 6.94 2 11.5c0 2.3 1.17 4.37 3.06 5.86L4 21l4.2-1.6c1.17.34 2.45.5 3.8.5 5.52 0 10-3.94 10-8.5S17.52 3 12 3z"/>
            </svg>
        <?php endif; ?>
    </button>

    <div class="swc-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr($title); ?>" hidden>
        <div class="swc-header">
            <span class="swc-header-id">
                <?php if ($config['avatar_url'] !== '') : ?>
                    <img class="swc-header-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
                <?php endif; ?>
                <span class="swc-title"><?php echo esc_html($title); ?></span>
            </span>
            <button type="button" class="swc-close" aria-label="<?php esc_attr_e('بستن', 'signteb-web-chat'); ?>">&times;</button>
        </div>

        <div class="swc-messages" aria-live="polite">
            <?php if ($welcome !== '') : ?>
                <div class="swc-msg swc-msg-bot"><?php echo esc_html($welcome); ?></div>
            <?php endif; ?>
        </div>

        <?php if (! empty($config['quick_replies'])) : ?>
            <div class="swc-quick">
                <?php foreach ($config['quick_replies'] as $qr) : ?>
                    <button type="button" class="swc-quick-reply"><?php echo esc_html($qr); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="swc-form" autocomplete="off">
            <input type="text" class="swc-input" name="message"
                   placeholder="<?php esc_attr_e('پیام خود را بنویسید…', 'signteb-web-chat'); ?>"
                   aria-label="<?php esc_attr_e('متن پیام', 'signteb-web-chat'); ?>" maxlength="2000" required>
            <button type="submit" class="swc-send" aria-label="<?php esc_attr_e('ارسال', 'signteb-web-chat'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>
            </button>
        </form>

        <?php if (trim($config['brand_footer']) !== '') : ?>
            <div class="swc-footer"><?php echo esc_html($config['brand_footer']); ?></div>
        <?php endif; ?>
    </div>
</div>

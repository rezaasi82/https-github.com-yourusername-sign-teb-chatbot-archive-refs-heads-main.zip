<?php
/**
 * Frontend widget markup.
 *
 * @var array $config Provided by Widget::render().
 */

if (! defined('ABSPATH')) {
    exit;
}

$welcome = $config['within_hours'] ? $config['welcome'] : ($config['offhours'] ?: $config['welcome']);
$nap     = $config['nap'];
?>
<div id="stmc-chat-root"
     class="stmc-chat-root"
     dir="rtl"
     style="--stmc-bg: <?php echo esc_attr($config['widget_color']); ?>; --stmc-accent: <?php echo esc_attr($config['accent_color']); ?>;"
     data-booking-url="<?php echo esc_url($nap['booking_url']); ?>"
     data-whatsapp="<?php echo esc_attr($nap['whatsapp']); ?>"
     data-phone="<?php echo esc_attr($nap['phone']); ?>">

    <button type="button" class="stmc-chat-launcher" aria-label="<?php esc_attr_e('باز کردن گفتگو', 'signteb-ai-chat'); ?>">
        <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 3C6.48 3 2 6.94 2 11.5c0 2.3 1.17 4.37 3.06 5.86L4 21l4.2-1.6c1.17.34 2.45.5 3.8.5 5.52 0 10-3.94 10-8.5S17.52 3 12 3z"/>
        </svg>
    </button>

    <div class="stmc-chat-panel" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e('دستیار هوشمند', 'signteb-ai-chat'); ?>" hidden>
        <div class="stmc-chat-header">
            <span class="stmc-chat-title"><?php echo esc_html($nap['clinic_name'] ?: __('دستیار هوشمند', 'signteb-ai-chat')); ?></span>
            <button type="button" class="stmc-chat-close" aria-label="<?php esc_attr_e('بستن', 'signteb-ai-chat'); ?>">&times;</button>
        </div>

        <div class="stmc-chat-messages" aria-live="polite">
            <?php if ($welcome !== '') : ?>
                <div class="stmc-msg stmc-msg-bot"><?php echo esc_html($welcome); ?></div>
            <?php endif; ?>
        </div>

        <?php if (! empty($config['quick_replies'])) : ?>
            <div class="stmc-chat-quick">
                <?php foreach ($config['quick_replies'] as $qr) : ?>
                    <button type="button" class="stmc-quick-reply"><?php echo esc_html($qr); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="stmc-chat-form" autocomplete="off">
            <input type="text" class="stmc-chat-input" name="message"
                   placeholder="<?php esc_attr_e('پیام خود را بنویسید…', 'signteb-ai-chat'); ?>"
                   aria-label="<?php esc_attr_e('متن پیام', 'signteb-ai-chat'); ?>" maxlength="2000" required>
            <button type="submit" class="stmc-chat-send" aria-label="<?php esc_attr_e('ارسال', 'signteb-ai-chat'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>
            </button>
        </form>

        <div class="stmc-chat-footer">
            <?php esc_html_e('قدرت‌گرفته از SignTeb AI', 'signteb-ai-chat'); ?>
        </div>
    </div>
</div>

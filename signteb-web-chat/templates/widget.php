<?php
/**
 * Frontend widget markup. White-label + Medora AI redesign: premium AI icon,
 * lead-capture step, professional booking CTA and communication channels.
 *
 * @var array $config Provided by \Medora\Frontend\Widget::render().
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$welcome  = $config['within_hours'] ? $config['welcome'] : ($config['offhours'] ?: $config['welcome']);
$title    = $config['bot_name'] !== '' ? $config['bot_name'] : __('دستیار هوشمند', 'signteb-web-chat');
$channels = $config['channels'];
?>
<div id="swc-root"
     class="swc-root<?php echo $config['inline'] ? ' swc-inline swc-open' : ''; ?>"
     dir="<?php echo esc_attr($config['direction']); ?>"
     style="--swc-bg: <?php echo esc_attr($config['widget_color']); ?>; --swc-accent: <?php echo esc_attr($config['accent_color']); ?>;"
     data-inline="<?php echo $config['inline'] ? '1' : '0'; ?>"
     data-teaser-delay="<?php echo (int) $config['teaser_delay']; ?>"
     data-teaser-sound="<?php echo $config['teaser_sound'] ? '1' : '0'; ?>"
     data-booking-url="<?php echo esc_url($config['booking_url']); ?>"
     data-whatsapp="<?php echo esc_attr($config['whatsapp']); ?>"
     data-phone="<?php echo esc_attr($config['phone']); ?>"
     data-bale-url="<?php echo esc_url($config['bale_url']); ?>"
     data-branch="<?php echo esc_attr($config['branch']); ?>"
     data-lead-capture="<?php echo $config['lead_capture'] ? '1' : '0'; ?>"
     data-ch-booking="<?php echo $channels['booking'] ? '1' : '0'; ?>"
     data-ch-whatsapp="<?php echo $channels['whatsapp'] ? '1' : '0'; ?>"
     data-ch-call="<?php echo $channels['call'] ? '1' : '0'; ?>"
     data-ch-bale="<?php echo $channels['bale'] ? '1' : '0'; ?>">

    <?php if (! $config['inline'] && trim($config['teaser']) !== '') : ?>
        <div class="swc-teaser" role="status" hidden>
            <button type="button" class="swc-teaser-close" aria-label="<?php esc_attr_e('بستن پیام', 'signteb-web-chat'); ?>">&times;</button>
            <?php if ($config['avatar_url'] !== '') : ?>
                <img class="swc-teaser-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
            <?php endif; ?>
            <span class="swc-teaser-text"><?php echo esc_html($config['teaser']); ?></span>
        </div>
    <?php endif; ?>

    <button type="button" class="swc-launcher" aria-label="<?php esc_attr_e('باز کردن گفتگوی هوشمند', 'signteb-web-chat'); ?>">
        <span class="swc-launcher-pulse" aria-hidden="true"></span>
        <?php if ($config['avatar_url'] !== '') : ?>
            <img class="swc-launcher-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
        <?php else : ?>
            <svg class="swc-launcher-icon" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M16 4C9.4 4 4 8.7 4 14.5c0 3 1.5 5.7 3.9 7.6L6.6 27l4.9-2.2c1.4.4 2.9.7 4.5.7 6.6 0 12-4.7 12-10.5S22.6 4 16 4z"/>
                <path fill="var(--swc-accent)" d="M15 9h2v3h3v2h-3v3h-2v-3h-3v-2h3z"/>
                <circle cx="24.5" cy="8" r="1.6" fill="var(--swc-accent)"/>
                <circle cx="27" cy="11" r="1" fill="var(--swc-accent)"/>
            </svg>
        <?php endif; ?>
    </button>

    <div class="swc-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr($title); ?>"<?php echo $config['inline'] ? '' : ' hidden'; ?>>
        <div class="swc-header">
            <span class="swc-header-id">
                <?php if ($config['avatar_url'] !== '') : ?>
                    <img class="swc-header-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
                <?php else : ?>
                    <span class="swc-header-avatar swc-header-avatar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 3C7 3 3 6.5 3 11c0 2.3 1.1 4.3 2.9 5.7L5 21l4-1.7c1 .3 2 .4 3 .4 5 0 9-3.5 9-8s-4-8-9-8z"/></svg>
                    </span>
                <?php endif; ?>
                <span class="swc-header-text">
                    <span class="swc-title"><?php echo esc_html($title); ?></span>
                    <span class="swc-status"><span class="swc-status-dot"></span><?php esc_html_e('آنلاین', 'signteb-web-chat'); ?></span>
                </span>
            </span>
            <button type="button" class="swc-close" aria-label="<?php esc_attr_e('بستن', 'signteb-web-chat'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 6.3 17.7 4.9 16.3 11.2 10 4.9 3.7 6.3 2.3l4.3 4.3L16.9 4.3z"/></svg>
            </button>
        </div>

        <div class="swc-body">
            <div class="swc-lead" hidden>
                <div class="swc-lead-card">
                    <div class="swc-lead-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="30" height="30"><path fill="currentColor" d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4 0-8 2-8 5v3h16v-3c0-3-4-5-8-5z"/></svg>
                    </div>
                    <h3 class="swc-lead-title"><?php esc_html_e('به گفتگو خوش آمدید 👋', 'signteb-web-chat'); ?></h3>
                    <p class="swc-lead-sub"><?php esc_html_e('برای شروع، لطفاً نام و شماره موبایل خود را وارد کنید تا بهتر راهنمایی‌تان کنیم.', 'signteb-web-chat'); ?></p>

                    <label class="swc-lead-label"><?php esc_html_e('نام و نام خانوادگی', 'signteb-web-chat'); ?></label>
                    <input type="text" class="swc-lead-name" autocomplete="name" placeholder="<?php esc_attr_e('مثلاً محمد احمدی', 'signteb-web-chat'); ?>">

                    <label class="swc-lead-label"><?php esc_html_e('شماره موبایل', 'signteb-web-chat'); ?></label>
                    <input type="tel" class="swc-lead-phone" inputmode="tel" autocomplete="tel" placeholder="09121234567">

                    <p class="swc-lead-error" role="alert" hidden></p>
                    <button type="button" class="swc-lead-start"><?php esc_html_e('شروع گفتگو', 'signteb-web-chat'); ?></button>
                    <button type="button" class="swc-lead-skip"><?php esc_html_e('فعلاً رد کن', 'signteb-web-chat'); ?></button>
                </div>
            </div>

            <div class="swc-messages" aria-live="polite">
                <?php if ($welcome !== '') : ?>
                    <div class="swc-msg swc-msg-bot"><?php echo esc_html($welcome); ?></div>
                <?php endif; ?>
            </div>
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
            <button type="submit" class="swc-send" aria-label="<?php esc_attr_e('ارسال پیام', 'signteb-web-chat'); ?>">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3 10l12 2-12 2z"/></svg>
            </button>
        </form>

        <?php if (trim($config['brand_footer']) !== '') : ?>
            <div class="swc-footer"><?php echo esc_html($config['brand_footer']); ?></div>
        <?php endif; ?>
    </div>
</div>

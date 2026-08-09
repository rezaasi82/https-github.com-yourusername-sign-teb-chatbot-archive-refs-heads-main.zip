<?php
/**
 * Frontend widget markup. White-label + Pezhkam redesign: premium AI icon,
 * lead-capture step, professional booking CTA and communication channels.
 *
 * @var array $config Provided by Widget::render().
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

$welcome  = $config['within_hours'] ? $config['welcome'] : ($config['offhours'] ?: $config['welcome']);
$title    = $config['bot_name'] !== '' ? $config['bot_name'] : __('دستیار هوشمند', 'pezhkam');
$channels = $config['channels'];
?>
<div id="pzk-root"
     class="pzk-root<?php echo $config['inline'] ? ' pzk-inline pzk-open' : ''; ?>"
     dir="<?php echo esc_attr($config['direction']); ?>"
     style="--pzk-bg: <?php echo esc_attr($config['widget_color']); ?>; --pzk-accent: <?php echo esc_attr($config['accent_color']); ?>;"
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
        <div class="pzk-teaser" role="status" hidden>
            <button type="button" class="pzk-teaser-close" aria-label="<?php esc_attr_e('بستن پیام', 'pezhkam'); ?>">&times;</button>
            <?php if ($config['avatar_url'] !== '') : ?>
                <img class="pzk-teaser-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
            <?php endif; ?>
            <span class="pzk-teaser-text"><?php echo esc_html($config['teaser']); ?></span>
        </div>
    <?php endif; ?>

    <button type="button" class="pzk-launcher" aria-label="<?php esc_attr_e('باز کردن گفتگوی هوشمند', 'pezhkam'); ?>">
        <span class="pzk-launcher-pulse" aria-hidden="true"></span>
        <?php if ($config['avatar_url'] !== '') : ?>
            <img class="pzk-launcher-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
        <?php else : ?>
            <svg class="pzk-launcher-icon" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M16 4C9.4 4 4 8.7 4 14.5c0 3 1.5 5.7 3.9 7.6L6.6 27l4.9-2.2c1.4.4 2.9.7 4.5.7 6.6 0 12-4.7 12-10.5S22.6 4 16 4z"/>
                <path fill="var(--pzk-accent)" d="M15 9h2v3h3v2h-3v3h-2v-3h-3v-2h3z"/>
                <circle cx="24.5" cy="8" r="1.6" fill="var(--pzk-accent)"/>
                <circle cx="27" cy="11" r="1" fill="var(--pzk-accent)"/>
            </svg>
        <?php endif; ?>
    </button>

    <div class="pzk-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr($title); ?>"<?php echo $config['inline'] ? '' : ' hidden'; ?>>
        <div class="pzk-header">
            <span class="pzk-header-id">
                <?php if ($config['avatar_url'] !== '') : ?>
                    <img class="pzk-header-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
                <?php else : ?>
                    <span class="pzk-header-avatar pzk-header-avatar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 3C7 3 3 6.5 3 11c0 2.3 1.1 4.3 2.9 5.7L5 21l4-1.7c1 .3 2 .4 3 .4 5 0 9-3.5 9-8s-4-8-9-8z"/></svg>
                    </span>
                <?php endif; ?>
                <span class="pzk-header-text">
                    <span class="pzk-title"><?php echo esc_html($title); ?></span>
                    <span class="pzk-status"><span class="pzk-status-dot"></span><?php esc_html_e('آنلاین', 'pezhkam'); ?></span>
                </span>
            </span>
            <button type="button" class="pzk-close" aria-label="<?php esc_attr_e('بستن', 'pezhkam'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 6.3 17.7 4.9 16.3 11.2 10 4.9 3.7 6.3 2.3l4.3 4.3L16.9 4.3z"/></svg>
            </button>
        </div>

        <div class="pzk-body">
            <div class="pzk-lead" hidden>
                <div class="pzk-lead-card">
                    <div class="pzk-lead-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="30" height="30"><path fill="currentColor" d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4 0-8 2-8 5v3h16v-3c0-3-4-5-8-5z"/></svg>
                    </div>
                    <h3 class="pzk-lead-title"><?php esc_html_e('به گفتگو خوش آمدید', 'pezhkam'); ?></h3>
                    <p class="pzk-lead-sub"><?php esc_html_e('برای شروع، لطفاً نام و شماره موبایل خود را وارد کنید تا بهتر راهنمایی‌تان کنیم.', 'pezhkam'); ?></p>

                    <label class="pzk-lead-label"><?php esc_html_e('نام و نام خانوادگی', 'pezhkam'); ?></label>
                    <input type="text" class="pzk-lead-name" autocomplete="name" placeholder="<?php esc_attr_e('مثلاً محمد احمدی', 'pezhkam'); ?>">

                    <label class="pzk-lead-label"><?php esc_html_e('شماره موبایل', 'pezhkam'); ?></label>
                    <input type="tel" class="pzk-lead-phone" inputmode="tel" autocomplete="tel" placeholder="09121234567">

                    <p class="pzk-lead-error" role="alert" hidden></p>
                    <button type="button" class="pzk-lead-start"><?php esc_html_e('شروع گفتگو', 'pezhkam'); ?></button>
                    <button type="button" class="pzk-lead-skip"><?php esc_html_e('فعلاً رد کن', 'pezhkam'); ?></button>
                </div>
            </div>

            <div class="pzk-messages" aria-live="polite">
                <?php if ($welcome !== '') : ?>
                    <div class="pzk-msg pzk-msg-bot"><?php echo esc_html($welcome); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (! empty($config['quick_replies'])) : ?>
            <div class="pzk-quick">
                <?php foreach ($config['quick_replies'] as $qr) : ?>
                    <button type="button" class="pzk-quick-reply"><?php echo esc_html($qr); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="pzk-form" autocomplete="off">
            <input type="text" class="pzk-input" name="message"
                   placeholder="<?php esc_attr_e('پیام خود را بنویسید…', 'pezhkam'); ?>"
                   aria-label="<?php esc_attr_e('متن پیام', 'pezhkam'); ?>" maxlength="2000" required>
            <button type="submit" class="pzk-send" aria-label="<?php esc_attr_e('ارسال پیام', 'pezhkam'); ?>">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3 10l12 2-12 2z"/></svg>
            </button>
        </form>

        <?php if (trim($config['brand_footer']) !== '') : ?>
            <div class="pzk-footer"><?php echo esc_html($config['brand_footer']); ?></div>
        <?php endif; ?>
    </div>
</div>

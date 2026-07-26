<?php
/**
 * Frontend widget markup. White-label + Pazira redesign: premium AI icon,
 * lead-capture step, professional booking CTA and communication channels.
 *
 * @var array $config Provided by Widget::render().
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

$welcome  = $config['within_hours'] ? $config['welcome'] : ($config['offhours'] ?: $config['welcome']);
$title    = $config['bot_name'] !== '' ? $config['bot_name'] : __('دستیار هوشمند', 'pazira');
$channels = $config['channels'];
?>
<div id="pzr-root"
     class="pzr-root<?php echo $config['inline'] ? ' pzr-inline pzr-open' : ''; ?>"
     dir="<?php echo esc_attr($config['direction']); ?>"
     style="--pzr-bg: <?php echo esc_attr($config['widget_color']); ?>; --pzr-accent: <?php echo esc_attr($config['accent_color']); ?>;"
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
        <div class="pzr-teaser" role="status" hidden>
            <button type="button" class="pzr-teaser-close" aria-label="<?php esc_attr_e('بستن پیام', 'pazira'); ?>">&times;</button>
            <?php if ($config['avatar_url'] !== '') : ?>
                <img class="pzr-teaser-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
            <?php endif; ?>
            <span class="pzr-teaser-text"><?php echo esc_html($config['teaser']); ?></span>
        </div>
    <?php endif; ?>

    <button type="button" class="pzr-launcher" aria-label="<?php esc_attr_e('باز کردن گفتگوی هوشمند', 'pazira'); ?>">
        <span class="pzr-launcher-pulse" aria-hidden="true"></span>
        <?php if ($config['avatar_url'] !== '') : ?>
            <img class="pzr-launcher-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
        <?php else : ?>
            <svg class="pzr-launcher-icon" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M16 4C9.4 4 4 8.7 4 14.5c0 3 1.5 5.7 3.9 7.6L6.6 27l4.9-2.2c1.4.4 2.9.7 4.5.7 6.6 0 12-4.7 12-10.5S22.6 4 16 4z"/>
                <path fill="var(--pzr-accent)" d="M15 9h2v3h3v2h-3v3h-2v-3h-3v-2h3z"/>
                <circle cx="24.5" cy="8" r="1.6" fill="var(--pzr-accent)"/>
                <circle cx="27" cy="11" r="1" fill="var(--pzr-accent)"/>
            </svg>
        <?php endif; ?>
    </button>

    <div class="pzr-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr($title); ?>"<?php echo $config['inline'] ? '' : ' hidden'; ?>>
        <div class="pzr-header">
            <span class="pzr-header-id">
                <?php if ($config['avatar_url'] !== '') : ?>
                    <img class="pzr-header-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
                <?php else : ?>
                    <span class="pzr-header-avatar pzr-header-avatar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 3C7 3 3 6.5 3 11c0 2.3 1.1 4.3 2.9 5.7L5 21l4-1.7c1 .3 2 .4 3 .4 5 0 9-3.5 9-8s-4-8-9-8z"/></svg>
                    </span>
                <?php endif; ?>
                <span class="pzr-header-text">
                    <span class="pzr-title"><?php echo esc_html($title); ?></span>
                    <span class="pzr-status"><span class="pzr-status-dot"></span><?php esc_html_e('آنلاین', 'pazira'); ?></span>
                </span>
            </span>
            <button type="button" class="pzr-close" aria-label="<?php esc_attr_e('بستن', 'pazira'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 6.3 17.7 4.9 16.3 11.2 10 4.9 3.7 6.3 2.3l4.3 4.3L16.9 4.3z"/></svg>
            </button>
        </div>

        <div class="pzr-body">
            <div class="pzr-lead" hidden>
                <div class="pzr-lead-card">
                    <div class="pzr-lead-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="30" height="30"><path fill="currentColor" d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4 0-8 2-8 5v3h16v-3c0-3-4-5-8-5z"/></svg>
                    </div>
                    <h3 class="pzr-lead-title"><?php esc_html_e('به گفتگو خوش آمدید 👋', 'pazira'); ?></h3>
                    <p class="pzr-lead-sub"><?php esc_html_e('برای شروع، لطفاً نام و شماره موبایل خود را وارد کنید تا بهتر راهنمایی‌تان کنیم.', 'pazira'); ?></p>

                    <label class="pzr-lead-label"><?php esc_html_e('نام و نام خانوادگی', 'pazira'); ?></label>
                    <input type="text" class="pzr-lead-name" autocomplete="name" placeholder="<?php esc_attr_e('مثلاً محمد احمدی', 'pazira'); ?>">

                    <label class="pzr-lead-label"><?php esc_html_e('شماره موبایل', 'pazira'); ?></label>
                    <input type="tel" class="pzr-lead-phone" inputmode="tel" autocomplete="tel" placeholder="09121234567">

                    <p class="pzr-lead-error" role="alert" hidden></p>
                    <button type="button" class="pzr-lead-start"><?php esc_html_e('شروع گفتگو', 'pazira'); ?></button>
                    <button type="button" class="pzr-lead-skip"><?php esc_html_e('فعلاً رد کن', 'pazira'); ?></button>
                </div>
            </div>

            <div class="pzr-messages" aria-live="polite">
                <?php if ($welcome !== '') : ?>
                    <div class="pzr-msg pzr-msg-bot"><?php echo esc_html($welcome); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (! empty($config['quick_replies'])) : ?>
            <div class="pzr-quick">
                <?php foreach ($config['quick_replies'] as $qr) : ?>
                    <button type="button" class="pzr-quick-reply"><?php echo esc_html($qr); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="pzr-form" autocomplete="off">
            <input type="text" class="pzr-input" name="message"
                   placeholder="<?php esc_attr_e('پیام خود را بنویسید…', 'pazira'); ?>"
                   aria-label="<?php esc_attr_e('متن پیام', 'pazira'); ?>" maxlength="2000" required>
            <button type="submit" class="pzr-send" aria-label="<?php esc_attr_e('ارسال پیام', 'pazira'); ?>">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3 10l12 2-12 2z"/></svg>
            </button>
        </form>

        <?php if (trim($config['brand_footer']) !== '') : ?>
            <div class="pzr-footer"><?php echo esc_html($config['brand_footer']); ?></div>
        <?php endif; ?>
    </div>
</div>

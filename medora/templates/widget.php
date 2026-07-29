<?php
/**
 * Frontend widget markup. White-label + Medora redesign: premium AI icon,
 * lead-capture step, professional booking CTA and communication channels.
 *
 * @var array $config Provided by Widget::render().
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

$welcome  = $config['within_hours'] ? $config['welcome'] : ($config['offhours'] ?: $config['welcome']);
$title    = $config['bot_name'] !== '' ? $config['bot_name'] : __('دستیار هوشمند', 'medora');
$channels = $config['channels'];
?>
<div id="mdr-root"
     class="mdr-root<?php echo $config['inline'] ? ' mdr-inline mdr-open' : ''; ?>"
     dir="<?php echo esc_attr($config['direction']); ?>"
     style="--mdr-bg: <?php echo esc_attr($config['widget_color']); ?>; --mdr-accent: <?php echo esc_attr($config['accent_color']); ?>;"
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
        <div class="mdr-teaser" role="status" hidden>
            <button type="button" class="mdr-teaser-close" aria-label="<?php esc_attr_e('بستن پیام', 'medora'); ?>">&times;</button>
            <?php if ($config['avatar_url'] !== '') : ?>
                <img class="mdr-teaser-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
            <?php endif; ?>
            <span class="mdr-teaser-text"><?php echo esc_html($config['teaser']); ?></span>
        </div>
    <?php endif; ?>

    <button type="button" class="mdr-launcher" aria-label="<?php esc_attr_e('باز کردن گفتگوی هوشمند', 'medora'); ?>">
        <span class="mdr-launcher-pulse" aria-hidden="true"></span>
        <?php if ($config['avatar_url'] !== '') : ?>
            <img class="mdr-launcher-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
        <?php else : ?>
            <svg class="mdr-launcher-icon" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M16 4C9.4 4 4 8.7 4 14.5c0 3 1.5 5.7 3.9 7.6L6.6 27l4.9-2.2c1.4.4 2.9.7 4.5.7 6.6 0 12-4.7 12-10.5S22.6 4 16 4z"/>
                <path fill="var(--mdr-accent)" d="M15 9h2v3h3v2h-3v3h-2v-3h-3v-2h3z"/>
                <circle cx="24.5" cy="8" r="1.6" fill="var(--mdr-accent)"/>
                <circle cx="27" cy="11" r="1" fill="var(--mdr-accent)"/>
            </svg>
        <?php endif; ?>
    </button>

    <div class="mdr-panel" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr($title); ?>"<?php echo $config['inline'] ? '' : ' hidden'; ?>>
        <div class="mdr-header">
            <span class="mdr-header-id">
                <?php if ($config['avatar_url'] !== '') : ?>
                    <img class="mdr-header-avatar" src="<?php echo esc_url($config['avatar_url']); ?>" alt="" />
                <?php else : ?>
                    <span class="mdr-header-avatar mdr-header-avatar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 3C7 3 3 6.5 3 11c0 2.3 1.1 4.3 2.9 5.7L5 21l4-1.7c1 .3 2 .4 3 .4 5 0 9-3.5 9-8s-4-8-9-8z"/></svg>
                    </span>
                <?php endif; ?>
                <span class="mdr-header-text">
                    <span class="mdr-title"><?php echo esc_html($title); ?></span>
                    <span class="mdr-status"><span class="mdr-status-dot"></span><?php esc_html_e('آنلاین', 'medora'); ?></span>
                </span>
            </span>
            <button type="button" class="mdr-close" aria-label="<?php esc_attr_e('بستن', 'medora'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 6.3 17.7 4.9 16.3 11.2 10 4.9 3.7 6.3 2.3l4.3 4.3L16.9 4.3z"/></svg>
            </button>
        </div>

        <div class="mdr-body">
            <div class="mdr-lead" hidden>
                <div class="mdr-lead-card">
                    <div class="mdr-lead-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="30" height="30"><path fill="currentColor" d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4 0-8 2-8 5v3h16v-3c0-3-4-5-8-5z"/></svg>
                    </div>
                    <h3 class="mdr-lead-title"><?php esc_html_e('به گفتگو خوش آمدید 👋', 'medora'); ?></h3>
                    <p class="mdr-lead-sub"><?php esc_html_e('برای شروع، لطفاً نام و شماره موبایل خود را وارد کنید تا بهتر راهنمایی‌تان کنیم.', 'medora'); ?></p>

                    <label class="mdr-lead-label"><?php esc_html_e('نام و نام خانوادگی', 'medora'); ?></label>
                    <input type="text" class="mdr-lead-name" autocomplete="name" placeholder="<?php esc_attr_e('مثلاً محمد احمدی', 'medora'); ?>">

                    <label class="mdr-lead-label"><?php esc_html_e('شماره موبایل', 'medora'); ?></label>
                    <input type="tel" class="mdr-lead-phone" inputmode="tel" autocomplete="tel" placeholder="09121234567">

                    <p class="mdr-lead-error" role="alert" hidden></p>
                    <button type="button" class="mdr-lead-start"><?php esc_html_e('شروع گفتگو', 'medora'); ?></button>
                    <button type="button" class="mdr-lead-skip"><?php esc_html_e('فعلاً رد کن', 'medora'); ?></button>
                </div>
            </div>

            <div class="mdr-messages" aria-live="polite">
                <?php if ($welcome !== '') : ?>
                    <div class="mdr-msg mdr-msg-bot"><?php echo esc_html($welcome); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (! empty($config['quick_replies'])) : ?>
            <div class="mdr-quick">
                <?php foreach ($config['quick_replies'] as $qr) : ?>
                    <button type="button" class="mdr-quick-reply"><?php echo esc_html($qr); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="mdr-form" autocomplete="off">
            <input type="text" class="mdr-input" name="message"
                   placeholder="<?php esc_attr_e('پیام خود را بنویسید…', 'medora'); ?>"
                   aria-label="<?php esc_attr_e('متن پیام', 'medora'); ?>" maxlength="2000" required>
            <button type="submit" class="mdr-send" aria-label="<?php esc_attr_e('ارسال پیام', 'medora'); ?>">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3 10l12 2-12 2z"/></svg>
            </button>
        </form>

        <?php if (trim($config['brand_footer']) !== '') : ?>
            <div class="mdr-footer"><?php echo esc_html($config['brand_footer']); ?></div>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Premium dashboard view (navy/gold glassmorphism).
 *
 * @var array         $integrity
 * @var array         $metrics
 * @var SWC_Settings  $settings
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$enabled      = $settings->is_enabled();
$provider     = strtoupper($settings->active_provider());
$has_key      = $settings->has_api_key();
$status_label = $enabled && $has_key
    ? __('فعال و آماده', 'signteb-web-chat')
    : ($enabled ? __('کلید API تنظیم نشده', 'signteb-web-chat') : __('غیرفعال', 'signteb-web-chat'));

$int_class = 'is-' . preg_replace('/[^a-z]/', '', $integrity['level']);
?>
<div class="wrap swc-dash" dir="rtl">
    <div class="swc-dash-inner">

        <div class="swc-vipcard">
            <div class="swc-vipcard-glow" aria-hidden="true"></div>
            <div class="swc-vipcard-body">
                <span class="swc-eyebrow">MEDORA AI</span>
                <h1><?php esc_html_e('به داشبورد هوشمند خوش آمدید', 'signteb-web-chat'); ?></h1>
                <p><?php echo esc_html(sprintf(
                    /* translators: 1: status, 2: provider */
                    __('وضعیت دستیار: %1$s · موتور هوش مصنوعی: %2$s', 'signteb-web-chat'),
                    $status_label,
                    $provider
                )); ?></p>
                <span class="swc-chip <?php echo $enabled && $has_key ? 'swc-chip-on' : 'swc-chip-off'; ?>">
                    <span class="swc-dot"></span><?php echo esc_html($status_label); ?>
                </span>
            </div>
        </div>

        <div class="swc-grid">
            <div class="swc-tile">
                <span class="swc-tile-ico">💬</span>
                <span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['active_chats']); ?>">0</span>
                <span class="swc-tile-label"><?php esc_html_e('گفتگوهای فعال (۲۴ ساعت)', 'signteb-web-chat'); ?></span>
            </div>
            <div class="swc-tile">
                <span class="swc-tile-ico">🎯</span>
                <span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['leads']); ?>">0</span>
                <span class="swc-tile-label"><?php esc_html_e('لیدهای تولیدشده (۳۰ روز)', 'signteb-web-chat'); ?></span>
            </div>
            <div class="swc-tile swc-tile-int <?php echo esc_attr($int_class); ?>">
                <span class="swc-tile-ico">🛡️</span>
                <span class="swc-int-badge" id="swc-int-badge"><?php echo esc_html($integrity['label']); ?></span>
                <span class="swc-tile-label"><?php esc_html_e('وضعیت یکپارچگی و امنیت', 'signteb-web-chat'); ?></span>
            </div>
        </div>

        <div class="swc-quicklinks">
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=provider')); ?>"><?php esc_html_e('تنظیمات هوش مصنوعی', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=conversations')); ?>"><?php esc_html_e('لیدها و مکالمات', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=integrations')); ?>"><?php esc_html_e('اتصال‌ها و خروجی', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=stats')); ?>"><?php esc_html_e('آمار', 'signteb-web-chat'); ?></a>
        </div>

    </div>
</div>

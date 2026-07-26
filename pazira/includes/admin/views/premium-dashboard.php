<?php
/**
 * Premium dashboard view (navy/gold glassmorphism).
 *
 * @var array         $integrity
 * @var array         $metrics
 * @var Settings  $settings
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

$enabled      = $settings->is_enabled();
$provider     = strtoupper($settings->active_provider());
$has_key      = $settings->has_api_key();
$status_label = $enabled && $has_key
    ? __('فعال و آماده', 'pazira')
    : ($enabled ? __('کلید API تنظیم نشده', 'pazira') : __('غیرفعال', 'pazira'));

$int_class = 'is-' . preg_replace('/[^a-z]/', '', $integrity['level']);
?>
<div class="wrap pzr-dash" dir="rtl">
    <div class="pzr-dash-inner">

        <div class="pzr-vipcard">
            <div class="pzr-vipcard-glow" aria-hidden="true"></div>
            <div class="pzr-vipcard-body">
                <span class="pzr-eyebrow">MEDORA AI</span>
                <h1><?php esc_html_e('به داشبورد هوشمند خوش آمدید', 'pazira'); ?></h1>
                <p><?php echo esc_html(sprintf(
                    /* translators: 1: status, 2: provider */
                    __('وضعیت دستیار: %1$s · موتور هوش مصنوعی: %2$s', 'pazira'),
                    $status_label,
                    $provider
                )); ?></p>
                <span class="pzr-chip <?php echo $enabled && $has_key ? 'pzr-chip-on' : 'pzr-chip-off'; ?>">
                    <span class="pzr-dot"></span><?php echo esc_html($status_label); ?>
                </span>
            </div>
        </div>

        <?php
        $range   = \Pazira\Core\Input::get_key('range', 'month');
        $base    = admin_url('admin.php?page=pzr-dashboard');
        $tabs    = ['day' => __('روزانه', 'pazira'), 'week' => __('هفتگی', 'pazira'), 'month' => __('ماهانه', 'pazira'), 'year' => __('سالانه', 'pazira')];
        $toman   = static fn($n) => number_format_i18n((int) $n) . ' ' . __('تومان', 'pazira');
        ?>
        <div class="pzr-range">
            <?php foreach ($tabs as $key => $lbl) : ?>
                <a class="pzr-range-btn <?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('range', $key, $base)); ?>"><?php echo esc_html($lbl); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="pzr-grid pzr-grid-4">
            <div class="pzr-tile"><span class="pzr-tile-ico">💬</span><span class="pzr-tile-num" data-count="<?php echo esc_attr($metrics['conversations']); ?>">0</span><span class="pzr-tile-label"><?php esc_html_e('کل گفتگوها', 'pazira'); ?></span></div>
            <div class="pzr-tile"><span class="pzr-tile-ico">🎯</span><span class="pzr-tile-num" data-count="<?php echo esc_attr($metrics['leads']); ?>">0</span><span class="pzr-tile-label"><?php esc_html_e('کل لیدها', 'pazira'); ?></span></div>
            <div class="pzr-tile"><span class="pzr-tile-ico">🔥</span><span class="pzr-tile-num" data-count="<?php echo esc_attr($metrics['hot_leads']); ?>">0</span><span class="pzr-tile-label"><?php esc_html_e('لیدهای داغ', 'pazira'); ?></span></div>
            <div class="pzr-tile"><span class="pzr-tile-ico">📈</span><span class="pzr-tile-num"><?php echo esc_html($metrics['conversion']); ?>٪</span><span class="pzr-tile-label"><?php esc_html_e('نرخ تبدیل', 'pazira'); ?></span></div>
            <div class="pzr-tile"><span class="pzr-tile-ico">📅</span><span class="pzr-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['booking']); ?>">0</span><span class="pzr-tile-label"><?php esc_html_e('کلیک رزرو', 'pazira'); ?></span></div>
            <div class="pzr-tile"><span class="pzr-tile-ico">💬</span><span class="pzr-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['whatsapp']); ?>">0</span><span class="pzr-tile-label"><?php esc_html_e('کلیک واتساپ', 'pazira'); ?></span></div>
            <div class="pzr-tile"><span class="pzr-tile-ico">📞</span><span class="pzr-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['call']); ?>">0</span><span class="pzr-tile-label"><?php esc_html_e('کلیک تماس', 'pazira'); ?></span></div>
            <div class="pzr-tile pzr-tile-int <?php echo esc_attr($int_class); ?>"><span class="pzr-tile-ico">🛡️</span><span class="pzr-int-badge" id="pzr-int-badge"><?php echo esc_html($integrity['label']); ?></span><span class="pzr-tile-label"><?php esc_html_e('یکپارچگی و امنیت', 'pazira'); ?></span></div>
        </div>

        <div class="pzr-revenue">
            <div class="pzr-revenue-body">
                <span class="pzr-revenue-label"><?php esc_html_e('برآورد درآمد تولیدشده توسط Pazira', 'pazira'); ?></span>
                <span class="pzr-revenue-num"><?php echo esc_html($toman($metrics['revenue'])); ?></span>
                <span class="pzr-revenue-formula"><?php esc_html_e('لیدها × نرخ تبدیل × میانگین قیمت خدمت', 'pazira'); ?></span>
            </div>
            <?php if ((int) $settings->get('avg_service_price', 0) === 0) : ?>
                <a class="pzr-revenue-cta" href="<?php echo esc_url(admin_url('admin.php?page=pzr-chat&tab=clinic')); ?>"><?php esc_html_e('تنظیم میانگین قیمت خدمت', 'pazira'); ?></a>
            <?php endif; ?>
        </div>

        <div class="pzr-funnel-wrap">
            <div class="pzr-funnel-head"><?php esc_html_e('قیف تبدیل بیمار (۳۰ روز)', 'pazira'); ?></div>
            <div class="pzr-funnel">
                <?php foreach (\Pazira\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                    <div class="pzr-funnel-step">
                        <b><?php echo esc_html(number_format_i18n($metrics['funnel'][$key] ?? 0)); ?></b>
                        <span><span class="pzr-funnel-dot" style="background:<?php echo esc_attr($def[1]); ?>"></span><?php echo esc_html($def[0]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pzr-quicklinks">
            <a class="pzr-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzr-chat&tab=provider')); ?>"><?php esc_html_e('تنظیمات هوش مصنوعی', 'pazira'); ?></a>
            <a class="pzr-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzr-board')); ?>"><?php esc_html_e('بورد CRM', 'pazira'); ?></a>
            <a class="pzr-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzr-chat&tab=conversations')); ?>"><?php esc_html_e('لیدها و مکالمات', 'pazira'); ?></a>
            <a class="pzr-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzr-chat&tab=integrations')); ?>"><?php esc_html_e('اتصال‌ها و خروجی', 'pazira'); ?></a>
            <a class="pzr-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzr-chat&tab=stats')); ?>"><?php esc_html_e('آمار', 'pazira'); ?></a>
        </div>

    </div>
</div>

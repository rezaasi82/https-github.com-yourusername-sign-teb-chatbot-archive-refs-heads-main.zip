<?php
/**
 * Premium dashboard view (navy/gold glassmorphism).
 *
 * @var array         $integrity
 * @var array         $metrics
 * @var Settings  $settings
 *
 * @package Clinovix
 */

if (! defined('ABSPATH')) {
    exit;
}

$enabled      = $settings->is_enabled();
$provider     = strtoupper($settings->active_provider());
$has_key      = $settings->has_api_key();
$status_label = $enabled && $has_key
    ? __('فعال و آماده', 'clinovix')
    : ($enabled ? __('کلید API تنظیم نشده', 'clinovix') : __('غیرفعال', 'clinovix'));

$int_class = 'is-' . preg_replace('/[^a-z]/', '', $integrity['level']);
?>
<div class="wrap clx-dash" dir="rtl">
    <div class="clx-dash-inner">

        <div class="clx-vipcard">
            <div class="clx-vipcard-glow" aria-hidden="true"></div>
            <div class="clx-vipcard-body">
                <span class="clx-eyebrow">CLINOVIX</span>
                <h1><?php esc_html_e('به داشبورد هوشمند خوش آمدید', 'clinovix'); ?></h1>
                <p><?php echo esc_html(sprintf(
                    /* translators: 1: status, 2: provider */
                    __('وضعیت دستیار: %1$s · موتور هوش مصنوعی: %2$s', 'clinovix'),
                    $status_label,
                    $provider
                )); ?></p>
                <span class="clx-chip <?php echo $enabled && $has_key ? 'clx-chip-on' : 'clx-chip-off'; ?>">
                    <span class="clx-dot"></span><?php echo esc_html($status_label); ?>
                </span>
            </div>
        </div>

        <?php
        $range   = \Clinovix\Core\Input::get_key('range', 'month');
        $base    = admin_url('admin.php?page=clx-dashboard');
        $tabs    = ['day' => __('روزانه', 'clinovix'), 'week' => __('هفتگی', 'clinovix'), 'month' => __('ماهانه', 'clinovix'), 'year' => __('سالانه', 'clinovix')];
        $toman   = static fn($n) => number_format_i18n((int) $n) . ' ' . __('تومان', 'clinovix');
        ?>
        <div class="clx-range">
            <?php foreach ($tabs as $key => $lbl) : ?>
                <a class="clx-range-btn <?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('range', $key, $base)); ?>"><?php echo esc_html($lbl); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="clx-grid clx-grid-4">
            <div class="clx-tile"><span class="clx-tile-ico">💬</span><span class="clx-tile-num" data-count="<?php echo esc_attr($metrics['conversations']); ?>">0</span><span class="clx-tile-label"><?php esc_html_e('کل گفتگوها', 'clinovix'); ?></span></div>
            <div class="clx-tile"><span class="clx-tile-ico">🎯</span><span class="clx-tile-num" data-count="<?php echo esc_attr($metrics['leads']); ?>">0</span><span class="clx-tile-label"><?php esc_html_e('کل لیدها', 'clinovix'); ?></span></div>
            <div class="clx-tile"><span class="clx-tile-ico">🔥</span><span class="clx-tile-num" data-count="<?php echo esc_attr($metrics['hot_leads']); ?>">0</span><span class="clx-tile-label"><?php esc_html_e('لیدهای داغ', 'clinovix'); ?></span></div>
            <div class="clx-tile"><span class="clx-tile-ico">📈</span><span class="clx-tile-num"><?php echo esc_html($metrics['conversion']); ?>٪</span><span class="clx-tile-label"><?php esc_html_e('نرخ تبدیل', 'clinovix'); ?></span></div>
            <div class="clx-tile"><span class="clx-tile-ico">📅</span><span class="clx-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['booking']); ?>">0</span><span class="clx-tile-label"><?php esc_html_e('کلیک رزرو', 'clinovix'); ?></span></div>
            <div class="clx-tile"><span class="clx-tile-ico">💬</span><span class="clx-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['whatsapp']); ?>">0</span><span class="clx-tile-label"><?php esc_html_e('کلیک واتساپ', 'clinovix'); ?></span></div>
            <div class="clx-tile"><span class="clx-tile-ico">📞</span><span class="clx-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['call']); ?>">0</span><span class="clx-tile-label"><?php esc_html_e('کلیک تماس', 'clinovix'); ?></span></div>
            <div class="clx-tile clx-tile-int <?php echo esc_attr($int_class); ?>"><span class="clx-tile-ico">🛡️</span><span class="clx-int-badge" id="clx-int-badge"><?php echo esc_html($integrity['label']); ?></span><span class="clx-tile-label"><?php esc_html_e('یکپارچگی و امنیت', 'clinovix'); ?></span></div>
        </div>

        <div class="clx-revenue">
            <div class="clx-revenue-body">
                <span class="clx-revenue-label"><?php esc_html_e('برآورد درآمد تولیدشده توسط Clinovix', 'clinovix'); ?></span>
                <span class="clx-revenue-num"><?php echo esc_html($toman($metrics['revenue'])); ?></span>
                <span class="clx-revenue-formula"><?php esc_html_e('لیدها × نرخ تبدیل × میانگین قیمت خدمت', 'clinovix'); ?></span>
            </div>
            <?php if ((int) $settings->get('avg_service_price', 0) === 0) : ?>
                <a class="clx-revenue-cta" href="<?php echo esc_url(admin_url('admin.php?page=clx-chat&tab=clinic')); ?>"><?php esc_html_e('تنظیم میانگین قیمت خدمت', 'clinovix'); ?></a>
            <?php endif; ?>
        </div>

        <div class="clx-funnel-wrap">
            <div class="clx-funnel-head"><?php esc_html_e('قیف تبدیل بیمار (۳۰ روز)', 'clinovix'); ?></div>
            <div class="clx-funnel">
                <?php foreach (\Clinovix\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                    <div class="clx-funnel-step">
                        <b><?php echo esc_html(number_format_i18n($metrics['funnel'][$key] ?? 0)); ?></b>
                        <span><span class="clx-funnel-dot" style="background:<?php echo esc_attr($def[1]); ?>"></span><?php echo esc_html($def[0]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="clx-quicklinks">
            <a class="clx-ql" href="<?php echo esc_url(admin_url('admin.php?page=clx-chat&tab=provider')); ?>"><?php esc_html_e('تنظیمات هوش مصنوعی', 'clinovix'); ?></a>
            <a class="clx-ql" href="<?php echo esc_url(admin_url('admin.php?page=clx-board')); ?>"><?php esc_html_e('بورد CRM', 'clinovix'); ?></a>
            <a class="clx-ql" href="<?php echo esc_url(admin_url('admin.php?page=clx-chat&tab=conversations')); ?>"><?php esc_html_e('لیدها و مکالمات', 'clinovix'); ?></a>
            <a class="clx-ql" href="<?php echo esc_url(admin_url('admin.php?page=clx-chat&tab=integrations')); ?>"><?php esc_html_e('اتصال‌ها و خروجی', 'clinovix'); ?></a>
            <a class="clx-ql" href="<?php echo esc_url(admin_url('admin.php?page=clx-chat&tab=stats')); ?>"><?php esc_html_e('آمار', 'clinovix'); ?></a>
        </div>

    </div>
</div>

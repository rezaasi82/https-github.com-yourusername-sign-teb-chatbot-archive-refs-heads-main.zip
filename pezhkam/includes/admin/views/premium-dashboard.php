<?php
/**
 * Premium dashboard view (navy/gold glassmorphism).
 *
 * @var array         $integrity
 * @var array         $metrics
 * @var Settings  $settings
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

$enabled      = $settings->is_enabled();
$provider     = strtoupper($settings->active_provider());
$has_key      = $settings->has_api_key();
$status_label = $enabled && $has_key
    ? __('فعال و آماده', 'pezhkam')
    : ($enabled ? __('کلید API تنظیم نشده', 'pezhkam') : __('غیرفعال', 'pezhkam'));

$int_class = 'is-' . preg_replace('/[^a-z]/', '', $integrity['level']);
?>
<div class="wrap pzk-dash" dir="rtl">
    <div class="pzk-dash-inner">

        <div class="pzk-vipcard">
            <div class="pzk-vipcard-glow" aria-hidden="true"></div>
            <div class="pzk-vipcard-body">
                <span class="pzk-eyebrow">PEZHKAM</span>
                <h1><?php esc_html_e('به داشبورد هوشمند خوش آمدید', 'pezhkam'); ?></h1>
                <p><?php echo esc_html(sprintf(
                    /* translators: 1: status, 2: provider */
                    __('وضعیت دستیار: %1$s · موتور هوش مصنوعی: %2$s', 'pezhkam'),
                    $status_label,
                    $provider
                )); ?></p>
                <span class="pzk-chip <?php echo $enabled && $has_key ? 'pzk-chip-on' : 'pzk-chip-off'; ?>">
                    <span class="pzk-dot"></span><?php echo esc_html($status_label); ?>
                </span>
            </div>
        </div>

        <?php
        $range   = \Pezhkam\Core\Input::get_key('range', 'month');
        $base    = admin_url('admin.php?page=pzk-dashboard');
        $tabs    = ['day' => __('روزانه', 'pezhkam'), 'week' => __('هفتگی', 'pezhkam'), 'month' => __('ماهانه', 'pezhkam'), 'year' => __('سالانه', 'pezhkam')];
        $toman   = static fn($n) => number_format_i18n((int) $n) . ' ' . __('تومان', 'pezhkam');
        ?>
        <div class="pzk-range">
            <?php foreach ($tabs as $key => $lbl) : ?>
                <a class="pzk-range-btn <?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('range', $key, $base)); ?>"><?php echo esc_html($lbl); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="pzk-grid pzk-grid-4">
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('chat'); ?></span><span class="pzk-tile-num" data-count="<?php echo esc_attr($metrics['conversations']); ?>">0</span><span class="pzk-tile-label"><?php esc_html_e('کل گفتگوها', 'pezhkam'); ?></span></div>
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('target'); ?></span><span class="pzk-tile-num" data-count="<?php echo esc_attr($metrics['leads']); ?>">0</span><span class="pzk-tile-label"><?php esc_html_e('کل لیدها', 'pezhkam'); ?></span></div>
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('flame'); ?></span><span class="pzk-tile-num" data-count="<?php echo esc_attr($metrics['hot_leads']); ?>">0</span><span class="pzk-tile-label"><?php esc_html_e('لیدهای داغ', 'pezhkam'); ?></span></div>
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('trending'); ?></span><span class="pzk-tile-num"><?php echo esc_html($metrics['conversion']); ?>٪</span><span class="pzk-tile-label"><?php esc_html_e('نرخ تبدیل', 'pezhkam'); ?></span></div>
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('calendar'); ?></span><span class="pzk-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['booking']); ?>">0</span><span class="pzk-tile-label"><?php esc_html_e('کلیک رزرو', 'pezhkam'); ?></span></div>
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('chat'); ?></span><span class="pzk-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['whatsapp']); ?>">0</span><span class="pzk-tile-label"><?php esc_html_e('کلیک واتساپ', 'pezhkam'); ?></span></div>
            <div class="pzk-tile"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('phone'); ?></span><span class="pzk-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['call']); ?>">0</span><span class="pzk-tile-label"><?php esc_html_e('کلیک تماس', 'pezhkam'); ?></span></div>
            <div class="pzk-tile pzk-tile-int <?php echo esc_attr($int_class); ?>"><span class="pzk-tile-ico"><?php echo \Pezhkam\Admin\Icon::svg('shield'); ?></span><span class="pzk-int-badge" id="pzk-int-badge"><?php echo esc_html($integrity['label']); ?></span><span class="pzk-tile-label"><?php esc_html_e('یکپارچگی و امنیت', 'pezhkam'); ?></span></div>
        </div>

        <div class="pzk-revenue">
            <div class="pzk-revenue-body">
                <span class="pzk-revenue-label"><?php esc_html_e('برآورد درآمد تولیدشده توسط Pezhkam', 'pezhkam'); ?></span>
                <span class="pzk-revenue-num"><?php echo esc_html($toman($metrics['revenue'])); ?></span>
                <span class="pzk-revenue-formula"><?php esc_html_e('لیدها × نرخ تبدیل × میانگین قیمت خدمت', 'pezhkam'); ?></span>
            </div>
            <?php if ((int) $settings->get('avg_service_price', 0) === 0) : ?>
                <a class="pzk-revenue-cta" href="<?php echo esc_url(admin_url('admin.php?page=pzk-chat&tab=clinic')); ?>"><?php esc_html_e('تنظیم میانگین قیمت خدمت', 'pezhkam'); ?></a>
            <?php endif; ?>
        </div>

        <div class="pzk-funnel-wrap">
            <div class="pzk-funnel-head"><?php esc_html_e('قیف تبدیل بیمار (۳۰ روز)', 'pezhkam'); ?></div>
            <div class="pzk-funnel">
                <?php foreach (\Pezhkam\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                    <div class="pzk-funnel-step">
                        <b><?php echo esc_html(number_format_i18n($metrics['funnel'][$key] ?? 0)); ?></b>
                        <span><span class="pzk-funnel-dot" style="background:<?php echo esc_attr($def[1]); ?>"></span><?php echo esc_html($def[0]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pzk-quicklinks">
            <a class="pzk-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzk-chat&tab=provider')); ?>"><?php esc_html_e('تنظیمات هوش مصنوعی', 'pezhkam'); ?></a>
            <a class="pzk-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzk-board')); ?>"><?php esc_html_e('بورد CRM', 'pezhkam'); ?></a>
            <a class="pzk-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzk-chat&tab=conversations')); ?>"><?php esc_html_e('لیدها و مکالمات', 'pezhkam'); ?></a>
            <a class="pzk-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzk-chat&tab=integrations')); ?>"><?php esc_html_e('اتصال‌ها و خروجی', 'pezhkam'); ?></a>
            <a class="pzk-ql" href="<?php echo esc_url(admin_url('admin.php?page=pzk-chat&tab=stats')); ?>"><?php esc_html_e('آمار', 'pezhkam'); ?></a>
        </div>

    </div>
</div>

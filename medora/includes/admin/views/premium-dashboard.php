<?php
/**
 * Premium dashboard view (navy/gold glassmorphism).
 *
 * @var array         $integrity
 * @var array         $metrics
 * @var Settings  $settings
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

$enabled      = $settings->is_enabled();
$provider     = strtoupper($settings->active_provider());
$has_key      = $settings->has_api_key();
$status_label = $enabled && $has_key
    ? __('فعال و آماده', 'medora')
    : ($enabled ? __('کلید API تنظیم نشده', 'medora') : __('غیرفعال', 'medora'));

$int_class = 'is-' . preg_replace('/[^a-z]/', '', $integrity['level']);
?>
<div class="wrap mdr-dash" dir="rtl">
    <div class="mdr-dash-inner">

        <div class="mdr-vipcard">
            <div class="mdr-vipcard-glow" aria-hidden="true"></div>
            <div class="mdr-vipcard-body">
                <span class="mdr-eyebrow">MEDORA</span>
                <h1><?php esc_html_e('به داشبورد هوشمند خوش آمدید', 'medora'); ?></h1>
                <p><?php echo esc_html(sprintf(
                    /* translators: 1: status, 2: provider */
                    __('وضعیت دستیار: %1$s · موتور هوش مصنوعی: %2$s', 'medora'),
                    $status_label,
                    $provider
                )); ?></p>
                <span class="mdr-chip <?php echo $enabled && $has_key ? 'mdr-chip-on' : 'mdr-chip-off'; ?>">
                    <span class="mdr-dot"></span><?php echo esc_html($status_label); ?>
                </span>
            </div>
        </div>

        <?php
        $range   = \Medora\Core\Input::get_key('range', 'month');
        $base    = admin_url('admin.php?page=mdr-dashboard');
        $tabs    = ['day' => __('روزانه', 'medora'), 'week' => __('هفتگی', 'medora'), 'month' => __('ماهانه', 'medora'), 'year' => __('سالانه', 'medora')];
        $toman   = static fn($n) => number_format_i18n((int) $n) . ' ' . __('تومان', 'medora');
        ?>
        <div class="mdr-range">
            <?php foreach ($tabs as $key => $lbl) : ?>
                <a class="mdr-range-btn <?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('range', $key, $base)); ?>"><?php echo esc_html($lbl); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="mdr-grid mdr-grid-4">
            <div class="mdr-tile"><span class="mdr-tile-ico">💬</span><span class="mdr-tile-num" data-count="<?php echo esc_attr($metrics['conversations']); ?>">0</span><span class="mdr-tile-label"><?php esc_html_e('کل گفتگوها', 'medora'); ?></span></div>
            <div class="mdr-tile"><span class="mdr-tile-ico">🎯</span><span class="mdr-tile-num" data-count="<?php echo esc_attr($metrics['leads']); ?>">0</span><span class="mdr-tile-label"><?php esc_html_e('کل لیدها', 'medora'); ?></span></div>
            <div class="mdr-tile"><span class="mdr-tile-ico">🔥</span><span class="mdr-tile-num" data-count="<?php echo esc_attr($metrics['hot_leads']); ?>">0</span><span class="mdr-tile-label"><?php esc_html_e('لیدهای داغ', 'medora'); ?></span></div>
            <div class="mdr-tile"><span class="mdr-tile-ico">📈</span><span class="mdr-tile-num"><?php echo esc_html($metrics['conversion']); ?>٪</span><span class="mdr-tile-label"><?php esc_html_e('نرخ تبدیل', 'medora'); ?></span></div>
            <div class="mdr-tile"><span class="mdr-tile-ico">📅</span><span class="mdr-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['booking']); ?>">0</span><span class="mdr-tile-label"><?php esc_html_e('کلیک رزرو', 'medora'); ?></span></div>
            <div class="mdr-tile"><span class="mdr-tile-ico">💬</span><span class="mdr-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['whatsapp']); ?>">0</span><span class="mdr-tile-label"><?php esc_html_e('کلیک واتساپ', 'medora'); ?></span></div>
            <div class="mdr-tile"><span class="mdr-tile-ico">📞</span><span class="mdr-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['call']); ?>">0</span><span class="mdr-tile-label"><?php esc_html_e('کلیک تماس', 'medora'); ?></span></div>
            <div class="mdr-tile mdr-tile-int <?php echo esc_attr($int_class); ?>"><span class="mdr-tile-ico">🛡️</span><span class="mdr-int-badge" id="mdr-int-badge"><?php echo esc_html($integrity['label']); ?></span><span class="mdr-tile-label"><?php esc_html_e('یکپارچگی و امنیت', 'medora'); ?></span></div>
        </div>

        <div class="mdr-revenue">
            <div class="mdr-revenue-body">
                <span class="mdr-revenue-label"><?php esc_html_e('برآورد درآمد تولیدشده توسط Medora', 'medora'); ?></span>
                <span class="mdr-revenue-num"><?php echo esc_html($toman($metrics['revenue'])); ?></span>
                <span class="mdr-revenue-formula"><?php esc_html_e('لیدها × نرخ تبدیل × میانگین قیمت خدمت', 'medora'); ?></span>
            </div>
            <?php if ((int) $settings->get('avg_service_price', 0) === 0) : ?>
                <a class="mdr-revenue-cta" href="<?php echo esc_url(admin_url('admin.php?page=mdr-chat&tab=clinic')); ?>"><?php esc_html_e('تنظیم میانگین قیمت خدمت', 'medora'); ?></a>
            <?php endif; ?>
        </div>

        <div class="mdr-funnel-wrap">
            <div class="mdr-funnel-head"><?php esc_html_e('قیف تبدیل بیمار (۳۰ روز)', 'medora'); ?></div>
            <div class="mdr-funnel">
                <?php foreach (\Medora\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                    <div class="mdr-funnel-step">
                        <b><?php echo esc_html(number_format_i18n($metrics['funnel'][$key] ?? 0)); ?></b>
                        <span><span class="mdr-funnel-dot" style="background:<?php echo esc_attr($def[1]); ?>"></span><?php echo esc_html($def[0]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mdr-quicklinks">
            <a class="mdr-ql" href="<?php echo esc_url(admin_url('admin.php?page=mdr-chat&tab=provider')); ?>"><?php esc_html_e('تنظیمات هوش مصنوعی', 'medora'); ?></a>
            <a class="mdr-ql" href="<?php echo esc_url(admin_url('admin.php?page=mdr-board')); ?>"><?php esc_html_e('بورد CRM', 'medora'); ?></a>
            <a class="mdr-ql" href="<?php echo esc_url(admin_url('admin.php?page=mdr-chat&tab=conversations')); ?>"><?php esc_html_e('لیدها و مکالمات', 'medora'); ?></a>
            <a class="mdr-ql" href="<?php echo esc_url(admin_url('admin.php?page=mdr-chat&tab=integrations')); ?>"><?php esc_html_e('اتصال‌ها و خروجی', 'medora'); ?></a>
            <a class="mdr-ql" href="<?php echo esc_url(admin_url('admin.php?page=mdr-chat&tab=stats')); ?>"><?php esc_html_e('آمار', 'medora'); ?></a>
        </div>

    </div>
</div>

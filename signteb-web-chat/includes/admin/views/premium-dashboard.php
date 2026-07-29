<?php
/**
 * Premium dashboard view (navy/gold glassmorphism).
 *
 * @var array         $integrity
 * @var array         $metrics
 * @var Settings  $settings
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
                <span class="swc-eyebrow">SIGNTEB</span>
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

        <?php
        $range   = \SignTeb\WebChat\Core\Input::get_key('range', 'month');
        $base    = admin_url('admin.php?page=swc-dashboard');
        $tabs    = ['day' => __('روزانه', 'signteb-web-chat'), 'week' => __('هفتگی', 'signteb-web-chat'), 'month' => __('ماهانه', 'signteb-web-chat'), 'year' => __('سالانه', 'signteb-web-chat')];
        $toman   = static fn($n) => number_format_i18n((int) $n) . ' ' . __('تومان', 'signteb-web-chat');
        ?>
        <div class="swc-range">
            <?php foreach ($tabs as $key => $lbl) : ?>
                <a class="swc-range-btn <?php echo $range === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('range', $key, $base)); ?>"><?php echo esc_html($lbl); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="swc-grid swc-grid-4">
            <div class="swc-tile"><span class="swc-tile-ico">💬</span><span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['conversations']); ?>">0</span><span class="swc-tile-label"><?php esc_html_e('کل گفتگوها', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile"><span class="swc-tile-ico">🎯</span><span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['leads']); ?>">0</span><span class="swc-tile-label"><?php esc_html_e('کل لیدها', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile"><span class="swc-tile-ico">🔥</span><span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['hot_leads']); ?>">0</span><span class="swc-tile-label"><?php esc_html_e('لیدهای داغ', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile"><span class="swc-tile-ico">📈</span><span class="swc-tile-num"><?php echo esc_html($metrics['conversion']); ?>٪</span><span class="swc-tile-label"><?php esc_html_e('نرخ تبدیل', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile"><span class="swc-tile-ico">📅</span><span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['booking']); ?>">0</span><span class="swc-tile-label"><?php esc_html_e('کلیک رزرو', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile"><span class="swc-tile-ico">💬</span><span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['whatsapp']); ?>">0</span><span class="swc-tile-label"><?php esc_html_e('کلیک واتساپ', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile"><span class="swc-tile-ico">📞</span><span class="swc-tile-num" data-count="<?php echo esc_attr($metrics['clicks']['call']); ?>">0</span><span class="swc-tile-label"><?php esc_html_e('کلیک تماس', 'signteb-web-chat'); ?></span></div>
            <div class="swc-tile swc-tile-int <?php echo esc_attr($int_class); ?>"><span class="swc-tile-ico">🛡️</span><span class="swc-int-badge" id="swc-int-badge"><?php echo esc_html($integrity['label']); ?></span><span class="swc-tile-label"><?php esc_html_e('یکپارچگی و امنیت', 'signteb-web-chat'); ?></span></div>
        </div>

        <div class="swc-revenue">
            <div class="swc-revenue-body">
                <span class="swc-revenue-label"><?php esc_html_e('برآورد درآمد تولیدشده توسط SignTeb Chat', 'signteb-web-chat'); ?></span>
                <span class="swc-revenue-num"><?php echo esc_html($toman($metrics['revenue'])); ?></span>
                <span class="swc-revenue-formula"><?php esc_html_e('لیدها × نرخ تبدیل × میانگین قیمت خدمت', 'signteb-web-chat'); ?></span>
            </div>
            <?php if ((int) $settings->get('avg_service_price', 0) === 0) : ?>
                <a class="swc-revenue-cta" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=clinic')); ?>"><?php esc_html_e('تنظیم میانگین قیمت خدمت', 'signteb-web-chat'); ?></a>
            <?php endif; ?>
        </div>

        <div class="swc-funnel-wrap">
            <div class="swc-funnel-head"><?php esc_html_e('قیف تبدیل بیمار (۳۰ روز)', 'signteb-web-chat'); ?></div>
            <div class="swc-funnel">
                <?php foreach (\SignTeb\WebChat\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                    <div class="swc-funnel-step">
                        <b><?php echo esc_html(number_format_i18n($metrics['funnel'][$key] ?? 0)); ?></b>
                        <span><span class="swc-funnel-dot" style="background:<?php echo esc_attr($def[1]); ?>"></span><?php echo esc_html($def[0]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="swc-quicklinks">
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=provider')); ?>"><?php esc_html_e('تنظیمات هوش مصنوعی', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-board')); ?>"><?php esc_html_e('بورد CRM', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=conversations')); ?>"><?php esc_html_e('لیدها و مکالمات', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=integrations')); ?>"><?php esc_html_e('اتصال‌ها و خروجی', 'signteb-web-chat'); ?></a>
            <a class="swc-ql" href="<?php echo esc_url(admin_url('admin.php?page=swc-chat&tab=stats')); ?>"><?php esc_html_e('آمار', 'signteb-web-chat'); ?></a>
        </div>

    </div>
</div>

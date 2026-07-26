<?php
/**
 * Single conversation transcript (rendered inside the Conversations tab).
 *
 * @var ?object           $conversation
 * @var array<int,object> $messages
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

$back = admin_url('admin.php?page=swc-chat&tab=conversations');
?>
<p>
    <strong><?php esc_html_e('مکالمه', 'signteb-web-chat'); ?> #<?php echo esc_html($conversation ? $conversation->id : 0); ?></strong>
    <a href="<?php echo esc_url($back); ?>" class="page-title-action"><?php esc_html_e('بازگشت', 'signteb-web-chat'); ?></a>
</p>

<?php if (! $conversation) : ?>
    <p><?php esc_html_e('مکالمه یافت نشد.', 'signteb-web-chat'); ?></p>
<?php else : ?>
    <p class="description">
        <?php echo esc_html(mysql2date('Y/m/d H:i', $conversation->created_at)); ?>
        · <?php echo esc_html($conversation->language); ?>
        <?php if ($conversation->is_lead) : ?>
            · <strong><?php esc_html_e('لید', 'signteb-web-chat'); ?>: <?php echo esc_html($conversation->cta_type); ?></strong>
        <?php endif; ?>
    </p>

    <?php
    $name  = trim((string) ($conversation->patient_name ?? ''));
    $phone = trim((string) ($conversation->patient_phone ?? ''));
    $badge = ['hot' => '🟢 ' . __('لید داغ', 'signteb-web-chat'), 'warm' => '🟡 ' . __('لید متوسط', 'signteb-web-chat'), 'cold' => '⚪ ' . __('لید سرد', 'signteb-web-chat')];
    ?>
    <div class="swc-lead-panel">
        <div class="swc-lead-info">
            <span><strong><?php esc_html_e('بیمار:', 'signteb-web-chat'); ?></strong> <?php echo esc_html($name !== '' ? $name : '—'); ?></span>
            <span><strong><?php esc_html_e('موبایل:', 'signteb-web-chat'); ?></strong> <?php echo $phone !== '' ? '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>' : '—'; ?></span>
            <span><strong><?php esc_html_e('امتیاز:', 'signteb-web-chat'); ?></strong> <?php echo esc_html($badge[$conversation->lead_score] ?? '—'); ?></span>
        </div>
        <?php if (! empty($conversation->summary)) : ?>
            <div class="swc-summary-box">
                <div class="swc-summary-title"><?php esc_html_e('خلاصه هوشمند گفتگو', 'signteb-web-chat'); ?></div>
                <pre class="swc-summary-text"><?php echo esc_html($conversation->summary); ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div class="swc-crm-panel" data-lead="<?php echo esc_attr($conversation->id); ?>" data-nonce="<?php echo esc_attr(\Medora\Crm\LeadCrm::nonce()); ?>">
        <div class="swc-crm-title"><?php esc_html_e('مدیریت لید (CRM)', 'signteb-web-chat'); ?></div>
        <div class="swc-crm-grid">
            <label>
                <span><?php esc_html_e('وضعیت لید', 'signteb-web-chat'); ?></span>
                <select class="swc-crm-status">
                    <?php foreach (\Medora\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($conversation->lead_status ?? 'new'), $key); ?>><?php echo esc_html($def[0]); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('ایمیل', 'signteb-web-chat'); ?></span>
                <input type="email" class="swc-crm-email" value="<?php echo esc_attr($conversation->email ?? ''); ?>" placeholder="name@example.com">
            </label>
            <label>
                <span><?php esc_html_e('برچسب‌ها', 'signteb-web-chat'); ?></span>
                <input type="text" class="swc-crm-tags" value="<?php echo esc_attr($conversation->tags ?? ''); ?>" placeholder="VIP، جراحی، فوری">
            </label>
            <?php if (! empty($branches)) : ?>
                <label>
                    <span><?php esc_html_e('شعبه / کلینیک', 'signteb-web-chat'); ?></span>
                    <select class="swc-crm-branch">
                        <option value="0"><?php esc_html_e('بدون شعبه', 'signteb-web-chat'); ?></option>
                        <?php foreach ($branches as $b) : ?>
                            <option value="<?php echo esc_attr($b->id); ?>" <?php selected((int) ($conversation->branch_id ?? 0), (int) $b->id); ?>><?php echo esc_html($b->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <label class="swc-crm-notes-wrap">
            <span><?php esc_html_e('یادداشت‌ها', 'signteb-web-chat'); ?></span>
            <textarea class="swc-crm-notes" rows="3" placeholder="<?php esc_attr_e('یادداشت داخلی برای پیگیری…', 'signteb-web-chat'); ?>"><?php echo esc_textarea($conversation->notes ?? ''); ?></textarea>
        </label>
        <div class="swc-crm-foot">
            <button type="button" class="button button-primary swc-crm-save"><?php esc_html_e('ذخیره تغییرات', 'signteb-web-chat'); ?></button>
            <span class="swc-crm-result"></span>
        </div>

        <?php
        $ref_text  = \Medora\Crm\LeadCrm::referral_text($conversation);
        $sms_mgr   = new \Medora\Notifications\SmsManager();
        $sms_ready = $sms_mgr->is_configured();
        $messengers = \Medora\Notifications\SmsManager::messenger_links((string) ($conversation->patient_phone ?? ''), $ref_text);
        ?>
        <div class="swc-refer" data-lead="<?php echo esc_attr($conversation->id); ?>" data-text="<?php echo esc_attr($ref_text); ?>">
            <div class="swc-crm-title"><?php esc_html_e('ارجاع لید به همکار', 'signteb-web-chat'); ?></div>

            <?php $staff = $sms_mgr->staff_numbers(); ?>
            <?php if (! empty($staff)) : ?>
                <div class="swc-refer-row">
                    <select class="swc-refer-staff">
                        <option value=""><?php esc_html_e('انتخاب همکار…', 'signteb-web-chat'); ?></option>
                        <?php foreach ($staff as $st) : ?>
                            <option value="<?php echo esc_attr($st['phone']); ?>" data-email="<?php echo esc_attr($st['email']); ?>">
                                <?php echo esc_html(($st['name'] !== '' ? $st['name'] . ' — ' : '') . $st['phone'] . ($st['email'] !== '' ? ' · ' . $st['email'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php esc_html_e('انتخاب همکار، شماره و ایمیل مقصد را به‌طور خودکار پر می‌کند.', 'signteb-web-chat'); ?></span>
                </div>
            <?php endif; ?>

            <div class="swc-refer-row">
                <input type="email" class="swc-refer-email" placeholder="<?php esc_attr_e('ایمیل همکار', 'signteb-web-chat'); ?>">
                <button type="button" class="button swc-refer-mail"><?php esc_html_e('ارجاع با ایمیل', 'signteb-web-chat'); ?></button>
            </div>
            <div class="swc-refer-row">
                <input type="tel" class="swc-refer-phone" placeholder="<?php esc_attr_e('موبایل مقصد', 'signteb-web-chat'); ?>" inputmode="tel" value="<?php echo esc_attr($conversation->patient_phone ?? ''); ?>">
                <?php if ($sms_ready) : ?>
                    <select class="swc-refer-template">
                        <?php foreach ($sms_mgr->templates() as $tk => $tp) : ?>
                            <option value="<?php echo esc_attr($tk); ?>"><?php echo esc_html($tp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-primary swc-refer-panel"><?php esc_html_e('ارسال پیامک از پنل', 'signteb-web-chat'); ?></button>
                <?php endif; ?>
            </div>

            <div class="swc-refer-messengers">
                <span class="swc-refer-ml-label"><?php esc_html_e('ارسال با پیام‌رسان:', 'signteb-web-chat'); ?></span>
                <?php foreach ($messengers as $mk => $mm) : ?>
                    <a class="button button-small swc-refer-ml" data-ch="<?php echo esc_attr($mk); ?>" href="<?php echo esc_url($mm['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($mm['label']); ?></a>
                <?php endforeach; ?>
            </div>

            <p class="description swc-refer-result">
                <?php
                echo $sms_ready
                    ? esc_html__('پیامک پنل مستقیماً از سرور ارسال می‌شود. ایمیل با میل‌سرور سایت. پیام‌رسان‌ها روی دستگاه شما باز می‌شوند.', 'signteb-web-chat')
                    : esc_html__('برای ارسال مستقیم پیامک، پنل پیامک را در «اتصال‌ها و خروجی» فعال کنید. فعلاً می‌توانید از ایمیل یا پیام‌رسان‌ها استفاده کنید.', 'signteb-web-chat');
                ?>
            </p>
        </div>
    </div>

    <div class="swc-transcript">
        <?php foreach ($messages as $m) : ?>
            <div class="swc-bubble swc-bubble-<?php echo esc_attr($m->role); ?> <?php echo $m->flagged ? 'swc-flagged' : ''; ?>">
                <span class="swc-bubble-role"><?php echo esc_html($m->role); ?></span>
                <div class="swc-bubble-text"><?php echo nl2br(esc_html($m->content)); ?></div>
                <?php if ($m->flagged) : ?><span class="swc-flag-badge"><?php esc_html_e('علامت‌گذاری ایمنی', 'signteb-web-chat'); ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

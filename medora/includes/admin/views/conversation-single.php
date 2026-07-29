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

$back = admin_url('admin.php?page=mdr-chat&tab=conversations');
?>
<p>
    <strong><?php esc_html_e('مکالمه', 'medora'); ?> #<?php echo esc_html($conversation ? $conversation->id : 0); ?></strong>
    <a href="<?php echo esc_url($back); ?>" class="page-title-action"><?php esc_html_e('بازگشت', 'medora'); ?></a>
</p>

<?php if (! $conversation) : ?>
    <p><?php esc_html_e('مکالمه یافت نشد.', 'medora'); ?></p>
<?php else : ?>
    <p class="description">
        <?php echo esc_html(mysql2date('Y/m/d H:i', $conversation->created_at)); ?>
        · <?php echo esc_html($conversation->language); ?>
        <?php if ($conversation->is_lead) : ?>
            · <strong><?php esc_html_e('لید', 'medora'); ?>: <?php echo esc_html($conversation->cta_type); ?></strong>
        <?php endif; ?>
    </p>

    <?php
    $name  = trim((string) ($conversation->patient_name ?? ''));
    $phone = trim((string) ($conversation->patient_phone ?? ''));
    $badge = ['hot' => '🟢 ' . __('لید داغ', 'medora'), 'warm' => '🟡 ' . __('لید متوسط', 'medora'), 'cold' => '⚪ ' . __('لید سرد', 'medora')];
    ?>
    <div class="mdr-lead-panel">
        <div class="mdr-lead-info">
            <span><strong><?php esc_html_e('بیمار:', 'medora'); ?></strong> <?php echo esc_html($name !== '' ? $name : '—'); ?></span>
            <span><strong><?php esc_html_e('موبایل:', 'medora'); ?></strong> <?php echo $phone !== '' ? '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>' : '—'; ?></span>
            <span><strong><?php esc_html_e('امتیاز:', 'medora'); ?></strong> <?php echo esc_html($badge[$conversation->lead_score] ?? '—'); ?></span>
        </div>
        <?php if (! empty($conversation->summary)) : ?>
            <div class="mdr-summary-box">
                <div class="mdr-summary-title"><?php esc_html_e('خلاصه هوشمند گفتگو', 'medora'); ?></div>
                <pre class="mdr-summary-text"><?php echo esc_html($conversation->summary); ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div class="mdr-crm-panel" data-lead="<?php echo esc_attr($conversation->id); ?>" data-nonce="<?php echo esc_attr(\Medora\Crm\LeadCrm::nonce()); ?>">
        <div class="mdr-crm-title"><?php esc_html_e('مدیریت لید (CRM)', 'medora'); ?></div>
        <div class="mdr-crm-grid">
            <label>
                <span><?php esc_html_e('وضعیت لید', 'medora'); ?></span>
                <select class="mdr-crm-status">
                    <?php foreach (\Medora\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($conversation->lead_status ?? 'new'), $key); ?>><?php echo esc_html($def[0]); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('ایمیل', 'medora'); ?></span>
                <input type="email" class="mdr-crm-email" value="<?php echo esc_attr($conversation->email ?? ''); ?>" placeholder="name@example.com">
            </label>
            <label>
                <span><?php esc_html_e('برچسب‌ها', 'medora'); ?></span>
                <input type="text" class="mdr-crm-tags" value="<?php echo esc_attr($conversation->tags ?? ''); ?>" placeholder="VIP، جراحی، فوری">
            </label>
            <?php if (! empty($branches)) : ?>
                <label>
                    <span><?php esc_html_e('شعبه / کلینیک', 'medora'); ?></span>
                    <select class="mdr-crm-branch">
                        <option value="0"><?php esc_html_e('بدون شعبه', 'medora'); ?></option>
                        <?php foreach ($branches as $b) : ?>
                            <option value="<?php echo esc_attr($b->id); ?>" <?php selected((int) ($conversation->branch_id ?? 0), (int) $b->id); ?>><?php echo esc_html($b->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <label class="mdr-crm-notes-wrap">
            <span><?php esc_html_e('یادداشت‌ها', 'medora'); ?></span>
            <textarea class="mdr-crm-notes" rows="3" placeholder="<?php esc_attr_e('یادداشت داخلی برای پیگیری…', 'medora'); ?>"><?php echo esc_textarea($conversation->notes ?? ''); ?></textarea>
        </label>
        <div class="mdr-crm-foot">
            <button type="button" class="button button-primary mdr-crm-save"><?php esc_html_e('ذخیره تغییرات', 'medora'); ?></button>
            <span class="mdr-crm-result"></span>
        </div>

        <?php
        $ref_text  = \Medora\Crm\LeadCrm::referral_text($conversation);
        $sms_mgr   = new \Medora\Notifications\SmsManager();
        $sms_ready = $sms_mgr->is_configured();
        $messengers = \Medora\Notifications\SmsManager::messenger_links((string) ($conversation->patient_phone ?? ''), $ref_text);
        ?>
        <div class="mdr-refer" data-lead="<?php echo esc_attr($conversation->id); ?>" data-text="<?php echo esc_attr($ref_text); ?>">
            <div class="mdr-crm-title"><?php esc_html_e('ارجاع لید به همکار', 'medora'); ?></div>

            <?php $staff = $sms_mgr->staff_numbers(); ?>
            <?php if (! empty($staff)) : ?>
                <div class="mdr-refer-row">
                    <select class="mdr-refer-staff">
                        <option value=""><?php esc_html_e('انتخاب همکار…', 'medora'); ?></option>
                        <?php foreach ($staff as $st) : ?>
                            <option value="<?php echo esc_attr($st['phone']); ?>" data-email="<?php echo esc_attr($st['email']); ?>">
                                <?php echo esc_html(($st['name'] !== '' ? $st['name'] . ' — ' : '') . $st['phone'] . ($st['email'] !== '' ? ' · ' . $st['email'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php esc_html_e('انتخاب همکار، شماره و ایمیل مقصد را به‌طور خودکار پر می‌کند.', 'medora'); ?></span>
                </div>
            <?php endif; ?>

            <div class="mdr-refer-row">
                <input type="email" class="mdr-refer-email" placeholder="<?php esc_attr_e('ایمیل همکار', 'medora'); ?>">
                <button type="button" class="button mdr-refer-mail"><?php esc_html_e('ارجاع با ایمیل', 'medora'); ?></button>
            </div>
            <div class="mdr-refer-row">
                <input type="tel" class="mdr-refer-phone" placeholder="<?php esc_attr_e('موبایل مقصد', 'medora'); ?>" inputmode="tel" value="<?php echo esc_attr($conversation->patient_phone ?? ''); ?>">
                <?php if ($sms_ready) : ?>
                    <select class="mdr-refer-template">
                        <?php foreach ($sms_mgr->templates() as $tk => $tp) : ?>
                            <option value="<?php echo esc_attr($tk); ?>"><?php echo esc_html($tp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-primary mdr-refer-panel"><?php esc_html_e('ارسال پیامک از پنل', 'medora'); ?></button>
                <?php endif; ?>
            </div>

            <div class="mdr-refer-messengers">
                <span class="mdr-refer-ml-label"><?php esc_html_e('ارسال با پیام‌رسان:', 'medora'); ?></span>
                <?php foreach ($messengers as $mk => $mm) : ?>
                    <a class="button button-small mdr-refer-ml" data-ch="<?php echo esc_attr($mk); ?>" href="<?php echo esc_url($mm['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($mm['label']); ?></a>
                <?php endforeach; ?>
            </div>

            <p class="description mdr-refer-result">
                <?php
                echo $sms_ready
                    ? esc_html__('پیامک پنل مستقیماً از سرور ارسال می‌شود. ایمیل با میل‌سرور سایت. پیام‌رسان‌ها روی دستگاه شما باز می‌شوند.', 'medora')
                    : esc_html__('برای ارسال مستقیم پیامک، پنل پیامک را در «اتصال‌ها و خروجی» فعال کنید. فعلاً می‌توانید از ایمیل یا پیام‌رسان‌ها استفاده کنید.', 'medora');
                ?>
            </p>
        </div>
    </div>

    <div class="mdr-transcript">
        <?php foreach ($messages as $m) : ?>
            <div class="mdr-bubble mdr-bubble-<?php echo esc_attr($m->role); ?> <?php echo $m->flagged ? 'mdr-flagged' : ''; ?>">
                <span class="mdr-bubble-role"><?php echo esc_html($m->role); ?></span>
                <div class="mdr-bubble-text"><?php echo nl2br(esc_html($m->content)); ?></div>
                <?php if ($m->flagged) : ?><span class="mdr-flag-badge"><?php esc_html_e('علامت‌گذاری ایمنی', 'medora'); ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

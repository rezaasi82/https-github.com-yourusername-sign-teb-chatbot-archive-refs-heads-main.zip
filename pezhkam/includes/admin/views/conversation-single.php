<?php
/**
 * Single conversation transcript (rendered inside the Conversations tab).
 *
 * @var ?object           $conversation
 * @var array<int,object> $messages
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

$back = admin_url('admin.php?page=pzk-chat&tab=conversations');
?>
<p>
    <strong><?php esc_html_e('مکالمه', 'pezhkam'); ?> #<?php echo esc_html($conversation ? $conversation->id : 0); ?></strong>
    <a href="<?php echo esc_url($back); ?>" class="page-title-action"><?php esc_html_e('بازگشت', 'pezhkam'); ?></a>
</p>

<?php if (! $conversation) : ?>
    <p><?php esc_html_e('مکالمه یافت نشد.', 'pezhkam'); ?></p>
<?php else : ?>
    <p class="description">
        <?php echo esc_html(mysql2date('Y/m/d H:i', $conversation->created_at)); ?>
        · <?php echo esc_html($conversation->language); ?>
        <?php if ($conversation->is_lead) : ?>
            · <strong><?php esc_html_e('لید', 'pezhkam'); ?>: <?php echo esc_html($conversation->cta_type); ?></strong>
        <?php endif; ?>
    </p>

    <?php
    $name  = trim((string) ($conversation->patient_name ?? ''));
    $phone = trim((string) ($conversation->patient_phone ?? ''));
    $badge = ['hot' => '🟢 ' . __('لید داغ', 'pezhkam'), 'warm' => '🟡 ' . __('لید متوسط', 'pezhkam'), 'cold' => '⚪ ' . __('لید سرد', 'pezhkam')];
    ?>
    <div class="pzk-lead-panel">
        <div class="pzk-lead-info">
            <span><strong><?php esc_html_e('بیمار:', 'pezhkam'); ?></strong> <?php echo esc_html($name !== '' ? $name : '—'); ?></span>
            <span><strong><?php esc_html_e('موبایل:', 'pezhkam'); ?></strong> <?php echo $phone !== '' ? '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>' : '—'; ?></span>
            <span><strong><?php esc_html_e('امتیاز:', 'pezhkam'); ?></strong> <?php echo esc_html($badge[$conversation->lead_score] ?? '—'); ?></span>
        </div>
        <?php if (! empty($conversation->summary)) : ?>
            <div class="pzk-summary-box">
                <div class="pzk-summary-title"><?php esc_html_e('خلاصه هوشمند گفتگو', 'pezhkam'); ?></div>
                <pre class="pzk-summary-text"><?php echo esc_html($conversation->summary); ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div class="pzk-crm-panel" data-lead="<?php echo esc_attr($conversation->id); ?>" data-nonce="<?php echo esc_attr(\Pezhkam\Crm\LeadCrm::nonce()); ?>">
        <div class="pzk-crm-title"><?php esc_html_e('مدیریت لید (CRM)', 'pezhkam'); ?></div>
        <div class="pzk-crm-grid">
            <label>
                <span><?php esc_html_e('وضعیت لید', 'pezhkam'); ?></span>
                <select class="pzk-crm-status">
                    <?php foreach (\Pezhkam\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($conversation->lead_status ?? 'new'), $key); ?>><?php echo esc_html($def[0]); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('ایمیل', 'pezhkam'); ?></span>
                <input type="email" class="pzk-crm-email" value="<?php echo esc_attr($conversation->email ?? ''); ?>" placeholder="name@example.com">
            </label>
            <label>
                <span><?php esc_html_e('برچسب‌ها', 'pezhkam'); ?></span>
                <input type="text" class="pzk-crm-tags" value="<?php echo esc_attr($conversation->tags ?? ''); ?>" placeholder="VIP، جراحی، فوری">
            </label>
            <?php if (! empty($branches)) : ?>
                <label>
                    <span><?php esc_html_e('شعبه / کلینیک', 'pezhkam'); ?></span>
                    <select class="pzk-crm-branch">
                        <option value="0"><?php esc_html_e('بدون شعبه', 'pezhkam'); ?></option>
                        <?php foreach ($branches as $b) : ?>
                            <option value="<?php echo esc_attr($b->id); ?>" <?php selected((int) ($conversation->branch_id ?? 0), (int) $b->id); ?>><?php echo esc_html($b->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <label class="pzk-crm-notes-wrap">
            <span><?php esc_html_e('یادداشت‌ها', 'pezhkam'); ?></span>
            <textarea class="pzk-crm-notes" rows="3" placeholder="<?php esc_attr_e('یادداشت داخلی برای پیگیری…', 'pezhkam'); ?>"><?php echo esc_textarea($conversation->notes ?? ''); ?></textarea>
        </label>
        <div class="pzk-crm-foot">
            <button type="button" class="button button-primary pzk-crm-save"><?php esc_html_e('ذخیره تغییرات', 'pezhkam'); ?></button>
            <span class="pzk-crm-result"></span>
        </div>

        <?php
        $ref_text  = \Pezhkam\Crm\LeadCrm::referral_text($conversation);
        $sms_mgr   = new \Pezhkam\Notifications\SmsManager();
        $sms_ready = $sms_mgr->is_configured();
        $messengers = \Pezhkam\Notifications\SmsManager::messenger_links((string) ($conversation->patient_phone ?? ''), $ref_text);
        ?>
        <div class="pzk-refer" data-lead="<?php echo esc_attr($conversation->id); ?>" data-text="<?php echo esc_attr($ref_text); ?>">
            <div class="pzk-crm-title"><?php esc_html_e('ارجاع لید به همکار', 'pezhkam'); ?></div>

            <?php $staff = $sms_mgr->staff_numbers(); ?>
            <?php if (! empty($staff)) : ?>
                <div class="pzk-refer-row">
                    <select class="pzk-refer-staff">
                        <option value=""><?php esc_html_e('انتخاب همکار…', 'pezhkam'); ?></option>
                        <?php foreach ($staff as $st) : ?>
                            <option value="<?php echo esc_attr($st['phone']); ?>" data-email="<?php echo esc_attr($st['email']); ?>">
                                <?php echo esc_html(($st['name'] !== '' ? $st['name'] . ' — ' : '') . $st['phone'] . ($st['email'] !== '' ? ' · ' . $st['email'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php esc_html_e('انتخاب همکار، شماره و ایمیل مقصد را به‌طور خودکار پر می‌کند.', 'pezhkam'); ?></span>
                </div>
            <?php endif; ?>

            <div class="pzk-refer-row">
                <input type="email" class="pzk-refer-email" placeholder="<?php esc_attr_e('ایمیل همکار', 'pezhkam'); ?>">
                <button type="button" class="button pzk-refer-mail"><?php esc_html_e('ارجاع با ایمیل', 'pezhkam'); ?></button>
            </div>
            <div class="pzk-refer-row">
                <input type="tel" class="pzk-refer-phone" placeholder="<?php esc_attr_e('موبایل مقصد', 'pezhkam'); ?>" inputmode="tel" value="<?php echo esc_attr($conversation->patient_phone ?? ''); ?>">
                <?php if ($sms_ready) : ?>
                    <select class="pzk-refer-template">
                        <?php foreach ($sms_mgr->templates() as $tk => $tp) : ?>
                            <option value="<?php echo esc_attr($tk); ?>"><?php echo esc_html($tp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-primary pzk-refer-panel"><?php esc_html_e('ارسال پیامک از پنل', 'pezhkam'); ?></button>
                <?php endif; ?>
            </div>

            <div class="pzk-refer-messengers">
                <span class="pzk-refer-ml-label"><?php esc_html_e('ارسال با پیام‌رسان:', 'pezhkam'); ?></span>
                <?php foreach ($messengers as $mk => $mm) : ?>
                    <a class="button button-small pzk-refer-ml" data-ch="<?php echo esc_attr($mk); ?>" href="<?php echo esc_url($mm['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($mm['label']); ?></a>
                <?php endforeach; ?>
            </div>

            <p class="description pzk-refer-result">
                <?php
                echo $sms_ready
                    ? esc_html__('پیامک پنل مستقیماً از سرور ارسال می‌شود. ایمیل با میل‌سرور سایت. پیام‌رسان‌ها روی دستگاه شما باز می‌شوند.', 'pezhkam')
                    : esc_html__('برای ارسال مستقیم پیامک، پنل پیامک را در «اتصال‌ها و خروجی» فعال کنید. فعلاً می‌توانید از ایمیل یا پیام‌رسان‌ها استفاده کنید.', 'pezhkam');
                ?>
            </p>
        </div>
    </div>

    <div class="pzk-transcript">
        <?php foreach ($messages as $m) : ?>
            <div class="pzk-bubble pzk-bubble-<?php echo esc_attr($m->role); ?> <?php echo $m->flagged ? 'pzk-flagged' : ''; ?>">
                <span class="pzk-bubble-role"><?php echo esc_html($m->role); ?></span>
                <div class="pzk-bubble-text"><?php echo nl2br(esc_html($m->content)); ?></div>
                <?php if ($m->flagged) : ?><span class="pzk-flag-badge"><?php esc_html_e('علامت‌گذاری ایمنی', 'pezhkam'); ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

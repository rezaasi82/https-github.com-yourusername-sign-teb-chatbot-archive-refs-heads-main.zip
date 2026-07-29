<?php
/**
 * Single conversation transcript (rendered inside the Conversations tab).
 *
 * @var ?object           $conversation
 * @var array<int,object> $messages
 *
 * @package Clinovix
 */

if (! defined('ABSPATH')) {
    exit;
}

$back = admin_url('admin.php?page=clx-chat&tab=conversations');
?>
<p>
    <strong><?php esc_html_e('مکالمه', 'clinovix'); ?> #<?php echo esc_html($conversation ? $conversation->id : 0); ?></strong>
    <a href="<?php echo esc_url($back); ?>" class="page-title-action"><?php esc_html_e('بازگشت', 'clinovix'); ?></a>
</p>

<?php if (! $conversation) : ?>
    <p><?php esc_html_e('مکالمه یافت نشد.', 'clinovix'); ?></p>
<?php else : ?>
    <p class="description">
        <?php echo esc_html(mysql2date('Y/m/d H:i', $conversation->created_at)); ?>
        · <?php echo esc_html($conversation->language); ?>
        <?php if ($conversation->is_lead) : ?>
            · <strong><?php esc_html_e('لید', 'clinovix'); ?>: <?php echo esc_html($conversation->cta_type); ?></strong>
        <?php endif; ?>
    </p>

    <?php
    $name  = trim((string) ($conversation->patient_name ?? ''));
    $phone = trim((string) ($conversation->patient_phone ?? ''));
    $badge = ['hot' => '🟢 ' . __('لید داغ', 'clinovix'), 'warm' => '🟡 ' . __('لید متوسط', 'clinovix'), 'cold' => '⚪ ' . __('لید سرد', 'clinovix')];
    ?>
    <div class="clx-lead-panel">
        <div class="clx-lead-info">
            <span><strong><?php esc_html_e('بیمار:', 'clinovix'); ?></strong> <?php echo esc_html($name !== '' ? $name : '—'); ?></span>
            <span><strong><?php esc_html_e('موبایل:', 'clinovix'); ?></strong> <?php echo $phone !== '' ? '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>' : '—'; ?></span>
            <span><strong><?php esc_html_e('امتیاز:', 'clinovix'); ?></strong> <?php echo esc_html($badge[$conversation->lead_score] ?? '—'); ?></span>
        </div>
        <?php if (! empty($conversation->summary)) : ?>
            <div class="clx-summary-box">
                <div class="clx-summary-title"><?php esc_html_e('خلاصه هوشمند گفتگو', 'clinovix'); ?></div>
                <pre class="clx-summary-text"><?php echo esc_html($conversation->summary); ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div class="clx-crm-panel" data-lead="<?php echo esc_attr($conversation->id); ?>" data-nonce="<?php echo esc_attr(\Clinovix\Crm\LeadCrm::nonce()); ?>">
        <div class="clx-crm-title"><?php esc_html_e('مدیریت لید (CRM)', 'clinovix'); ?></div>
        <div class="clx-crm-grid">
            <label>
                <span><?php esc_html_e('وضعیت لید', 'clinovix'); ?></span>
                <select class="clx-crm-status">
                    <?php foreach (\Clinovix\Crm\LeadCrm::STATUSES as $key => $def) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($conversation->lead_status ?? 'new'), $key); ?>><?php echo esc_html($def[0]); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('ایمیل', 'clinovix'); ?></span>
                <input type="email" class="clx-crm-email" value="<?php echo esc_attr($conversation->email ?? ''); ?>" placeholder="name@example.com">
            </label>
            <label>
                <span><?php esc_html_e('برچسب‌ها', 'clinovix'); ?></span>
                <input type="text" class="clx-crm-tags" value="<?php echo esc_attr($conversation->tags ?? ''); ?>" placeholder="VIP، جراحی، فوری">
            </label>
            <?php if (! empty($branches)) : ?>
                <label>
                    <span><?php esc_html_e('شعبه / کلینیک', 'clinovix'); ?></span>
                    <select class="clx-crm-branch">
                        <option value="0"><?php esc_html_e('بدون شعبه', 'clinovix'); ?></option>
                        <?php foreach ($branches as $b) : ?>
                            <option value="<?php echo esc_attr($b->id); ?>" <?php selected((int) ($conversation->branch_id ?? 0), (int) $b->id); ?>><?php echo esc_html($b->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <label class="clx-crm-notes-wrap">
            <span><?php esc_html_e('یادداشت‌ها', 'clinovix'); ?></span>
            <textarea class="clx-crm-notes" rows="3" placeholder="<?php esc_attr_e('یادداشت داخلی برای پیگیری…', 'clinovix'); ?>"><?php echo esc_textarea($conversation->notes ?? ''); ?></textarea>
        </label>
        <div class="clx-crm-foot">
            <button type="button" class="button button-primary clx-crm-save"><?php esc_html_e('ذخیره تغییرات', 'clinovix'); ?></button>
            <span class="clx-crm-result"></span>
        </div>

        <?php
        $ref_text  = \Clinovix\Crm\LeadCrm::referral_text($conversation);
        $sms_mgr   = new \Clinovix\Notifications\SmsManager();
        $sms_ready = $sms_mgr->is_configured();
        $messengers = \Clinovix\Notifications\SmsManager::messenger_links((string) ($conversation->patient_phone ?? ''), $ref_text);
        ?>
        <div class="clx-refer" data-lead="<?php echo esc_attr($conversation->id); ?>" data-text="<?php echo esc_attr($ref_text); ?>">
            <div class="clx-crm-title"><?php esc_html_e('ارجاع لید به همکار', 'clinovix'); ?></div>

            <?php $staff = $sms_mgr->staff_numbers(); ?>
            <?php if (! empty($staff)) : ?>
                <div class="clx-refer-row">
                    <select class="clx-refer-staff">
                        <option value=""><?php esc_html_e('انتخاب همکار…', 'clinovix'); ?></option>
                        <?php foreach ($staff as $st) : ?>
                            <option value="<?php echo esc_attr($st['phone']); ?>" data-email="<?php echo esc_attr($st['email']); ?>">
                                <?php echo esc_html(($st['name'] !== '' ? $st['name'] . ' — ' : '') . $st['phone'] . ($st['email'] !== '' ? ' · ' . $st['email'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php esc_html_e('انتخاب همکار، شماره و ایمیل مقصد را به‌طور خودکار پر می‌کند.', 'clinovix'); ?></span>
                </div>
            <?php endif; ?>

            <div class="clx-refer-row">
                <input type="email" class="clx-refer-email" placeholder="<?php esc_attr_e('ایمیل همکار', 'clinovix'); ?>">
                <button type="button" class="button clx-refer-mail"><?php esc_html_e('ارجاع با ایمیل', 'clinovix'); ?></button>
            </div>
            <div class="clx-refer-row">
                <input type="tel" class="clx-refer-phone" placeholder="<?php esc_attr_e('موبایل مقصد', 'clinovix'); ?>" inputmode="tel" value="<?php echo esc_attr($conversation->patient_phone ?? ''); ?>">
                <?php if ($sms_ready) : ?>
                    <select class="clx-refer-template">
                        <?php foreach ($sms_mgr->templates() as $tk => $tp) : ?>
                            <option value="<?php echo esc_attr($tk); ?>"><?php echo esc_html($tp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-primary clx-refer-panel"><?php esc_html_e('ارسال پیامک از پنل', 'clinovix'); ?></button>
                <?php endif; ?>
            </div>

            <div class="clx-refer-messengers">
                <span class="clx-refer-ml-label"><?php esc_html_e('ارسال با پیام‌رسان:', 'clinovix'); ?></span>
                <?php foreach ($messengers as $mk => $mm) : ?>
                    <a class="button button-small clx-refer-ml" data-ch="<?php echo esc_attr($mk); ?>" href="<?php echo esc_url($mm['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($mm['label']); ?></a>
                <?php endforeach; ?>
            </div>

            <p class="description clx-refer-result">
                <?php
                echo $sms_ready
                    ? esc_html__('پیامک پنل مستقیماً از سرور ارسال می‌شود. ایمیل با میل‌سرور سایت. پیام‌رسان‌ها روی دستگاه شما باز می‌شوند.', 'clinovix')
                    : esc_html__('برای ارسال مستقیم پیامک، پنل پیامک را در «اتصال‌ها و خروجی» فعال کنید. فعلاً می‌توانید از ایمیل یا پیام‌رسان‌ها استفاده کنید.', 'clinovix');
                ?>
            </p>
        </div>
    </div>

    <div class="clx-transcript">
        <?php foreach ($messages as $m) : ?>
            <div class="clx-bubble clx-bubble-<?php echo esc_attr($m->role); ?> <?php echo $m->flagged ? 'clx-flagged' : ''; ?>">
                <span class="clx-bubble-role"><?php echo esc_html($m->role); ?></span>
                <div class="clx-bubble-text"><?php echo nl2br(esc_html($m->content)); ?></div>
                <?php if ($m->flagged) : ?><span class="clx-flag-badge"><?php esc_html_e('علامت‌گذاری ایمنی', 'clinovix'); ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

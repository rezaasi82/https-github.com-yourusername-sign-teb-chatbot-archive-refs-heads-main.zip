<?php
/**
 * Single conversation transcript (rendered inside the Conversations tab).
 *
 * @var ?object           $conversation
 * @var array<int,object> $messages
 *
 * @package SignTeb_Web_Chat
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

    <div class="swc-crm-panel" data-lead="<?php echo esc_attr($conversation->id); ?>" data-nonce="<?php echo esc_attr(SWC_Lead_CRM::nonce()); ?>">
        <div class="swc-crm-title"><?php esc_html_e('مدیریت لید (CRM)', 'signteb-web-chat'); ?></div>
        <div class="swc-crm-grid">
            <label>
                <span><?php esc_html_e('وضعیت لید', 'signteb-web-chat'); ?></span>
                <select class="swc-crm-status">
                    <?php foreach (SWC_Lead_CRM::STATUSES as $key => $def) : ?>
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

<?php
/**
 * Leads list (Conversations tab) with export columns and actions.
 *
 * @var array<int,object> $items
 * @var int               $total
 * @var int               $pages
 * @var int               $page
 * @var bool              $leads_only
 * @var string            $score
 * @var int               $branch
 * @var array<int,object> $branches
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$base   = admin_url('admin.php?page=swc-chat&tab=conversations');
$dl_url = admin_url('admin-ajax.php');
$dl_nonce = wp_create_nonce('swc_export');

// Batched sync-status for the whole page (single query, no N+1).
$lead_ids   = array_map(static fn($c) => (int) $c->id, $items);
$status_map = (new SWC_Sync_Status())->for_leads($lead_ids);

$score_badge = static function (?string $level): string {
    switch ($level) {
        case 'hot':  return '🟢 ' . esc_html__('داغ', 'signteb-web-chat');
        case 'warm': return '🟡 ' . esc_html__('متوسط', 'signteb-web-chat');
        case 'cold': return '⚪ ' . esc_html__('سرد', 'signteb-web-chat');
        default:     return '—';
    }
};
?>
<ul class="subsubsub">
    <li><a href="<?php echo esc_url($base); ?>" class="<?php echo (! $leads_only && $score === '') ? 'current' : ''; ?>"><?php esc_html_e('همه', 'signteb-web-chat'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('leads', 1, $base)); ?>" class="<?php echo $leads_only ? 'current' : ''; ?>"><?php esc_html_e('لیدها', 'signteb-web-chat'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('score', 'hot', $base)); ?>" class="<?php echo $score === 'hot' ? 'current' : ''; ?>">🟢 <?php esc_html_e('لید داغ', 'signteb-web-chat'); ?></a></li>
</ul>

<?php if (! empty($branches)) : ?>
    <form method="get" style="margin:8px 0 12px">
        <input type="hidden" name="page" value="swc-chat">
        <input type="hidden" name="tab" value="conversations">
        <select name="branch" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('همه‌ی شعب', 'signteb-web-chat'); ?></option>
            <?php foreach ($branches as $b) : ?>
                <option value="<?php echo esc_attr($b->id); ?>" <?php selected($branch, (int) $b->id); ?>><?php echo esc_html($b->name); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
<?php endif; ?>

<div class="swc-bulkbar">
    <select id="swc-bulk-op">
        <option value=""><?php esc_html_e('عملیات گروهی', 'signteb-web-chat'); ?></option>
        <option value="pdf"><?php esc_html_e('ساخت PDF انتخاب‌شده‌ها', 'signteb-web-chat'); ?></option>
        <option value="webhook"><?php esc_html_e('همگام‌سازی Webhook', 'signteb-web-chat'); ?></option>
        <option value="gsheet"><?php esc_html_e('همگام‌سازی Google Sheets', 'signteb-web-chat'); ?></option>
        <option value="resend"><?php esc_html_e('ارسال مجدد Webhookهای ناموفق', 'signteb-web-chat'); ?></option>
        <option value="delete_files"><?php esc_html_e('حذف فایل‌های خروجی', 'signteb-web-chat'); ?></option>
    </select>
    <button type="button" class="button" id="swc-bulk-apply"><?php esc_html_e('اجرا', 'signteb-web-chat'); ?></button>
    <span id="swc-bulk-result"></span>
</div>

<table class="widefat striped swc-leads-table">
    <thead>
        <tr>
            <td class="check-column"><input type="checkbox" id="swc-check-all"></td>
            <th><?php esc_html_e('بیمار', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('موبایل', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('تاریخ', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('امتیاز', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('PDF', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('Google Sheet', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('Webhook', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('عملیات', 'signteb-web-chat'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($items)) : ?>
        <tr><td colspan="9"><?php esc_html_e('مکالمه‌ای یافت نشد.', 'signteb-web-chat'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($items as $c) :
            $name  = trim((string) ($c->patient_name ?? ''));
            $phone = trim((string) ($c->patient_phone ?? ''));
            $label = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'signteb-web-chat'), $c->id));
            $st    = $status_map[(int) $c->id] ?? ['webhook' => 'none', 'google_sheets' => 'none', 'pdf' => 'none'];
            $dl    = add_query_arg(['action' => 'swc_download_pdf', 'lead_id' => $c->id, 'nonce' => $dl_nonce], $dl_url);
            ?>
            <tr data-lead="<?php echo esc_attr($c->id); ?>">
                <th class="check-column"><input type="checkbox" class="swc-check" value="<?php echo esc_attr($c->id); ?>"></th>
                <?php $lead_status = (string) ($c->lead_status ?? 'new'); ?>
                <td>
                    <strong><?php echo esc_html($label); ?></strong>
                    <span class="swc-status-pill" style="background:<?php echo esc_attr(SWC_Lead_CRM::color($lead_status)); ?>"><?php echo esc_html(SWC_Lead_CRM::label($lead_status)); ?></span>
                    <div class="swc-row-sub">#<?php echo esc_html($c->id); ?> · <?php echo esc_html($c->language); ?></div>
                </td>
                <td><?php echo $phone !== '' ? esc_html($phone) : '—'; ?></td>
                <td><?php echo esc_html(mysql2date('Y/m/d H:i', $c->created_at)); ?></td>
                <td><?php echo wp_kses_post($score_badge($c->lead_score ?? null)); ?></td>
                <td><?php echo SWC_Sync_Status::badge($st['pdf']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                <td><?php echo SWC_Sync_Status::badge($st['google_sheets']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                <td><?php echo SWC_Sync_Status::badge($st['webhook']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                <td class="swc-actions">
                    <a class="button button-small" href="<?php echo esc_url($dl); ?>" target="_blank"><?php esc_html_e('دانلود PDF', 'signteb-web-chat'); ?></a>
                    <button type="button" class="button button-small swc-act" data-op="webhook" data-lead="<?php echo esc_attr($c->id); ?>">Webhook</button>
                    <button type="button" class="button button-small swc-act" data-op="gsheet" data-lead="<?php echo esc_attr($c->id); ?>">Sheet</button>
                    <a href="<?php echo esc_url(add_query_arg('conversation', $c->id, $base)); ?>"><?php esc_html_e('مشاهده', 'signteb-web-chat'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($pages > 1) : ?>
    <div class="tablenav"><div class="tablenav-pages">
        <?php
        $page_base = $base;
        if ($leads_only) { $page_base = add_query_arg('leads', 1, $page_base); }
        if ($score !== '') { $page_base = add_query_arg('score', $score, $page_base); }
        if ($branch > 0) { $page_base = add_query_arg('branch', $branch, $page_base); }
        echo wp_kses_post(paginate_links([
            'base'      => add_query_arg('paged', '%#%', $page_base),
            'format'    => '',
            'current'   => $page,
            'total'     => $pages,
            'prev_text' => '‹',
            'next_text' => '›',
        ]));
        ?>
    </div></div>
<?php endif; ?>

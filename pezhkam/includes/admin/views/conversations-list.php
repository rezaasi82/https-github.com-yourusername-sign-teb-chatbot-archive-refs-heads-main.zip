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
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

$base   = admin_url('admin.php?page=pzk-chat&tab=conversations');
$dl_url = admin_url('admin-ajax.php');
$dl_nonce = wp_create_nonce('pzk_export');

// Batched sync-status for the whole page (single query, no N+1).
$lead_ids   = array_map(static fn($c) => (int) $c->id, $items);
$status_map = (new \Pezhkam\Export\SyncStatus())->for_leads($lead_ids);

$score_badge = static function (?string $level): string {
    switch ($level) {
        case 'hot':  return '🟢 ' . esc_html__('داغ', 'pezhkam');
        case 'warm': return '🟡 ' . esc_html__('متوسط', 'pezhkam');
        case 'cold': return '⚪ ' . esc_html__('سرد', 'pezhkam');
        default:     return '—';
    }
};
?>
<ul class="subsubsub">
    <li><a href="<?php echo esc_url($base); ?>" class="<?php echo (! $leads_only && $score === '') ? 'current' : ''; ?>"><?php esc_html_e('همه', 'pezhkam'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('leads', 1, $base)); ?>" class="<?php echo $leads_only ? 'current' : ''; ?>"><?php esc_html_e('لیدها', 'pezhkam'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('score', 'hot', $base)); ?>" class="<?php echo $score === 'hot' ? 'current' : ''; ?>">🟢 <?php esc_html_e('لید داغ', 'pezhkam'); ?></a></li>
</ul>

<?php if (! empty($branches)) : ?>
    <form method="get" style="margin:8px 0 12px">
        <input type="hidden" name="page" value="pzk-chat">
        <input type="hidden" name="tab" value="conversations">
        <select name="branch" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('همه‌ی شعب', 'pezhkam'); ?></option>
            <?php foreach ($branches as $b) : ?>
                <option value="<?php echo esc_attr($b->id); ?>" <?php selected($branch, (int) $b->id); ?>><?php echo esc_html($b->name); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
<?php endif; ?>

<div class="pzk-bulkbar">
    <select id="pzk-bulk-op">
        <option value=""><?php esc_html_e('عملیات گروهی', 'pezhkam'); ?></option>
        <option value="pdf"><?php esc_html_e('ساخت PDF انتخاب‌شده‌ها', 'pezhkam'); ?></option>
        <option value="webhook"><?php esc_html_e('همگام‌سازی Webhook', 'pezhkam'); ?></option>
        <option value="gsheet"><?php esc_html_e('همگام‌سازی Google Sheets', 'pezhkam'); ?></option>
        <option value="resend"><?php esc_html_e('ارسال مجدد Webhookهای ناموفق', 'pezhkam'); ?></option>
        <option value="delete_files"><?php esc_html_e('حذف فایل‌های خروجی', 'pezhkam'); ?></option>
    </select>
    <button type="button" class="button" id="pzk-bulk-apply"><?php esc_html_e('اجرا', 'pezhkam'); ?></button>
    <span id="pzk-bulk-result"></span>
</div>

<table class="widefat striped pzk-leads-table">
    <thead>
        <tr>
            <td class="check-column"><input type="checkbox" id="pzk-check-all"></td>
            <th><?php esc_html_e('بیمار', 'pezhkam'); ?></th>
            <th><?php esc_html_e('موبایل', 'pezhkam'); ?></th>
            <th><?php esc_html_e('تاریخ', 'pezhkam'); ?></th>
            <th><?php esc_html_e('امتیاز', 'pezhkam'); ?></th>
            <th><?php esc_html_e('PDF', 'pezhkam'); ?></th>
            <th><?php esc_html_e('Google Sheet', 'pezhkam'); ?></th>
            <th><?php esc_html_e('Webhook', 'pezhkam'); ?></th>
            <th><?php esc_html_e('عملیات', 'pezhkam'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($items)) : ?>
        <tr><td colspan="9"><?php esc_html_e('مکالمه‌ای یافت نشد.', 'pezhkam'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($items as $c) :
            $name  = trim((string) ($c->patient_name ?? ''));
            $phone = trim((string) ($c->patient_phone ?? ''));
            $label = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'pezhkam'), $c->id));
            $st    = $status_map[(int) $c->id] ?? ['webhook' => 'none', 'google_sheets' => 'none', 'pdf' => 'none'];
            $dl    = add_query_arg(['action' => 'pzk_download_pdf', 'lead_id' => $c->id, 'nonce' => $dl_nonce], $dl_url);
            ?>
            <tr data-lead="<?php echo esc_attr($c->id); ?>">
                <th class="check-column"><input type="checkbox" class="pzk-check" value="<?php echo esc_attr($c->id); ?>"></th>
                <?php $lead_status = (string) ($c->lead_status ?? 'new'); ?>
                <td>
                    <strong><?php echo esc_html($label); ?></strong>
                    <span class="pzk-status-pill" style="background:<?php echo esc_attr(\Pezhkam\Crm\LeadCrm::color($lead_status)); ?>"><?php echo esc_html(\Pezhkam\Crm\LeadCrm::label($lead_status)); ?></span>
                    <div class="pzk-row-sub">#<?php echo esc_html($c->id); ?> · <?php echo esc_html($c->language); ?></div>
                </td>
                <td><?php echo $phone !== '' ? esc_html($phone) : '—'; ?></td>
                <td><?php echo esc_html(mysql2date('Y/m/d H:i', $c->created_at)); ?></td>
                <td><?php echo wp_kses_post($score_badge($c->lead_score ?? null)); ?></td>
                <td><?php echo wp_kses_post(\Pezhkam\Export\SyncStatus::badge($st['pdf'])); ?></td>
                <td><?php echo wp_kses_post(\Pezhkam\Export\SyncStatus::badge($st['google_sheets'])); ?></td>
                <td><?php echo wp_kses_post(\Pezhkam\Export\SyncStatus::badge($st['webhook'])); ?></td>
                <td class="pzk-actions">
                    <a class="button button-small" href="<?php echo esc_url($dl); ?>" target="_blank"><?php esc_html_e('دانلود PDF', 'pezhkam'); ?></a>
                    <button type="button" class="button button-small pzk-act" data-op="webhook" data-lead="<?php echo esc_attr($c->id); ?>">Webhook</button>
                    <button type="button" class="button button-small pzk-act" data-op="gsheet" data-lead="<?php echo esc_attr($c->id); ?>">Sheet</button>
                    <a href="<?php echo esc_url(add_query_arg('conversation', $c->id, $base)); ?>"><?php esc_html_e('مشاهده', 'pezhkam'); ?></a>
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

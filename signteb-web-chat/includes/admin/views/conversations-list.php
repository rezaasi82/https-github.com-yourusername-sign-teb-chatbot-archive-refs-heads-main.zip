<?php
/**
 * Smart conversations list (Conversations tab). Patient identity is primary;
 * the numeric id is secondary. Shows lead temperature and booking status.
 *
 * @var array<int,object> $items
 * @var int               $total
 * @var int               $pages
 * @var int               $page
 * @var bool              $leads_only
 * @var string            $score
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$base = admin_url('admin.php?page=swc-chat&tab=conversations');

$score_badge = static function (?string $level): string {
    switch ($level) {
        case 'hot':  return '🟢 ' . esc_html__('داغ', 'signteb-web-chat');
        case 'warm': return '🟡 ' . esc_html__('متوسط', 'signteb-web-chat');
        case 'cold': return '⚪ ' . esc_html__('سرد', 'signteb-web-chat');
        default:     return '—';
    }
};
$booking_badge = static function (?string $status): string {
    return $status && $status !== 'none'
        ? '<span class="swc-pill swc-pill-book">' . esc_html__('کلیک رزرو', 'signteb-web-chat') . '</span>'
        : '—';
};
?>
<ul class="subsubsub">
    <li><a href="<?php echo esc_url($base); ?>" class="<?php echo (! $leads_only && $score === '') ? 'current' : ''; ?>"><?php esc_html_e('همه', 'signteb-web-chat'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('leads', 1, $base)); ?>" class="<?php echo $leads_only ? 'current' : ''; ?>"><?php esc_html_e('لیدها', 'signteb-web-chat'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('score', 'hot', $base)); ?>" class="<?php echo $score === 'hot' ? 'current' : ''; ?>">🟢 <?php esc_html_e('لید داغ', 'signteb-web-chat'); ?></a></li>
</ul>

<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('بیمار', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('موبایل', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('تاریخ', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('امتیاز لید', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('پیام‌ها', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('رزرو', 'signteb-web-chat'); ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($items)) : ?>
        <tr><td colspan="7"><?php esc_html_e('مکالمه‌ای یافت نشد.', 'signteb-web-chat'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($items as $c) : ?>
            <?php
            $name  = trim((string) ($c->patient_name ?? ''));
            $phone = trim((string) ($c->patient_phone ?? ''));
            $label = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'signteb-web-chat'), $c->id));
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($label); ?></strong>
                    <div class="swc-row-sub">#<?php echo esc_html($c->id); ?> · <?php echo esc_html($c->language); ?></div>
                </td>
                <td><?php echo $phone !== '' ? '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>' : '—'; ?></td>
                <td><?php echo esc_html(mysql2date('Y/m/d H:i', $c->created_at)); ?></td>
                <td><?php echo wp_kses_post($score_badge($c->lead_score ?? null)); ?></td>
                <td><?php echo esc_html(number_format_i18n($c->message_count)); ?></td>
                <td><?php echo wp_kses_post($booking_badge($c->booking_status ?? null)); ?></td>
                <td><a href="<?php echo esc_url(add_query_arg('conversation', $c->id, $base)); ?>"><?php esc_html_e('مشاهده', 'signteb-web-chat'); ?></a></td>
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

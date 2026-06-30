<?php
/**
 * Conversations list (rendered inside the Conversations tab).
 *
 * @var array<int,object> $items
 * @var int               $total
 * @var int               $pages
 * @var int               $page
 * @var bool              $leads_only
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$base = admin_url('admin.php?page=swc-chat&tab=conversations');
?>
<ul class="subsubsub">
    <li><a href="<?php echo esc_url($base); ?>" class="<?php echo $leads_only ? '' : 'current'; ?>"><?php esc_html_e('همه', 'signteb-web-chat'); ?></a> | </li>
    <li><a href="<?php echo esc_url(add_query_arg('leads', 1, $base)); ?>" class="<?php echo $leads_only ? 'current' : ''; ?>"><?php esc_html_e('فقط لیدها', 'signteb-web-chat'); ?></a></li>
</ul>

<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('شناسه', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('تاریخ', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('زبان', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('پیام‌ها', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('لید', 'signteb-web-chat'); ?></th>
            <th><?php esc_html_e('CTA', 'signteb-web-chat'); ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($items)) : ?>
        <tr><td colspan="7"><?php esc_html_e('مکالمه‌ای یافت نشد.', 'signteb-web-chat'); ?></td></tr>
    <?php else : ?>
        <?php foreach ($items as $c) : ?>
            <tr>
                <td>#<?php echo esc_html($c->id); ?></td>
                <td><?php echo esc_html(mysql2date('Y/m/d H:i', $c->created_at)); ?></td>
                <td><?php echo esc_html($c->language); ?></td>
                <td><?php echo esc_html(number_format_i18n($c->message_count)); ?></td>
                <td><?php echo $c->is_lead ? '✅' : '—'; ?></td>
                <td><?php echo esc_html($c->cta_type ?: '—'); ?></td>
                <td><a href="<?php echo esc_url(add_query_arg('conversation', $c->id, $base)); ?>"><?php esc_html_e('مشاهده', 'signteb-web-chat'); ?></a></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($pages > 1) : ?>
    <div class="tablenav"><div class="tablenav-pages">
        <?php
        echo wp_kses_post(paginate_links([
            'base'      => add_query_arg('paged', '%#%', $leads_only ? add_query_arg('leads', 1, $base) : $base),
            'format'    => '',
            'current'   => $page,
            'total'     => $pages,
            'prev_text' => '‹',
            'next_text' => '›',
        ]));
        ?>
    </div></div>
<?php endif; ?>

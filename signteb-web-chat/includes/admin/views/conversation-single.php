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

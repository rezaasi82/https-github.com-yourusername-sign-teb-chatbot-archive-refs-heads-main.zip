<?php
/**
 * Single conversation transcript view.
 *
 * @var ?object             $conversation
 * @var array<int,object>   $messages
 */

if (! defined('ABSPATH')) {
    exit;
}

$back = admin_url('admin.php?page=stmc-chat-conversations');
?>
<div class="wrap stmc-admin" dir="rtl">
    <h1>
        <?php esc_html_e('مکالمه', 'signteb-ai-chat'); ?> #<?php echo esc_html($conversation ? $conversation->id : 0); ?>
        <a href="<?php echo esc_url($back); ?>" class="page-title-action"><?php esc_html_e('بازگشت', 'signteb-ai-chat'); ?></a>
    </h1>

    <?php if (! $conversation) : ?>
        <p><?php esc_html_e('مکالمه یافت نشد.', 'signteb-ai-chat'); ?></p>
    <?php else : ?>
        <p class="description">
            <?php echo esc_html(mysql2date('Y/m/d H:i', $conversation->created_at)); ?>
            · <?php echo esc_html($conversation->language); ?>
            <?php if ($conversation->is_lead) : ?>
                · <strong><?php esc_html_e('لید', 'signteb-ai-chat'); ?>: <?php echo esc_html($conversation->cta_type); ?></strong>
            <?php endif; ?>
        </p>

        <div class="stmc-transcript">
            <?php foreach ($messages as $m) : ?>
                <div class="stmc-bubble stmc-bubble-<?php echo esc_attr($m->role); ?> <?php echo $m->flagged ? 'stmc-flagged' : ''; ?>">
                    <span class="stmc-bubble-role"><?php echo esc_html($m->role); ?></span>
                    <div class="stmc-bubble-text"><?php echo nl2br(esc_html($m->content)); ?></div>
                    <?php if ($m->flagged) : ?><span class="stmc-flag-badge"><?php esc_html_e('علامت‌گذاری ایمنی', 'signteb-ai-chat'); ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

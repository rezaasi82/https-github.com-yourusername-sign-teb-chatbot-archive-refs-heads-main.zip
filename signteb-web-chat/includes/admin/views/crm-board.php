<?php
/**
 * Kanban board view.
 *
 * @var array<string,array{label:string,color:string,leads:array<int,object>}> $columns
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$view_base  = admin_url('admin.php?page=swc-chat&tab=conversations');
$score_dot  = static function (?string $s): string {
    $map = ['hot' => '🟢', 'warm' => '🟡', 'cold' => '⚪'];
    return $map[$s] ?? '';
};
?>
<div class="wrap swc-admin swc-board-wrap" dir="rtl">
    <?php \SignTeb\WebChat\Admin\PageHeader::render(
        __('بورد CRM', 'signteb-web-chat'),
        __('برای تغییر وضعیت، کارت لید را بین ستون‌ها بکشید و رها کنید.', 'signteb-web-chat')
    ); ?>

    <div class="swc-board" id="swc-board">
        <?php foreach ($columns as $key => $col) : ?>
            <div class="swc-col" data-status="<?php echo esc_attr($key); ?>">
                <div class="swc-col-head" style="border-top-color:<?php echo esc_attr($col['color']); ?>">
                    <span class="swc-col-title"><?php echo esc_html($col['label']); ?></span>
                    <span class="swc-col-count"><?php echo esc_html(number_format_i18n(count($col['leads']))); ?></span>
                </div>
                <div class="swc-col-body">
                    <?php foreach ($col['leads'] as $lead) :
                        $name  = trim((string) ($lead->patient_name ?? ''));
                        $phone = trim((string) ($lead->patient_phone ?? ''));
                        $title = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'signteb-web-chat'), $lead->id));
                        ?>
                        <div class="swc-card" draggable="true" data-lead="<?php echo esc_attr($lead->id); ?>">
                            <div class="swc-card-name"><?php echo esc_html($score_dot($lead->lead_score ?? null)); ?> <?php echo esc_html($title); ?></div>
                            <?php if ($phone !== '') : ?><div class="swc-card-phone"><?php echo esc_html($phone); ?></div><?php endif; ?>
                            <div class="swc-card-foot">
                                <span>#<?php echo esc_html($lead->id); ?></span>
                                <a href="<?php echo esc_url(add_query_arg('conversation', $lead->id, $view_base)); ?>"><?php esc_html_e('مشاهده', 'signteb-web-chat'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="swc-board-toast" class="swc-board-toast" hidden></div>
</div>

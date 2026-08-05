<?php
/**
 * Kanban board view.
 *
 * @var array<string,array{label:string,color:string,leads:array<int,object>}> $columns
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

$view_base  = admin_url('admin.php?page=pzk-chat&tab=conversations');
$score_dot  = static function (?string $s): string {
    $map = ['hot' => '🟢', 'warm' => '🟡', 'cold' => '⚪'];
    return $map[$s] ?? '';
};
?>
<div class="wrap pzk-admin pzk-board-wrap" dir="rtl">
    <?php \Pezhkam\Admin\PageHeader::render(
        __('بورد CRM', 'pezhkam'),
        __('برای تغییر وضعیت، کارت لید را بین ستون‌ها بکشید و رها کنید.', 'pezhkam')
    ); ?>

    <div class="pzk-board" id="pzk-board">
        <?php foreach ($columns as $key => $col) : ?>
            <div class="pzk-col" data-status="<?php echo esc_attr($key); ?>">
                <div class="pzk-col-head" style="border-top-color:<?php echo esc_attr($col['color']); ?>">
                    <span class="pzk-col-title"><?php echo esc_html($col['label']); ?></span>
                    <span class="pzk-col-count"><?php echo esc_html(number_format_i18n(count($col['leads']))); ?></span>
                </div>
                <div class="pzk-col-body">
                    <?php foreach ($col['leads'] as $lead) :
                        $name  = trim((string) ($lead->patient_name ?? ''));
                        $phone = trim((string) ($lead->patient_phone ?? ''));
                        $title = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'pezhkam'), $lead->id));
                        ?>
                        <div class="pzk-card" draggable="true" data-lead="<?php echo esc_attr($lead->id); ?>">
                            <div class="pzk-card-name"><?php echo esc_html($score_dot($lead->lead_score ?? null)); ?> <?php echo esc_html($title); ?></div>
                            <?php if ($phone !== '') : ?><div class="pzk-card-phone"><?php echo esc_html($phone); ?></div><?php endif; ?>
                            <div class="pzk-card-foot">
                                <span>#<?php echo esc_html($lead->id); ?></span>
                                <a href="<?php echo esc_url(add_query_arg('conversation', $lead->id, $view_base)); ?>"><?php esc_html_e('مشاهده', 'pezhkam'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="pzk-board-toast" class="pzk-board-toast" hidden></div>
</div>

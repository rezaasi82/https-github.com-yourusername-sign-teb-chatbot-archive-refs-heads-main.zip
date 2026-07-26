<?php
/**
 * Kanban board view.
 *
 * @var array<string,array{label:string,color:string,leads:array<int,object>}> $columns
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

$view_base  = admin_url('admin.php?page=pzr-chat&tab=conversations');
$score_dot  = static function (?string $s): string {
    $map = ['hot' => '🟢', 'warm' => '🟡', 'cold' => '⚪'];
    return $map[$s] ?? '';
};
?>
<div class="wrap pzr-admin pzr-board-wrap" dir="rtl">
    <?php \Pazira\Admin\PageHeader::render(
        __('بورد CRM', 'pazira'),
        __('برای تغییر وضعیت، کارت لید را بین ستون‌ها بکشید و رها کنید.', 'pazira')
    ); ?>

    <div class="pzr-board" id="pzr-board">
        <?php foreach ($columns as $key => $col) : ?>
            <div class="pzr-col" data-status="<?php echo esc_attr($key); ?>">
                <div class="pzr-col-head" style="border-top-color:<?php echo esc_attr($col['color']); ?>">
                    <span class="pzr-col-title"><?php echo esc_html($col['label']); ?></span>
                    <span class="pzr-col-count"><?php echo esc_html(number_format_i18n(count($col['leads']))); ?></span>
                </div>
                <div class="pzr-col-body">
                    <?php foreach ($col['leads'] as $lead) :
                        $name  = trim((string) ($lead->patient_name ?? ''));
                        $phone = trim((string) ($lead->patient_phone ?? ''));
                        $title = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'pazira'), $lead->id));
                        ?>
                        <div class="pzr-card" draggable="true" data-lead="<?php echo esc_attr($lead->id); ?>">
                            <div class="pzr-card-name"><?php echo esc_html($score_dot($lead->lead_score ?? null)); ?> <?php echo esc_html($title); ?></div>
                            <?php if ($phone !== '') : ?><div class="pzr-card-phone"><?php echo esc_html($phone); ?></div><?php endif; ?>
                            <div class="pzr-card-foot">
                                <span>#<?php echo esc_html($lead->id); ?></span>
                                <a href="<?php echo esc_url(add_query_arg('conversation', $lead->id, $view_base)); ?>"><?php esc_html_e('مشاهده', 'pazira'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="pzr-board-toast" class="pzr-board-toast" hidden></div>
</div>

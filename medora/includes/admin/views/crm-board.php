<?php
/**
 * Kanban board view.
 *
 * @var array<string,array{label:string,color:string,leads:array<int,object>}> $columns
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

$view_base  = admin_url('admin.php?page=mdr-chat&tab=conversations');
$score_dot  = static function (?string $s): string {
    $map = ['hot' => '🟢', 'warm' => '🟡', 'cold' => '⚪'];
    return $map[$s] ?? '';
};
?>
<div class="wrap mdr-admin mdr-board-wrap" dir="rtl">
    <?php \Medora\Admin\PageHeader::render(
        __('بورد CRM', 'medora'),
        __('برای تغییر وضعیت، کارت لید را بین ستون‌ها بکشید و رها کنید.', 'medora')
    ); ?>

    <div class="mdr-board" id="mdr-board">
        <?php foreach ($columns as $key => $col) : ?>
            <div class="mdr-col" data-status="<?php echo esc_attr($key); ?>">
                <div class="mdr-col-head" style="border-top-color:<?php echo esc_attr($col['color']); ?>">
                    <span class="mdr-col-title"><?php echo esc_html($col['label']); ?></span>
                    <span class="mdr-col-count"><?php echo esc_html(number_format_i18n(count($col['leads']))); ?></span>
                </div>
                <div class="mdr-col-body">
                    <?php foreach ($col['leads'] as $lead) :
                        $name  = trim((string) ($lead->patient_name ?? ''));
                        $phone = trim((string) ($lead->patient_phone ?? ''));
                        $title = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'medora'), $lead->id));
                        ?>
                        <div class="mdr-card" draggable="true" data-lead="<?php echo esc_attr($lead->id); ?>">
                            <div class="mdr-card-name"><?php echo esc_html($score_dot($lead->lead_score ?? null)); ?> <?php echo esc_html($title); ?></div>
                            <?php if ($phone !== '') : ?><div class="mdr-card-phone"><?php echo esc_html($phone); ?></div><?php endif; ?>
                            <div class="mdr-card-foot">
                                <span>#<?php echo esc_html($lead->id); ?></span>
                                <a href="<?php echo esc_url(add_query_arg('conversation', $lead->id, $view_base)); ?>"><?php esc_html_e('مشاهده', 'medora'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="mdr-board-toast" class="mdr-board-toast" hidden></div>
</div>

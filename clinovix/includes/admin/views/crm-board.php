<?php
/**
 * Kanban board view.
 *
 * @var array<string,array{label:string,color:string,leads:array<int,object>}> $columns
 *
 * @package Clinovix
 */

if (! defined('ABSPATH')) {
    exit;
}

$view_base  = admin_url('admin.php?page=clx-chat&tab=conversations');
$score_dot  = static function (?string $s): string {
    $map = ['hot' => '🟢', 'warm' => '🟡', 'cold' => '⚪'];
    return $map[$s] ?? '';
};
?>
<div class="wrap clx-admin clx-board-wrap" dir="rtl">
    <?php \Clinovix\Admin\PageHeader::render(
        __('بورد CRM', 'clinovix'),
        __('برای تغییر وضعیت، کارت لید را بین ستون‌ها بکشید و رها کنید.', 'clinovix')
    ); ?>

    <div class="clx-board" id="clx-board">
        <?php foreach ($columns as $key => $col) : ?>
            <div class="clx-col" data-status="<?php echo esc_attr($key); ?>">
                <div class="clx-col-head" style="border-top-color:<?php echo esc_attr($col['color']); ?>">
                    <span class="clx-col-title"><?php echo esc_html($col['label']); ?></span>
                    <span class="clx-col-count"><?php echo esc_html(number_format_i18n(count($col['leads']))); ?></span>
                </div>
                <div class="clx-col-body">
                    <?php foreach ($col['leads'] as $lead) :
                        $name  = trim((string) ($lead->patient_name ?? ''));
                        $phone = trim((string) ($lead->patient_phone ?? ''));
                        $title = $name !== '' ? $name : ($phone !== '' ? $phone : sprintf(__('مهمان #%d', 'clinovix'), $lead->id));
                        ?>
                        <div class="clx-card" draggable="true" data-lead="<?php echo esc_attr($lead->id); ?>">
                            <div class="clx-card-name"><?php echo esc_html($score_dot($lead->lead_score ?? null)); ?> <?php echo esc_html($title); ?></div>
                            <?php if ($phone !== '') : ?><div class="clx-card-phone"><?php echo esc_html($phone); ?></div><?php endif; ?>
                            <div class="clx-card-foot">
                                <span>#<?php echo esc_html($lead->id); ?></span>
                                <a href="<?php echo esc_url(add_query_arg('conversation', $lead->id, $view_base)); ?>"><?php esc_html_e('مشاهده', 'clinovix'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="clx-board-toast" class="clx-board-toast" hidden></div>
</div>

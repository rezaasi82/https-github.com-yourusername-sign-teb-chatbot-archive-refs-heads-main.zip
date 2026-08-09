<?php
/**
 * Branches management view.
 *
 * @var array<int,object>          $branches
 * @var array<int,array{total:int,leads:int}> $stats
 * @var ?object                    $editing
 * @var string                     $nonce
 *
 * @package Medora
 */

if (! defined('ABSPATH')) {
    exit;
}

$action_url = admin_url('admin-post.php');
$page_url   = admin_url('admin.php?page=mdr-branches');
?>
<div class="wrap mdr-admin" dir="rtl">
    <?php \Medora\Admin\PageHeader::render(
        __('کلینیک‌ها و شعب', 'medora'),
        __('تعریف شعب و انتساب لیدها به هر شعبه برای گزارش تفکیک‌شده.', 'medora')
    ); ?>
    <p class="description"><?php esc_html_e('چند کلینیک/پزشک/شعبه را با یک نصب مدیریت کنید. هر لید می‌تواند به یک شعبه منتسب شود و آمار هر شعبه جداگانه محاسبه می‌شود.', 'medora'); ?></p>

    <?php if (\Medora\Core\Input::has_get('saved')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('ذخیره شد.', 'medora'); ?></p></div><?php endif; ?>
    <?php if (\Medora\Core\Input::has_get('deleted')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('حذف شد.', 'medora'); ?></p></div><?php endif; ?>

    <div class="mdr-branch-layout">
        <div class="mdr-branch-list">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'medora'); ?></th>
                        <th><?php esc_html_e('پزشک', 'medora'); ?></th>
                        <th><?php esc_html_e('تلفن', 'medora'); ?></th>
                        <th><?php esc_html_e('گفتگو', 'medora'); ?></th>
                        <th><?php esc_html_e('لید', 'medora'); ?></th>
                        <th><?php esc_html_e('شناسه ویجت', 'medora'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($branches)) : ?>
                    <tr><td colspan="7"><?php esc_html_e('هنوز شعبه‌ای ثبت نشده است.', 'medora'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($branches as $b) :
                        $st = $stats[(int) $b->id] ?? ['total' => 0, 'leads' => 0];
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($b->name); ?></strong></td>
                            <td><?php echo esc_html($b->doctor ?: '—'); ?></td>
                            <td><?php echo esc_html($b->phone ?: '—'); ?></td>
                            <td><?php echo esc_html(number_format_i18n($st['total'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n($st['leads'])); ?></td>
                            <td><code>branch=<?php echo esc_html($b->id); ?></code></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', $b->id, $page_url)); ?>"><?php esc_html_e('ویرایش', 'medora'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['branch' => $b->id], admin_url('admin.php?page=mdr-chat&tab=conversations'))); ?>"><?php esc_html_e('لیدها', 'medora'); ?></a>
                                <form method="post" action="<?php echo esc_url($action_url); ?>" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('حذف این شعبه؟ لیدهایش حذف نمی‌شوند و بدون‌شعبه می‌شوند.', 'medora'); ?>');">
                                    <?php wp_nonce_field($nonce); ?>
                                    <input type="hidden" name="action" value="mdr_branch_delete">
                                    <input type="hidden" name="branch_id" value="<?php echo esc_attr($b->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('حذف', 'medora'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mdr-branch-form">
            <h2><?php echo $editing ? esc_html__('ویرایش شعبه', 'medora') : esc_html__('افزودن شعبه', 'medora'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <?php wp_nonce_field($nonce); ?>
                <input type="hidden" name="action" value="mdr_branch_save">
                <input type="hidden" name="branch_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                <p><label><?php esc_html_e('نام شعبه/کلینیک', 'medora'); ?><br><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($editing->name ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('پزشک', 'medora'); ?><br><input type="text" name="doctor" class="regular-text" value="<?php echo esc_attr($editing->doctor ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('تلفن', 'medora'); ?><br><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($editing->phone ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('آدرس', 'medora'); ?><br><input type="text" name="address" class="regular-text" value="<?php echo esc_attr($editing->address ?? ''); ?>"></label></p>
                <p>
                    <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('به‌روزرسانی', 'medora') : esc_html__('افزودن', 'medora'); ?></button>
                    <?php if ($editing) : ?><a class="button" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('انصراف', 'medora'); ?></a><?php endif; ?>
                </p>
                <p class="description"><?php esc_html_e('برای اتصال ویجت یک صفحه به این شعبه، از فیلتر mdr_widget_branch استفاده کنید یا شناسه‌ی شعبه را در تنظیمات صفحه قرار دهید.', 'medora'); ?></p>
            </form>
        </div>
    </div>
</div>

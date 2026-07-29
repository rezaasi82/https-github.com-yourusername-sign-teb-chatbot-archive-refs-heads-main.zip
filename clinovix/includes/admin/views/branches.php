<?php
/**
 * Branches management view.
 *
 * @var array<int,object>          $branches
 * @var array<int,array{total:int,leads:int}> $stats
 * @var ?object                    $editing
 * @var string                     $nonce
 *
 * @package Clinovix
 */

if (! defined('ABSPATH')) {
    exit;
}

$action_url = admin_url('admin-post.php');
$page_url   = admin_url('admin.php?page=clx-branches');
?>
<div class="wrap clx-admin" dir="rtl">
    <?php \Clinovix\Admin\PageHeader::render(
        __('کلینیک‌ها و شعب', 'clinovix'),
        __('تعریف شعب و انتساب لیدها به هر شعبه برای گزارش تفکیک‌شده.', 'clinovix')
    ); ?>
    <p class="description"><?php esc_html_e('چند کلینیک/پزشک/شعبه را با یک نصب مدیریت کنید. هر لید می‌تواند به یک شعبه منتسب شود و آمار هر شعبه جداگانه محاسبه می‌شود.', 'clinovix'); ?></p>

    <?php if (\Clinovix\Core\Input::has_get('saved')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('ذخیره شد.', 'clinovix'); ?></p></div><?php endif; ?>
    <?php if (\Clinovix\Core\Input::has_get('deleted')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('حذف شد.', 'clinovix'); ?></p></div><?php endif; ?>

    <div class="clx-branch-layout">
        <div class="clx-branch-list">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'clinovix'); ?></th>
                        <th><?php esc_html_e('پزشک', 'clinovix'); ?></th>
                        <th><?php esc_html_e('تلفن', 'clinovix'); ?></th>
                        <th><?php esc_html_e('گفتگو', 'clinovix'); ?></th>
                        <th><?php esc_html_e('لید', 'clinovix'); ?></th>
                        <th><?php esc_html_e('شناسه ویجت', 'clinovix'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($branches)) : ?>
                    <tr><td colspan="7"><?php esc_html_e('هنوز شعبه‌ای ثبت نشده است.', 'clinovix'); ?></td></tr>
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
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', $b->id, $page_url)); ?>"><?php esc_html_e('ویرایش', 'clinovix'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['branch' => $b->id], admin_url('admin.php?page=clx-chat&tab=conversations'))); ?>"><?php esc_html_e('لیدها', 'clinovix'); ?></a>
                                <form method="post" action="<?php echo esc_url($action_url); ?>" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('حذف این شعبه؟ لیدهایش حذف نمی‌شوند و بدون‌شعبه می‌شوند.', 'clinovix'); ?>');">
                                    <?php wp_nonce_field($nonce); ?>
                                    <input type="hidden" name="action" value="clx_branch_delete">
                                    <input type="hidden" name="branch_id" value="<?php echo esc_attr($b->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('حذف', 'clinovix'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="clx-branch-form">
            <h2><?php echo $editing ? esc_html__('ویرایش شعبه', 'clinovix') : esc_html__('افزودن شعبه', 'clinovix'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <?php wp_nonce_field($nonce); ?>
                <input type="hidden" name="action" value="clx_branch_save">
                <input type="hidden" name="branch_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                <p><label><?php esc_html_e('نام شعبه/کلینیک', 'clinovix'); ?><br><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($editing->name ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('پزشک', 'clinovix'); ?><br><input type="text" name="doctor" class="regular-text" value="<?php echo esc_attr($editing->doctor ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('تلفن', 'clinovix'); ?><br><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($editing->phone ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('آدرس', 'clinovix'); ?><br><input type="text" name="address" class="regular-text" value="<?php echo esc_attr($editing->address ?? ''); ?>"></label></p>
                <p>
                    <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('به‌روزرسانی', 'clinovix') : esc_html__('افزودن', 'clinovix'); ?></button>
                    <?php if ($editing) : ?><a class="button" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('انصراف', 'clinovix'); ?></a><?php endif; ?>
                </p>
                <p class="description"><?php esc_html_e('برای اتصال ویجت یک صفحه به این شعبه، از فیلتر clx_widget_branch استفاده کنید یا شناسه‌ی شعبه را در تنظیمات صفحه قرار دهید.', 'clinovix'); ?></p>
            </form>
        </div>
    </div>
</div>

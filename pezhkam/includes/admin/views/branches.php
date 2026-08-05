<?php
/**
 * Branches management view.
 *
 * @var array<int,object>          $branches
 * @var array<int,array{total:int,leads:int}> $stats
 * @var ?object                    $editing
 * @var string                     $nonce
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

$action_url = admin_url('admin-post.php');
$page_url   = admin_url('admin.php?page=pzk-branches');
?>
<div class="wrap pzk-admin" dir="rtl">
    <?php \Pezhkam\Admin\PageHeader::render(
        __('کلینیک‌ها و شعب', 'pezhkam'),
        __('تعریف شعب و انتساب لیدها به هر شعبه برای گزارش تفکیک‌شده.', 'pezhkam')
    ); ?>
    <p class="description"><?php esc_html_e('چند کلینیک/پزشک/شعبه را با یک نصب مدیریت کنید. هر لید می‌تواند به یک شعبه منتسب شود و آمار هر شعبه جداگانه محاسبه می‌شود.', 'pezhkam'); ?></p>

    <?php if (\Pezhkam\Core\Input::has_get('saved')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('ذخیره شد.', 'pezhkam'); ?></p></div><?php endif; ?>
    <?php if (\Pezhkam\Core\Input::has_get('deleted')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('حذف شد.', 'pezhkam'); ?></p></div><?php endif; ?>

    <div class="pzk-branch-layout">
        <div class="pzk-branch-list">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'pezhkam'); ?></th>
                        <th><?php esc_html_e('پزشک', 'pezhkam'); ?></th>
                        <th><?php esc_html_e('تلفن', 'pezhkam'); ?></th>
                        <th><?php esc_html_e('گفتگو', 'pezhkam'); ?></th>
                        <th><?php esc_html_e('لید', 'pezhkam'); ?></th>
                        <th><?php esc_html_e('شناسه ویجت', 'pezhkam'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($branches)) : ?>
                    <tr><td colspan="7"><?php esc_html_e('هنوز شعبه‌ای ثبت نشده است.', 'pezhkam'); ?></td></tr>
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
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', $b->id, $page_url)); ?>"><?php esc_html_e('ویرایش', 'pezhkam'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['branch' => $b->id], admin_url('admin.php?page=pzk-chat&tab=conversations'))); ?>"><?php esc_html_e('لیدها', 'pezhkam'); ?></a>
                                <form method="post" action="<?php echo esc_url($action_url); ?>" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('حذف این شعبه؟ لیدهایش حذف نمی‌شوند و بدون‌شعبه می‌شوند.', 'pezhkam'); ?>');">
                                    <?php wp_nonce_field($nonce); ?>
                                    <input type="hidden" name="action" value="pzk_branch_delete">
                                    <input type="hidden" name="branch_id" value="<?php echo esc_attr($b->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('حذف', 'pezhkam'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pzk-branch-form">
            <h2><?php echo $editing ? esc_html__('ویرایش شعبه', 'pezhkam') : esc_html__('افزودن شعبه', 'pezhkam'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <?php wp_nonce_field($nonce); ?>
                <input type="hidden" name="action" value="pzk_branch_save">
                <input type="hidden" name="branch_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                <p><label><?php esc_html_e('نام شعبه/کلینیک', 'pezhkam'); ?><br><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($editing->name ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('پزشک', 'pezhkam'); ?><br><input type="text" name="doctor" class="regular-text" value="<?php echo esc_attr($editing->doctor ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('تلفن', 'pezhkam'); ?><br><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($editing->phone ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('آدرس', 'pezhkam'); ?><br><input type="text" name="address" class="regular-text" value="<?php echo esc_attr($editing->address ?? ''); ?>"></label></p>
                <p>
                    <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('به‌روزرسانی', 'pezhkam') : esc_html__('افزودن', 'pezhkam'); ?></button>
                    <?php if ($editing) : ?><a class="button" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('انصراف', 'pezhkam'); ?></a><?php endif; ?>
                </p>
                <p class="description"><?php esc_html_e('برای اتصال ویجت یک صفحه به این شعبه، از فیلتر pzk_widget_branch استفاده کنید یا شناسه‌ی شعبه را در تنظیمات صفحه قرار دهید.', 'pezhkam'); ?></p>
            </form>
        </div>
    </div>
</div>

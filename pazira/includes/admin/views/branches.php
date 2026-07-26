<?php
/**
 * Branches management view.
 *
 * @var array<int,object>          $branches
 * @var array<int,array{total:int,leads:int}> $stats
 * @var ?object                    $editing
 * @var string                     $nonce
 *
 * @package Pazira
 */

if (! defined('ABSPATH')) {
    exit;
}

$action_url = admin_url('admin-post.php');
$page_url   = admin_url('admin.php?page=pzr-branches');
?>
<div class="wrap pzr-admin" dir="rtl">
    <?php \Pazira\Admin\PageHeader::render(
        __('کلینیک‌ها و شعب', 'pazira'),
        __('تعریف شعب و انتساب لیدها به هر شعبه برای گزارش تفکیک‌شده.', 'pazira')
    ); ?>
    <p class="description"><?php esc_html_e('چند کلینیک/پزشک/شعبه را با یک نصب مدیریت کنید. هر لید می‌تواند به یک شعبه منتسب شود و آمار هر شعبه جداگانه محاسبه می‌شود.', 'pazira'); ?></p>

    <?php if (\Pazira\Core\Input::has_get('saved')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('ذخیره شد.', 'pazira'); ?></p></div><?php endif; ?>
    <?php if (\Pazira\Core\Input::has_get('deleted')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('حذف شد.', 'pazira'); ?></p></div><?php endif; ?>

    <div class="pzr-branch-layout">
        <div class="pzr-branch-list">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'pazira'); ?></th>
                        <th><?php esc_html_e('پزشک', 'pazira'); ?></th>
                        <th><?php esc_html_e('تلفن', 'pazira'); ?></th>
                        <th><?php esc_html_e('گفتگو', 'pazira'); ?></th>
                        <th><?php esc_html_e('لید', 'pazira'); ?></th>
                        <th><?php esc_html_e('شناسه ویجت', 'pazira'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($branches)) : ?>
                    <tr><td colspan="7"><?php esc_html_e('هنوز شعبه‌ای ثبت نشده است.', 'pazira'); ?></td></tr>
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
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', $b->id, $page_url)); ?>"><?php esc_html_e('ویرایش', 'pazira'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['branch' => $b->id], admin_url('admin.php?page=pzr-chat&tab=conversations'))); ?>"><?php esc_html_e('لیدها', 'pazira'); ?></a>
                                <form method="post" action="<?php echo esc_url($action_url); ?>" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('حذف این شعبه؟ لیدهایش حذف نمی‌شوند و بدون‌شعبه می‌شوند.', 'pazira'); ?>');">
                                    <?php wp_nonce_field($nonce); ?>
                                    <input type="hidden" name="action" value="pzr_branch_delete">
                                    <input type="hidden" name="branch_id" value="<?php echo esc_attr($b->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('حذف', 'pazira'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pzr-branch-form">
            <h2><?php echo $editing ? esc_html__('ویرایش شعبه', 'pazira') : esc_html__('افزودن شعبه', 'pazira'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <?php wp_nonce_field($nonce); ?>
                <input type="hidden" name="action" value="pzr_branch_save">
                <input type="hidden" name="branch_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                <p><label><?php esc_html_e('نام شعبه/کلینیک', 'pazira'); ?><br><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($editing->name ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('پزشک', 'pazira'); ?><br><input type="text" name="doctor" class="regular-text" value="<?php echo esc_attr($editing->doctor ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('تلفن', 'pazira'); ?><br><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($editing->phone ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('آدرس', 'pazira'); ?><br><input type="text" name="address" class="regular-text" value="<?php echo esc_attr($editing->address ?? ''); ?>"></label></p>
                <p>
                    <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('به‌روزرسانی', 'pazira') : esc_html__('افزودن', 'pazira'); ?></button>
                    <?php if ($editing) : ?><a class="button" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('انصراف', 'pazira'); ?></a><?php endif; ?>
                </p>
                <p class="description"><?php esc_html_e('برای اتصال ویجت یک صفحه به این شعبه، از فیلتر pzr_widget_branch استفاده کنید یا شناسه‌ی شعبه را در تنظیمات صفحه قرار دهید.', 'pazira'); ?></p>
            </form>
        </div>
    </div>
</div>

<?php
/**
 * Branches management view.
 *
 * @var array<int,object>          $branches
 * @var array<int,array{total:int,leads:int}> $stats
 * @var ?object                    $editing
 * @var string                     $nonce
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

$action_url = admin_url('admin-post.php');
$page_url   = admin_url('admin.php?page=swc-branches');
?>
<div class="wrap swc-admin" dir="rtl">
    <?php \SignTeb\WebChat\Admin\PageHeader::render(
        __('کلینیک‌ها و شعب', 'signteb-web-chat'),
        __('تعریف شعب و انتساب لیدها به هر شعبه برای گزارش تفکیک‌شده.', 'signteb-web-chat')
    ); ?>
    <p class="description"><?php esc_html_e('چند کلینیک/پزشک/شعبه را با یک نصب مدیریت کنید. هر لید می‌تواند به یک شعبه منتسب شود و آمار هر شعبه جداگانه محاسبه می‌شود.', 'signteb-web-chat'); ?></p>

    <?php if (\SignTeb\WebChat\Core\Input::has_get('saved')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('ذخیره شد.', 'signteb-web-chat'); ?></p></div><?php endif; ?>
    <?php if (\SignTeb\WebChat\Core\Input::has_get('deleted')) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('حذف شد.', 'signteb-web-chat'); ?></p></div><?php endif; ?>

    <div class="swc-branch-layout">
        <div class="swc-branch-list">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'signteb-web-chat'); ?></th>
                        <th><?php esc_html_e('پزشک', 'signteb-web-chat'); ?></th>
                        <th><?php esc_html_e('تلفن', 'signteb-web-chat'); ?></th>
                        <th><?php esc_html_e('گفتگو', 'signteb-web-chat'); ?></th>
                        <th><?php esc_html_e('لید', 'signteb-web-chat'); ?></th>
                        <th><?php esc_html_e('شناسه ویجت', 'signteb-web-chat'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($branches)) : ?>
                    <tr><td colspan="7"><?php esc_html_e('هنوز شعبه‌ای ثبت نشده است.', 'signteb-web-chat'); ?></td></tr>
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
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg('edit', $b->id, $page_url)); ?>"><?php esc_html_e('ویرایش', 'signteb-web-chat'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['branch' => $b->id], admin_url('admin.php?page=swc-chat&tab=conversations'))); ?>"><?php esc_html_e('لیدها', 'signteb-web-chat'); ?></a>
                                <form method="post" action="<?php echo esc_url($action_url); ?>" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('حذف این شعبه؟ لیدهایش حذف نمی‌شوند و بدون‌شعبه می‌شوند.', 'signteb-web-chat'); ?>');">
                                    <?php wp_nonce_field($nonce); ?>
                                    <input type="hidden" name="action" value="swc_branch_delete">
                                    <input type="hidden" name="branch_id" value="<?php echo esc_attr($b->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('حذف', 'signteb-web-chat'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="swc-branch-form">
            <h2><?php echo $editing ? esc_html__('ویرایش شعبه', 'signteb-web-chat') : esc_html__('افزودن شعبه', 'signteb-web-chat'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <?php wp_nonce_field($nonce); ?>
                <input type="hidden" name="action" value="swc_branch_save">
                <input type="hidden" name="branch_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                <p><label><?php esc_html_e('نام شعبه/کلینیک', 'signteb-web-chat'); ?><br><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($editing->name ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('پزشک', 'signteb-web-chat'); ?><br><input type="text" name="doctor" class="regular-text" value="<?php echo esc_attr($editing->doctor ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('تلفن', 'signteb-web-chat'); ?><br><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($editing->phone ?? ''); ?>"></label></p>
                <p><label><?php esc_html_e('آدرس', 'signteb-web-chat'); ?><br><input type="text" name="address" class="regular-text" value="<?php echo esc_attr($editing->address ?? ''); ?>"></label></p>
                <p>
                    <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('به‌روزرسانی', 'signteb-web-chat') : esc_html__('افزودن', 'signteb-web-chat'); ?></button>
                    <?php if ($editing) : ?><a class="button" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('انصراف', 'signteb-web-chat'); ?></a><?php endif; ?>
                </p>
                <p class="description"><?php esc_html_e('برای اتصال ویجت یک صفحه به این شعبه، از فیلتر swc_widget_branch استفاده کنید یا شناسه‌ی شعبه را در تنظیمات صفحه قرار دهید.', 'signteb-web-chat'); ?></p>
            </form>
        </div>
    </div>
</div>

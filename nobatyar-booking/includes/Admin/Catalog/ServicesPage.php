<?php

namespace Nobatyar\Admin\Catalog;

use Nobatyar\Labels\TerminologyMap;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Free-tier Service CRUD. Creating/editing/deleting services must never be
 * gated by license status (CLAUDE.md non-negotiable #4) - only SMS and
 * online payment are Pro/Business features, so this page never touches
 * GracePeriodHandler/LicenseManager.
 */
class ServicesPage
{
    private ServiceRepository $service_repository;

    public function __construct(ServiceRepository $service_repository)
    {
        $this->service_repository = $service_repository;
    }

    public function handle_submission(): void
    {
        if (! isset($_POST['nobatyar_services_action'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_services_save', 'nobatyar_services_nonce')) {
            return;
        }

        $action = sanitize_key($_POST['nobatyar_services_action']);

        switch ($action) {
            case 'save':
                $this->save();
                break;
            case 'delete':
                $this->delete();
                break;
        }
    }

    private function save(): void
    {
        $id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));

        if ('' === $name) {
            return;
        }

        $price          = '' === ($_POST['price'] ?? '') ? null : (float) $_POST['price'];
        $deposit_amount = '' === ($_POST['deposit_amount'] ?? '') ? null : (float) $_POST['deposit_amount'];

        $data = [
            'name'             => $name,
            'duration_minutes' => max(1, absint($_POST['duration_minutes'] ?? 30)),
            'buffer_minutes'   => absint($_POST['buffer_minutes'] ?? 0),
            'price'            => $price,
            'deposit_amount'   => $deposit_amount,
            'is_active'        => ! empty($_POST['is_active']),
        ];

        if ($id) {
            $this->service_repository->update($id, $data);
        } else {
            $this->service_repository->create($data);
        }
    }

    private function delete(): void
    {
        $id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;

        if ($id) {
            $this->service_repository->delete($id);
        }
    }

    public function render(?int $editing_id = null): string
    {
        $services = $this->service_repository->all(false);
        $editing  = $editing_id ? $this->service_repository->find($editing_id) : null;
        $label    = TerminologyMap::get('service');

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-services">
            <h1><?php echo esc_html(sprintf(__('مدیریت %s', 'nobatyar-booking'), $label)); ?></h1>

            <?php echo $this->render_form($editing); ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('مدت (دقیقه)', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('قیمت', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('عملیات', 'nobatyar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service) : ?>
                        <tr>
                            <td><?php echo esc_html($service['name']); ?></td>
                            <td><?php echo esc_html($service['duration_minutes']); ?></td>
                            <td><?php echo esc_html($service['price'] ?? '-'); ?></td>
                            <td><?php echo $service['is_active'] ? esc_html__('فعال', 'nobatyar-booking') : esc_html__('غیرفعال', 'nobatyar-booking'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['edit' => $service['id']])); ?>"><?php esc_html_e('ویرایش', 'nobatyar-booking'); ?></a>
                                |
                                <form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('حذف شود؟', 'nobatyar-booking')); ?>');">
                                    <?php wp_nonce_field('nobatyar_services_save', 'nobatyar_services_nonce'); ?>
                                    <input type="hidden" name="nobatyar_services_action" value="delete">
                                    <input type="hidden" name="service_id" value="<?php echo esc_attr($service['id']); ?>">
                                    <button type="submit" class="button-link-delete"><?php esc_html_e('حذف', 'nobatyar-booking'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $services) : ?>
                        <tr><td colspan="5"><?php echo esc_html(sprintf(__('هنوز %s ثبت نشده است.', 'nobatyar-booking'), $label)); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_form(?array $editing): string
    {
        $is_active_checked = null === $editing || ! empty($editing['is_active']);

        ob_start();
        ?>
        <form method="post" class="nobatyar-services-form">
            <?php wp_nonce_field('nobatyar_services_save', 'nobatyar_services_nonce'); ?>
            <input type="hidden" name="nobatyar_services_action" value="save">
            <input type="hidden" name="service_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">

            <p>
                <label><?php esc_html_e('نام', 'nobatyar-booking'); ?></label>
                <input type="text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" required>
            </p>
            <p>
                <label><?php esc_html_e('مدت (دقیقه)', 'nobatyar-booking'); ?></label>
                <input type="number" name="duration_minutes" min="1" value="<?php echo esc_attr($editing['duration_minutes'] ?? 30); ?>">
            </p>
            <p>
                <label><?php esc_html_e('فاصله بعد از نوبت (دقیقه)', 'nobatyar-booking'); ?></label>
                <input type="number" name="buffer_minutes" min="0" value="<?php echo esc_attr($editing['buffer_minutes'] ?? 0); ?>">
            </p>
            <p>
                <label><?php esc_html_e('قیمت', 'nobatyar-booking'); ?></label>
                <input type="number" step="0.01" name="price" value="<?php echo esc_attr($editing['price'] ?? ''); ?>">
            </p>
            <p>
                <label><?php esc_html_e('مبلغ بیعانه', 'nobatyar-booking'); ?></label>
                <input type="number" step="0.01" name="deposit_amount" value="<?php echo esc_attr($editing['deposit_amount'] ?? ''); ?>">
            </p>
            <p>
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php checked($is_active_checked); ?>>
                    <?php esc_html_e('فعال', 'nobatyar-booking'); ?>
                </label>
            </p>

            <button type="submit" class="button button-primary">
                <?php echo $editing ? esc_html__('به‌روزرسانی', 'nobatyar-booking') : esc_html__('افزودن', 'nobatyar-booking'); ?>
            </button>
        </form>
        <?php

        return ob_get_clean();
    }
}

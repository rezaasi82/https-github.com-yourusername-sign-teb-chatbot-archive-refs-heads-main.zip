<?php

namespace Nobatyar\Admin\Catalog;

use Nobatyar\Labels\TerminologyMap;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Free-tier Provider CRUD. Like ServicesPage, this never checks license
 * status - per CLAUDE.md, only SMS and online payment are gated by license,
 * never Provider/Service management (core Free tier features).
 */
class ProvidersPage
{
    private ProviderRepository $provider_repository;
    private ServiceRepository $service_repository;

    public function __construct(ProviderRepository $provider_repository, ServiceRepository $service_repository)
    {
        $this->provider_repository = $provider_repository;
        $this->service_repository  = $service_repository;
    }

    public function handle_submission(): void
    {
        if (! isset($_POST['nobatyar_providers_action'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_providers_save', 'nobatyar_providers_nonce')) {
            return;
        }

        $action = sanitize_key($_POST['nobatyar_providers_action']);

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
        $id = isset($_POST['provider_id']) ? absint($_POST['provider_id']) : 0;

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));

        if ('' === $name) {
            return;
        }

        $data = [
            'user_id'        => isset($_POST['user_id']) ? absint($_POST['user_id']) : null,
            'name'           => $name,
            'label_override' => sanitize_text_field(wp_unslash($_POST['label_override'] ?? '')),
            'license_field'  => sanitize_text_field(wp_unslash($_POST['license_field'] ?? '')),
            'is_active'      => ! empty($_POST['is_active']),
            'sort_order'     => absint($_POST['sort_order'] ?? 0),
        ];

        if ($id) {
            $this->provider_repository->update($id, $data);
        } else {
            $id = $this->provider_repository->create($data);
        }

        $service_ids = array_map('absint', (array) ($_POST['service_ids'] ?? []));

        $this->provider_repository->sync_services($id, $service_ids);
    }

    private function delete(): void
    {
        $id = isset($_POST['provider_id']) ? absint($_POST['provider_id']) : 0;

        if ($id) {
            $this->provider_repository->delete($id);
        }
    }

    public function render(?int $editing_id = null): string
    {
        $providers = $this->provider_repository->all(false);
        $services  = $this->service_repository->all(false);
        $editing    = $editing_id ? $this->provider_repository->find($editing_id) : null;
        $selected_service_ids = $editing_id ? $this->provider_repository->get_service_ids_for_provider($editing_id) : [];

        $label = TerminologyMap::get('provider');

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-providers">
            <h1><?php echo esc_html(sprintf(__('مدیریت %s', 'nobatyar-booking'), $label)); ?></h1>

            <?php echo $this->render_form($editing, $services, $selected_service_ids); ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html(TerminologyMap::get('license_field')); ?></th>
                        <th><?php esc_html_e('وضعیت', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('عملیات', 'nobatyar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $provider) : ?>
                        <tr>
                            <td><?php echo esc_html($provider['label_override'] ?: $provider['name']); ?></td>
                            <td><?php echo esc_html($provider['license_field'] ?: '-'); ?></td>
                            <td><?php echo $provider['is_active'] ? esc_html__('فعال', 'nobatyar-booking') : esc_html__('غیرفعال', 'nobatyar-booking'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['edit' => $provider['id']])); ?>"><?php esc_html_e('ویرایش', 'nobatyar-booking'); ?></a>
                                |
                                <form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('حذف شود؟', 'nobatyar-booking')); ?>');">
                                    <?php wp_nonce_field('nobatyar_providers_save', 'nobatyar_providers_nonce'); ?>
                                    <input type="hidden" name="nobatyar_providers_action" value="delete">
                                    <input type="hidden" name="provider_id" value="<?php echo esc_attr($provider['id']); ?>">
                                    <button type="submit" class="button-link-delete"><?php esc_html_e('حذف', 'nobatyar-booking'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $providers) : ?>
                        <tr><td colspan="4"><?php echo esc_html(sprintf(__('هنوز %s ثبت نشده است.', 'nobatyar-booking'), $label)); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_form(?array $editing, array $services, array $selected_service_ids): string
    {
        $is_active_checked = null === $editing || ! empty($editing['is_active']);

        ob_start();
        ?>
        <form method="post" class="nobatyar-providers-form">
            <?php wp_nonce_field('nobatyar_providers_save', 'nobatyar_providers_nonce'); ?>
            <input type="hidden" name="nobatyar_providers_action" value="save">
            <input type="hidden" name="provider_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">

            <p>
                <label><?php esc_html_e('نام', 'nobatyar-booking'); ?></label>
                <input type="text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" required>
            </p>
            <p>
                <label><?php esc_html_e('عنوان جایگزین (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="text" name="label_override" value="<?php echo esc_attr($editing['label_override'] ?? ''); ?>">
            </p>
            <p>
                <label><?php echo esc_html(TerminologyMap::get('license_field')); ?></label>
                <input type="text" name="license_field" value="<?php echo esc_attr($editing['license_field'] ?? ''); ?>">
            </p>
            <p>
                <label><?php esc_html_e('ترتیب نمایش', 'nobatyar-booking'); ?></label>
                <input type="number" name="sort_order" value="<?php echo esc_attr($editing['sort_order'] ?? 0); ?>">
            </p>

            <fieldset>
                <legend><?php echo esc_html(TerminologyMap::get('service')); ?></legend>
                <?php foreach ($services as $service) : ?>
                    <label style="display:block">
                        <input type="checkbox" name="service_ids[]" value="<?php echo esc_attr($service['id']); ?>" <?php checked(in_array((int) $service['id'], $selected_service_ids, true)); ?>>
                        <?php echo esc_html($service['name']); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

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

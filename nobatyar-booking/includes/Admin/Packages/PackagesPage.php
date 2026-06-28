<?php

namespace Nobatyar\Admin\Packages;

use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;
use Nobatyar\Packages\PackageEngine;
use Nobatyar\Packages\PackageRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Unlike ServicesPage (free-tier CRUD with one gated field), a Package is
 * itself an entirely new Business-tier-only concept with no free-tier
 * equivalent — so the whole create/edit/delete capability is gated, not
 * just one field. Tier-gating lives in PackageEngine, so this page delegates
 * writes to it rather than touching PackageRepository directly. Existing
 * package rows (created during a prior Business period) still display
 * read-only if the site later downgrades.
 */
class PackagesPage
{
    private PackageEngine $package_engine;
    private PackageRepository $package_repository;
    private ServiceRepository $service_repository;
    private LicenseManager $license_manager;

    public function __construct(
        PackageEngine $package_engine,
        PackageRepository $package_repository,
        ServiceRepository $service_repository,
        LicenseManager $license_manager
    ) {
        $this->package_engine      = $package_engine;
        $this->package_repository  = $package_repository;
        $this->service_repository  = $service_repository;
        $this->license_manager     = $license_manager;
    }

    public function handle_submission(): void
    {
        if (! isset($_POST['nobatyar_packages_action'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_packages_save', 'nobatyar_packages_nonce')) {
            return;
        }

        $action = sanitize_key($_POST['nobatyar_packages_action']);

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
        $id   = isset($_POST['package_id']) ? absint($_POST['package_id']) : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));

        if ('' === $name) {
            return;
        }

        $data = [
            'service_id'    => absint($_POST['service_id'] ?? 0),
            'name'          => $name,
            'session_count' => max(1, absint($_POST['session_count'] ?? 1)),
            'price'         => (float) ($_POST['price'] ?? 0),
            'validity_days' => '' === ($_POST['validity_days'] ?? '') ? null : absint($_POST['validity_days']),
            'is_active'     => ! empty($_POST['is_active']),
        ];

        if ($id) {
            $this->package_engine->update_package($id, $data);
        } else {
            $this->package_engine->create_package($data);
        }
    }

    private function delete(): void
    {
        $id = isset($_POST['package_id']) ? absint($_POST['package_id']) : 0;

        if ($id) {
            $this->package_engine->delete_package($id);
        }
    }

    public function render(?int $editing_id = null): string
    {
        $packages_available = $this->license_manager->is_tier_available(LicenseTier::BUSINESS);
        $packages           = $this->package_repository->all(false);
        $purchases          = $this->package_repository->all_purchases();
        $services           = $this->index_services_by_id($this->service_repository->all(false));
        $editing             = $editing_id ? $this->package_repository->find($editing_id) : null;

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-packages">
            <h1><?php esc_html_e('مدیریت پکیج‌های نشست', 'nobatyar-booking'); ?></h1>

            <?php if (! $packages_available) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('پکیج‌های نشست ویژگی پلن Business است. پکیج‌های قبلی (در صورت وجود) فقط قابل مشاهده هستند و امکان ایجاد یا ویرایش پکیج جدید وجود ندارد.', 'nobatyar-booking'); ?></p>
                </div>
            <?php else : ?>
                <?php echo $this->render_form($editing, $services); ?>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نام پکیج', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('خدمت', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('تعداد نشست', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('قیمت', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('اعتبار (روز)', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'nobatyar-booking'); ?></th>
                        <?php if ($packages_available) : ?>
                            <th><?php esc_html_e('عملیات', 'nobatyar-booking'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $package) : ?>
                        <tr>
                            <td><?php echo esc_html($package['name']); ?></td>
                            <td><?php echo esc_html($services[(int) $package['service_id']]['name'] ?? ''); ?></td>
                            <td><?php echo esc_html($package['session_count']); ?></td>
                            <td><?php echo esc_html($package['price']); ?></td>
                            <td><?php echo $package['validity_days'] ? esc_html($package['validity_days']) : esc_html__('بدون انقضا', 'nobatyar-booking'); ?></td>
                            <td><?php echo $package['is_active'] ? esc_html__('فعال', 'nobatyar-booking') : esc_html__('غیرفعال', 'nobatyar-booking'); ?></td>
                            <?php if ($packages_available) : ?>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['edit' => $package['id']])); ?>"><?php esc_html_e('ویرایش', 'nobatyar-booking'); ?></a>
                                    |
                                    <form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('حذف شود؟', 'nobatyar-booking')); ?>');">
                                        <?php wp_nonce_field('nobatyar_packages_save', 'nobatyar_packages_nonce'); ?>
                                        <input type="hidden" name="nobatyar_packages_action" value="delete">
                                        <input type="hidden" name="package_id" value="<?php echo esc_attr($package['id']); ?>">
                                        <button type="submit" class="button-link-delete"><?php esc_html_e('حذف', 'nobatyar-booking'); ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $packages) : ?>
                        <tr><td colspan="7"><?php esc_html_e('هنوز پکیجی ثبت نشده است.', 'nobatyar-booking'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('خریدهای مشتریان', 'nobatyar-booking'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('مشتری', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('شماره موبایل', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('پکیج', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('نشست باقی‌مانده', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('تاریخ خرید', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('انقضا', 'nobatyar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase) : ?>
                        <tr>
                            <td><?php echo esc_html($purchase['customer_name']); ?></td>
                            <td><?php echo esc_html($purchase['customer_phone']); ?></td>
                            <td><?php echo esc_html($purchase['package_name']); ?></td>
                            <td><?php echo esc_html($purchase['sessions_remaining'] . ' / ' . $purchase['sessions_total']); ?></td>
                            <td><?php echo esc_html($purchase['purchased_at']); ?></td>
                            <td><?php echo $purchase['expires_at'] ? esc_html($purchase['expires_at']) : esc_html__('بدون انقضا', 'nobatyar-booking'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $purchases) : ?>
                        <tr><td colspan="6"><?php esc_html_e('هنوز خریدی ثبت نشده است.', 'nobatyar-booking'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_form(?array $editing, array $services): string
    {
        ob_start();
        ?>
        <form method="post" class="nobatyar-packages-form">
            <?php wp_nonce_field('nobatyar_packages_save', 'nobatyar_packages_nonce'); ?>
            <input type="hidden" name="nobatyar_packages_action" value="save">
            <input type="hidden" name="package_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">

            <p>
                <label><?php esc_html_e('نام پکیج', 'nobatyar-booking'); ?></label>
                <input type="text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" required>
            </p>
            <p>
                <label><?php esc_html_e('خدمت', 'nobatyar-booking'); ?></label>
                <select name="service_id" required>
                    <option value=""><?php esc_html_e('انتخاب کنید', 'nobatyar-booking'); ?></option>
                    <?php foreach ($services as $service) : ?>
                        <option value="<?php echo esc_attr($service['id']); ?>" <?php selected($editing['service_id'] ?? '', $service['id']); ?>><?php echo esc_html($service['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label><?php esc_html_e('تعداد نشست', 'nobatyar-booking'); ?></label>
                <input type="number" name="session_count" min="1" value="<?php echo esc_attr($editing['session_count'] ?? 1); ?>">
            </p>
            <p>
                <label><?php esc_html_e('قیمت', 'nobatyar-booking'); ?></label>
                <input type="number" step="0.01" name="price" value="<?php echo esc_attr($editing['price'] ?? ''); ?>">
            </p>
            <p>
                <label><?php esc_html_e('اعتبار زمانی (روز، اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="number" name="validity_days" min="1" value="<?php echo esc_attr($editing['validity_days'] ?? ''); ?>">
                <br><span class="description"><?php esc_html_e('خالی بگذارید برای پکیج بدون انقضا.', 'nobatyar-booking'); ?></span>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php checked(null === $editing || ! empty($editing['is_active'])); ?>>
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

    private function index_services_by_id(array $services): array
    {
        $indexed = [];

        foreach ($services as $service) {
            $indexed[(int) $service['id']] = $service;
        }

        return $indexed;
    }
}

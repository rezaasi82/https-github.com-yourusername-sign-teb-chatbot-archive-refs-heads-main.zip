<?php

namespace Nobatyar\Admin\Dashboard;

use Nobatyar\Booking\BookingEngine;
use Nobatyar\Booking\BookingRepository;
use Nobatyar\Booking\BookingStatus;
use Nobatyar\Calendar\JalaliConverter;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

class ListView
{
    private BookingRepository $booking_repository;
    private ProviderRepository $provider_repository;
    private ServiceRepository $service_repository;
    private BookingEngine $booking_engine;

    public function __construct(
        BookingRepository $booking_repository,
        ProviderRepository $provider_repository,
        ServiceRepository $service_repository,
        BookingEngine $booking_engine
    ) {
        $this->booking_repository  = $booking_repository;
        $this->provider_repository = $provider_repository;
        $this->service_repository  = $service_repository;
        $this->booking_engine      = $booking_engine;
    }

    /**
     * Processes a status-change request submitted from the list table.
     * Hooked to admin_init so it runs before the page itself is rendered.
     */
    public function handle_actions(): void
    {
        if (! isset($_POST['nobatyar_change_status'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_change_status', 'nobatyar_change_status_nonce')) {
            return;
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $new_status = sanitize_key($_POST['new_status'] ?? '');

        $this->booking_engine->change_status($booking_id, $new_status);
    }

    public function render(array $filters = []): string
    {
        $bookings  = $this->booking_repository->all($filters);
        $providers = $this->index_by_id($this->provider_repository->all(false));
        $services  = $this->index_by_id($this->service_repository->all(false));

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-list">
            <h1><?php echo esc_html__('نوبت‌ها', 'nobatyar-booking'); ?></h1>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('مشتری', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html__('سرویس‌دهنده', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html__('خدمت', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html__('تاریخ و ساعت', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html__('وضعیت', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html__('عملیات', 'nobatyar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking) : ?>
                        <tr>
                            <td><?php echo esc_html($booking['customer_name']); ?></td>
                            <td><?php echo esc_html($providers[(int) $booking['provider_id']]['name'] ?? ''); ?></td>
                            <td><?php echo esc_html($services[(int) $booking['service_id']]['name'] ?? ''); ?></td>
                            <td><?php echo esc_html(JalaliConverter::gregorian_to_jalali_string(substr($booking['booking_datetime'], 0, 10))); ?></td>
                            <td><?php echo esc_html($booking['status']); ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
                                    <?php wp_nonce_field('nobatyar_change_status', 'nobatyar_change_status_nonce'); ?>
                                    <select name="new_status">
                                        <?php foreach (BookingStatus::all() as $status) : ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected($booking['status'], $status); ?>><?php echo esc_html($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="nobatyar_change_status" value="1" class="button"><?php echo esc_html__('ثبت', 'nobatyar-booking'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function index_by_id(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        return $indexed;
    }
}

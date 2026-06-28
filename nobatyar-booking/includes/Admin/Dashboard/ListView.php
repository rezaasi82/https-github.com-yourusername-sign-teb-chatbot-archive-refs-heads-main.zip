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
        if (isset($_POST['nobatyar_change_status'])) {
            $this->handle_change_status();
        }

        if (isset($_POST['nobatyar_cancel_recurrence_series'])) {
            $this->handle_cancel_recurrence_series();
        }
    }

    private function handle_change_status(): void
    {
        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_change_status', 'nobatyar_change_status_nonce')) {
            return;
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $new_status = sanitize_key($_POST['new_status'] ?? '');

        $this->booking_engine->change_status($booking_id, $new_status);
    }

    /**
     * "Cancel this and future occurrences" — leaves past/already-resolved
     * occurrences of the series untouched, mirroring the availability
     * single-date-exception pattern of not disturbing what's already settled.
     */
    private function handle_cancel_recurrence_series(): void
    {
        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_cancel_recurrence_series', 'nobatyar_cancel_recurrence_series_nonce')) {
            return;
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);

        $this->booking_engine->cancel_series_from($booking_id);
    }

    public function render(array $filters = []): string
    {
        $bookings  = $this->booking_repository->all($filters);
        $providers = $this->index_by_id($this->provider_repository->all(false));
        $services  = $this->index_by_id($this->service_repository->all(false));

        $group_totals = [];

        foreach ($bookings as $booking) {
            $key                 = $this->group_key($booking);
            $group_totals[$key] = ($group_totals[$key] ?? 0) + 1;
        }

        $group_seen = [];

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
                        <?php
                        $service_row = $services[(int) $booking['service_id']] ?? null;
                        $is_group    = $service_row && (int) $service_row['capacity_max'] > 1;
                        $group_key   = $this->group_key($booking);
                        $is_recurring = ! empty($booking['recurrence_total']) && (int) $booking['recurrence_total'] > 1;

                        $group_seen[$group_key] = ($group_seen[$group_key] ?? 0) + 1;
                        ?>
                        <tr class="<?php echo $is_group ? 'nby-group-row' : ''; ?>">
                            <td>
                                <?php echo esc_html($booking['customer_name']); ?>
                                <?php if ($is_group) : ?>
                                    <br><span class="description"><?php echo esc_html(sprintf(__('شرکت‌کننده %1$d از %2$d', 'nobatyar-booking'), $group_seen[$group_key], $group_totals[$group_key])); ?></span>
                                <?php endif; ?>
                                <?php if ($is_recurring) : ?>
                                    <br><span class="description"><?php echo esc_html(sprintf(__('نوبت %1$d از %2$d (سری تکرارشونده)', 'nobatyar-booking'), (int) $booking['recurrence_index'], (int) $booking['recurrence_total'])); ?></span>
                                <?php endif; ?>
                            </td>
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
                                <?php if ($is_recurring && in_array($booking['status'], BookingStatus::ACTIVE, true)) : ?>
                                    <form method="post" onsubmit="return confirm('<?php echo esc_js(__('این نوبت و تمام نوبت‌های بعدی این سری لغو شوند؟', 'nobatyar-booking')); ?>');">
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
                                        <?php wp_nonce_field('nobatyar_cancel_recurrence_series', 'nobatyar_cancel_recurrence_series_nonce'); ?>
                                        <button type="submit" name="nobatyar_cancel_recurrence_series" value="1" class="button"><?php echo esc_html__('لغو این و نوبت‌های بعدی', 'nobatyar-booking'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Identifies attendees of the same group-booking slot — same provider,
     * service, and start time — so they can be displayed as one block
     * ("attendee 2 of 5") while each keeps its own status-change form.
     */
    private function group_key(array $booking): string
    {
        return $booking['provider_id'] . '|' . $booking['service_id'] . '|' . $booking['booking_datetime'];
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

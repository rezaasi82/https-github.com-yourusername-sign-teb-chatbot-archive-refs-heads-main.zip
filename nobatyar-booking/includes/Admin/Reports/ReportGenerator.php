<?php

namespace Nobatyar\Admin\Reports;

use Nobatyar\Booking\BookingRepository;
use Nobatyar\Booking\BookingStatus;
use Nobatyar\Payment\TransactionRepository;

if (! defined('ABSPATH')) {
    exit;
}

class ReportGenerator
{
    private BookingRepository $booking_repository;
    private TransactionRepository $transaction_repository;

    public function __construct(BookingRepository $booking_repository, TransactionRepository $transaction_repository)
    {
        $this->booking_repository     = $booking_repository;
        $this->transaction_repository = $transaction_repository;
    }

    /**
     * @return array{total_bookings:int,status_counts:array<string,int>,revenue:float}
     */
    public function generate(string $date_from, string $date_to): array
    {
        $bookings = $this->booking_repository->all([
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ]);

        $status_counts = array_fill_keys(BookingStatus::all(), 0);

        foreach ($bookings as $booking) {
            $status_counts[$booking['status']] = ($status_counts[$booking['status']] ?? 0) + 1;
        }

        return [
            'total_bookings' => count($bookings),
            'status_counts'  => $status_counts,
            'revenue'        => $this->transaction_repository->sum_successful_amount_between($date_from, $date_to),
        ];
    }

    public function render(string $date_from, string $date_to): string
    {
        $report = $this->generate($date_from, $date_to);

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-reports">
            <h1><?php echo esc_html__('گزارش‌ها', 'nobatyar-booking'); ?></h1>

            <p><?php echo esc_html(sprintf(__('تعداد کل نوبت‌ها: %d', 'nobatyar-booking'), $report['total_bookings'])); ?></p>
            <p><?php echo esc_html(sprintf(__('درآمد تأییدشده: %s', 'nobatyar-booking'), number_format($report['revenue']))); ?></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('وضعیت', 'nobatyar-booking'); ?></th>
                        <th><?php echo esc_html__('تعداد', 'nobatyar-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['status_counts'] as $status => $count) : ?>
                        <tr>
                            <td><?php echo esc_html($status); ?></td>
                            <td><?php echo (int) $count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }
}

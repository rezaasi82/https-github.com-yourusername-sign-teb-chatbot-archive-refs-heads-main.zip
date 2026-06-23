<?php

namespace Nobatyar\Admin\Dashboard;

use Nobatyar\Booking\BookingRepository;
use Nobatyar\Calendar\JalaliConverter;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders a Jalali month grid with per-day booking counts. Bookings are
 * always queried by their Gregorian datetime (source of truth); the Jalali
 * month boundaries are converted to a Gregorian range up front so the grid
 * itself is the only place Jalali math happens.
 */
class CalendarView
{
    private BookingRepository $booking_repository;

    public function __construct(BookingRepository $booking_repository)
    {
        $this->booking_repository = $booking_repository;
    }

    public function render(?int $jalali_year = null, ?int $jalali_month = null): string
    {
        if (! $jalali_year || ! $jalali_month) {
            $today        = JalaliConverter::to_jalali(current_time('Y-m-d'));
            $jalali_year  = $jalali_year ?: $today['year'];
            $jalali_month = $jalali_month ?: $today['month'];
        }

        $month_length = JalaliConverter::jalali_month_length($jalali_year, $jalali_month);

        $first_gregorian = JalaliConverter::to_gregorian($jalali_year, $jalali_month, 1);
        $last_gregorian   = JalaliConverter::to_gregorian($jalali_year, $jalali_month, $month_length);

        $date_from = sprintf('%04d-%02d-%02d 00:00:00', $first_gregorian['year'], $first_gregorian['month'], $first_gregorian['day']);
        $date_to   = sprintf('%04d-%02d-%02d 23:59:59', $last_gregorian['year'], $last_gregorian['month'], $last_gregorian['day']);

        $bookings = $this->booking_repository->all([
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ]);

        $counts_by_jalali_day = array_fill(1, $month_length, 0);

        foreach ($bookings as $booking) {
            $jalali = JalaliConverter::to_jalali(substr($booking['booking_datetime'], 0, 10));

            if ($jalali['year'] === $jalali_year && $jalali['month'] === $jalali_month) {
                $counts_by_jalali_day[$jalali['day']]++;
            }
        }

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-calendar">
            <h1><?php echo esc_html(JalaliConverter::month_name($jalali_month) . ' ' . $jalali_year); ?></h1>

            <div class="nobatyar-calendar-grid">
                <?php for ($day = 1; $day <= $month_length; $day++) : ?>
                    <div class="nobatyar-calendar-day" data-day="<?php echo (int) $day; ?>">
                        <span class="nobatyar-calendar-day-number"><?php echo (int) $day; ?></span>
                        <span class="nobatyar-calendar-day-count"><?php echo (int) $counts_by_jalali_day[$day]; ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}

<?php

namespace Nobatyar\Provider;

use Nobatyar\Calendar\HolidayProvider;

if (! defined('ABSPATH')) {
    exit;
}

class AvailabilityManager
{
    private function availability_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_availability';
    }

    private function exceptions_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_availability_exceptions';
    }

    /**
     * Working periods for a provider on a given date as [['start' => 'HH:MM:SS', 'end' => 'HH:MM:SS'], ...].
     * A full-day exception clears the day; a partial exception overrides the
     * recurring weekly schedule for that date; otherwise the weekly schedule applies.
     *
     * When no exception row exists at all for the date, an observed official Iranian
     * holiday (see HolidayProvider) closes the day by default - explicit per-provider
     * exceptions always take priority and are checked first, so a provider can still
     * choose to work on a holiday by adding their own exception row.
     */
    public function get_working_periods(int $provider_id, string $date): array
    {
        global $wpdb;

        $exceptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->exceptions_table()} WHERE (provider_id = %d OR provider_id IS NULL) AND date = %s",
                $provider_id,
                $date
            ),
            ARRAY_A
        );

        if ($exceptions) {
            foreach ($exceptions as $exception) {
                if ((int) $exception['is_full_day'] === 1) {
                    return [];
                }
            }

            $partial_exceptions = array_filter($exceptions, static fn ($exception) => (int) $exception['is_full_day'] === 0);

            return array_map(
                static fn ($exception) => ['start' => $exception['start_time'], 'end' => $exception['end_time']],
                array_values($partial_exceptions)
            );
        }

        if (apply_filters('nobatyar_observe_official_holidays', true) && HolidayProvider::is_holiday($date)) {
            return [];
        }

        $weekday = (int) gmdate('w', strtotime($date));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time, end_time FROM {$this->availability_table()} WHERE provider_id = %d AND weekday = %d ORDER BY start_time ASC",
                $provider_id,
                $weekday
            ),
            ARRAY_A
        );

        return array_map(
            static fn ($row) => ['start' => $row['start_time'], 'end' => $row['end_time']],
            $rows
        );
    }
}

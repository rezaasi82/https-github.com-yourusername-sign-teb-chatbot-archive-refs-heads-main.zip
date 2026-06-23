<?php

namespace Nobatyar\Calendar;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Official Iranian holiday awareness, keyed on the Jalali calendar.
 *
 * Only fixed-date (solar) holidays are bundled here. Lunar Hijri holidays
 * (Eid al-Fitr, Ashura, Tasua, Arbaeen, etc.) shift every Jalali year and need
 * a reliable, annually-updated external data source - they are intentionally
 * left out of this static list and must be supplied per-year via the
 * `nobatyar_holidays` filter instead of being hardcoded and going stale.
 */
class HolidayProvider
{
    /**
     * @var array<int,array{0:int,1:int,2:string}> [jalali_month, jalali_day, label]
     */
    private const FIXED_HOLIDAYS = [
        [1, 1, 'نوروز'],
        [1, 2, 'نوروز'],
        [1, 3, 'نوروز'],
        [1, 4, 'نوروز'],
        [1, 12, 'روز جمهوری اسلامی'],
        [1, 13, 'سیزده‌بدر'],
        [3, 14, 'رحلت امام خمینی (ره)'],
        [3, 15, 'قیام پانزده خرداد'],
        [11, 22, 'پیروزی انقلاب اسلامی'],
        [12, 29, 'ملی شدن صنعت نفت'],
    ];

    public static function is_holiday(string $gregorian_date): bool
    {
        return self::label($gregorian_date) !== null;
    }

    public static function label(string $gregorian_date): ?string
    {
        $jalali   = JalaliConverter::to_jalali($gregorian_date);
        $holidays = self::holidays_for_year($jalali['year']);

        foreach ($holidays as $holiday) {
            if ($holiday['month'] === $jalali['month'] && $holiday['day'] === $jalali['day']) {
                return $holiday['label'];
            }
        }

        return null;
    }

    /**
     * @return array<int,array{month:int,day:int,label:string}>
     */
    private static function holidays_for_year(int $jalali_year): array
    {
        $fixed = array_map(
            static fn (array $holiday): array => ['month' => $holiday[0], 'day' => $holiday[1], 'label' => $holiday[2]],
            self::FIXED_HOLIDAYS
        );

        /**
         * @param array<int,array{month:int,day:int,label:string}> $fixed
         */
        return apply_filters('nobatyar_holidays', $fixed, $jalali_year);
    }
}

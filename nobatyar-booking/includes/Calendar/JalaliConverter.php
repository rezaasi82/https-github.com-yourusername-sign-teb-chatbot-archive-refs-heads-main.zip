<?php

namespace Nobatyar\Calendar;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Gregorian <-> Jalali (Solar Hijri) conversion.
 *
 * Booking data is always stored in Gregorian (source of truth); this class is
 * only used at the display/input boundary. Uses the 33-year break-point
 * algorithm (the same one behind jalaali-js / morilog-jalali) rather than a
 * naive 4-year leap cycle, since Jalali leap years don't follow a fixed
 * period and a fixed-cycle approximation drifts by a day every few decades.
 */
class JalaliConverter
{
    public const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    /**
     * @param string $gregorian_date 'Y-m-d'
     * @return array{year:int,month:int,day:int}
     */
    public static function to_jalali(string $gregorian_date): array
    {
        [$gy, $gm, $gd] = self::parse_date($gregorian_date);

        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    /**
     * @return array{year:int,month:int,day:int}
     */
    public static function to_gregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    public static function gregorian_to_jalali_string(string $gregorian_date, string $separator = '/'): string
    {
        $jalali = self::to_jalali($gregorian_date);

        return self::format($jalali['year'], $jalali['month'], $jalali['day'], $separator);
    }

    public static function jalali_to_gregorian_string(string $jalali_date): string
    {
        [$jy, $jm, $jd] = self::parse_date($jalali_date);
        $gregorian      = self::to_gregorian($jy, $jm, $jd);

        return self::format($gregorian['year'], $gregorian['month'], $gregorian['day'], '-');
    }

    public static function is_leap_jalali_year(int $jy): bool
    {
        return self::jal_cal($jy)['leap'] === 0;
    }

    public static function jalali_month_length(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }

        if ($jm <= 11) {
            return 30;
        }

        return self::is_leap_jalali_year($jy) ? 30 : 29;
    }

    public static function month_name(int $jm): string
    {
        return self::MONTH_NAMES[$jm] ?? '';
    }

    private static function format(int $y, int $m, int $d, string $separator): string
    {
        return sprintf('%04d%s%02d%s%02d', $y, $separator, $m, $separator, $d);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function parse_date(string $date): array
    {
        $parts = preg_split('/[\/\-]/', trim($date));

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid date string: {$date}");
        }

        return [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
    }

    // --- Core algorithm (ported from the public-domain jalaali-js break-point method) ---

    private static function div(int $a, int $b): int
    {
        return intdiv($a, $b);
    }

    private static function mod(int $a, int $b): int
    {
        return $a - self::div($a, $b) * $b;
    }

    /**
     * @return array{leap:int,gy:int,march:int}
     */
    private static function jal_cal(int $jy): array
    {
        $breaks = self::BREAKS;
        $bl     = count($breaks);
        $gy     = $jy + 621;
        $leap_j = -14;
        $jp     = $breaks[0];

        if ($jy < $jp || $jy >= $breaks[$bl - 1]) {
            throw new \InvalidArgumentException("Invalid Jalaali year {$jy}");
        }

        $jump = 0;

        for ($i = 1; $i < $bl; $i++) {
            $jm   = $breaks[$i];
            $jump = $jm - $jp;

            if ($jy < $jm) {
                break;
            }

            $leap_j = $leap_j + self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
            $jp     = $jm;
        }

        $n = $jy - $jp;

        $leap_j = $leap_j + self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);

        if (self::mod($jump, 33) === 4 && ($jump - $n) === 4) {
            $leap_j += 1;
        }

        $leap_g = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
        $march  = 20 + $leap_j - $leap_g;

        if (($jump - $n) < 6) {
            $n = $n - $jump + self::div($jump + 4, 33) * 33;
        }

        $leap = self::mod(self::mod($n + 1, 33) - 1, 4);

        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4)
            + self::div(153 * self::mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408;

        $d = $d - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;

        return $d;
    }

    /**
     * @return array{year:int,month:int,day:int}
     */
    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::div(self::mod($j, 1461), 4) * 5 + 308;
        $gd = self::div(self::mod($i, 153), 5) + 1;
        $gm = self::mod(self::div($i, 153), 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);

        return ['year' => $gy, 'month' => $gm, 'day' => $gd];
    }

    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jal_cal($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /**
     * @return array{year:int,month:int,day:int}
     */
    private static function d2j(int $jdn): array
    {
        $gy    = self::d2g($jdn)['year'];
        $jy    = $gy - 621;
        $r     = self::jal_cal($jy);
        $jdn1f = self::g2d($r['gy'], 3, $r['march']);

        $k = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                return [
                    'year'  => $jy,
                    'month' => 1 + self::div($k, 31),
                    'day'   => self::mod($k, 31) + 1,
                ];
            }

            $k -= 186;
        } else {
            $jy -= 1;
            $k  += 179;

            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        return [
            'year'  => $jy,
            'month' => 7 + self::div($k, 30),
            'day'   => self::mod($k, 30) + 1,
        ];
    }
}

<?php

namespace QRCODR\Scans;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deliberately minimal regex-based UA parsing - no external service call,
 * no bundled third-party UA database. Good enough for device/browser/os
 * breakdown charts; not meant to be exhaustive.
 */
class UserAgentParser
{
    public static function device_type($user_agent)
    {
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $user_agent)) {
            return 'bot';
        }
        if (preg_match('/ipad|tablet(?!.*mobile)/i', $user_agent)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|android/i', $user_agent)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function browser($user_agent)
    {
        $map = array(
            'Edg/' => 'Edge',
            'OPR/' => 'Opera',
            'Firefox/' => 'Firefox',
            'Instagram' => 'Instagram In-App',
            'FBAN' => 'Facebook In-App',
            'CriOS' => 'Chrome (iOS)',
            'Chrome/' => 'Chrome',
            'Safari/' => 'Safari',
        );

        foreach ($map as $needle => $label) {
            if (stripos($user_agent, $needle) !== false) {
                return $label;
            }
        }

        return 'Other';
    }

    public static function os($user_agent)
    {
        $map = array(
            'Windows' => 'Windows',
            'Android' => 'Android',
            'iPhone' => 'iOS',
            'iPad' => 'iOS',
            'Mac OS X' => 'macOS',
            'Linux' => 'Linux',
        );

        foreach ($map as $needle => $label) {
            if (stripos($user_agent, $needle) !== false) {
                return $label;
            }
        }

        return 'Other';
    }
}

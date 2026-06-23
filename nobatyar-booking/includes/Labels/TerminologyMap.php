<?php

namespace Nobatyar\Labels;

if (! defined('ABSPATH')) {
    exit;
}

class TerminologyMap
{
    private const DEFAULTS = [
        'provider'      => 'سرویس‌دهنده',
        'service'       => 'خدمت',
        'service_category' => 'دسته خدمت',
        'booking'       => 'نوبت',
        'customer'      => 'مشتری',
        'license_field' => 'شماره مجوز',
    ];

    public static function get(string $key, string $context = ''): string
    {
        $overrides = get_option('nobatyar_terminology_overrides', []);
        $default   = $overrides[$key] ?? self::DEFAULTS[$key] ?? $key;

        return apply_filters('nobatyar_terminology_label', $default, $key, $context);
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }
}

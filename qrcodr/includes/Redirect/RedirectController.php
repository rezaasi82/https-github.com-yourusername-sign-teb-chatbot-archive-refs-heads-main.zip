<?php

namespace QRCODR\Redirect;

use QRCODR\Codes\CodeRepository;
use QRCODR\Scans\ScanLogger;

if (!defined('ABSPATH')) {
    exit;
}

class RedirectController
{
    const QUERY_VAR = 'qrcodr_code';

    public static function register_rewrite_rule()
    {
        add_rewrite_rule('^qr/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public static function register_query_var($vars)
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function handle_request()
    {
        $short_code = get_query_var(self::QUERY_VAR);

        if (empty($short_code)) {
            return;
        }

        $short_code = sanitize_text_field($short_code);
        $repository = new CodeRepository();
        $code = $repository->find_by_short_code($short_code);

        if (!$code || $code->status !== 'active') {
            nocache_headers();
            wp_die(
                esc_html__('این QR Code یافت نشد یا غیرفعال است.', 'qrcodr'),
                esc_html__('یافت نشد', 'qrcodr'),
                array('response' => 404)
            );
        }

        ScanLogger::log($code->id);

        wp_safe_redirect(esc_url_raw($code->destination_url), 302);
        exit;
    }
}

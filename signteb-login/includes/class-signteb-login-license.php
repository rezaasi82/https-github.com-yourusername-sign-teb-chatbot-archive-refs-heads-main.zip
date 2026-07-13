<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Domain lock: the plugin only renders on a site whose activation code
 * matches its domain. Copying the folder to another host disables the
 * design and the login-URL rename (data and settings stay untouched)
 * until a code issued for that domain is entered.
 *
 * Codes are the first 16 hex chars of sha256(host . SALT), grouped in
 * blocks of four. The www prefix is ignored so www/non-www both work.
 */
class SignTeb_Login_License
{
    private const SALT = 'stb#9K2vXr7qLm4eZ8wYd3uHn6pJc5Rf1Tg0Bs';

    public const OPTION = 'signteb_login_license_key';

    public function register(): void
    {
        if (self::is_valid()) {
            return;
        }

        add_action('admin_notices', [$this, 'render_unlicensed_notice']);
    }

    public static function is_valid(): bool
    {
        $stored = strtoupper(trim((string) get_option(self::OPTION, '')));

        return $stored !== '' && hash_equals(self::key_for_host(self::host()), $stored);
    }

    public static function host(): string
    {
        $host = strtolower((string) parse_url(home_url(), PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host);
    }

    public static function key_for_host(string $host): string
    {
        $raw = strtoupper(substr(hash('sha256', $host . self::SALT), 0, 16));

        return implode('-', str_split($raw, 4));
    }

    public function render_unlicensed_notice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>SignTeb Login:</strong> %s <a href="%s">%s</a></p></div>',
            esc_html(sprintf(
                /* translators: %s: site host name. */
                __('کد فعال‌سازی برای دامنه %s وارد نشده یا نامعتبر است؛ طراحی صفحه ورود غیرفعال است. کد را از SignTeb.com دریافت کنید.', 'signteb-login'),
                self::host()
            )),
            esc_url(admin_url('options-general.php?page=signteb-login')),
            esc_html__('ورود کد فعال‌سازی', 'signteb-login')
        );
    }
}

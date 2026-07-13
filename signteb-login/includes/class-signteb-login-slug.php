<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renames the login URL: when a slug is configured in Settings > SignTeb
 * Login, the login screen is served from home_url('/{slug}') and direct
 * requests to wp-login.php answer 404. Works without pretty permalinks
 * because the request is intercepted from REQUEST_URI before WP routing,
 * and every core-generated wp-login.php link is rewritten through the
 * site_url / network_site_url / wp_redirect filters, so logout, lost
 * password and interim re-auth keep working through the new address.
 */
class SignTeb_Login_Slug
{
    private bool $block_wp_login = false;
    private bool $serve_login = false;

    public static function slug(): string
    {
        $slug = get_option('signteb_login_slug', '');
        $slug = is_string($slug) ? sanitize_title($slug) : '';

        // Paths that must keep their core meaning.
        if (in_array($slug, ['wp-admin', 'wp-content', 'wp-includes', 'wp-login-php'], true)) {
            return '';
        }

        return $slug;
    }

    public function register(): void
    {
        if (self::slug() === '') {
            return;
        }

        add_action('plugins_loaded', [$this, 'intercept_request'], 2);
        add_action('wp_loaded', [$this, 'serve_or_block'], 1);
        add_filter('site_url', [$this, 'rewrite_login_url'], 10, 2);
        add_filter('network_site_url', [$this, 'rewrite_login_url'], 10, 2);
        add_filter('wp_redirect', [$this, 'rewrite_login_url'], 10, 1);
    }

    public function rewrite_login_url($url, $path = ''): string
    {
        if (is_string($url) && strpos($url, 'wp-login.php') !== false) {
            $url = str_replace('wp-login.php', self::slug(), $url);
        }

        return $url;
    }

    public function intercept_request(): void
    {
        $request_path = (string) parse_url(rawurldecode($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $request_path = untrailingslashit($request_path);
        $home_path    = untrailingslashit((string) parse_url(home_url(), PHP_URL_PATH));

        if ($request_path === $home_path . '/' . self::slug()) {
            // Make WordPress treat this request as the login page so
            // login_enqueue_scripts, login_body_class etc. all fire.
            global $pagenow;
            $pagenow           = 'wp-login.php';
            $this->serve_login = true;

            return;
        }

        if (strpos($request_path, 'wp-login.php') !== false) {
            $this->block_wp_login = true;
        }
    }

    public function serve_or_block(): void
    {
        if ($this->block_wp_login) {
            status_header(404);
            nocache_headers();
            wp_die(
                esc_html__('صفحه مورد نظر یافت نشد.', 'signteb-login'),
                esc_html__('یافت نشد', 'signteb-login'),
                ['response' => 404]
            );
        }

        if ($this->serve_login) {
            global $error, $interim_login, $action, $user_login;

            require_once ABSPATH . 'wp-login.php';
            die;
        }
    }
}

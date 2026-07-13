<?php

namespace Nobatyar\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Replaces the stock wp-login.php look with a branded, Persian-first design:
 * gradient backdrop, glass card, custom logo and RTL-friendly typography.
 * The stylesheet only loads on the login screen itself, so the sitewide
 * conditional-asset-loading rule is preserved by construction.
 */
class LoginCustomizer
{
    public function register(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        add_action('login_enqueue_scripts', [$this, 'enqueue_styles']);
        add_filter('login_headerurl', [$this, 'header_url']);
        add_filter('login_headertext', [$this, 'header_text']);
        add_filter('login_body_class', [$this, 'body_class'], 10, 2);
        add_filter('login_message', [$this, 'welcome_message']);
    }

    /**
     * Enabled by default; site owners can switch it off with the option or
     * developers with the filter, without touching the rest of the plugin.
     */
    private function is_enabled(): bool
    {
        $enabled = get_option('nobatyar_login_customizer_enabled', '1') === '1';

        return (bool) apply_filters('nobatyar_login_customizer_enabled', $enabled);
    }

    public function enqueue_styles(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        wp_enqueue_style(
            'nobatyar-login',
            NOBATYAR_PLUGIN_URL . 'assets/css/login.css',
            [],
            NOBATYAR_VERSION
        );
    }

    public function header_url(): string
    {
        return home_url('/');
    }

    public function header_text(): string
    {
        return get_bloginfo('name', 'display');
    }

    /**
     * Scopes every style rule to body.nobatyar-login so nothing leaks into
     * the interim re-auth iframe or other plugins' login variants.
     */
    public function body_class(array $classes, string $action): array
    {
        if (! $this->is_interim_login()) {
            $classes[] = 'nobatyar-login';
        }

        return $classes;
    }

    public function welcome_message(string $message): string
    {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : 'login';

        if ($action !== 'login' || $this->is_interim_login()) {
            return $message;
        }

        $welcome = sprintf(
            /* translators: %s: site name. */
            __('به %s خوش آمدید — برای مدیریت نوبت‌ها وارد شوید.', 'nobatyar-booking'),
            get_bloginfo('name', 'display')
        );

        return '<p class="nobatyar-login-welcome">' . esc_html($welcome) . '</p>' . $message;
    }

    private function is_interim_login(): bool
    {
        return isset($_REQUEST['interim-login']);
    }
}

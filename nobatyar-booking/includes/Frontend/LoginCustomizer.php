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
        add_action('login_footer', [$this, 'render_floating_icons']);
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

    /**
     * Decorative booking-themed icons drifting behind the form. Purely
     * visual: fixed-position, pointer-events:none, aria-hidden, and the
     * stylesheet freezes them under prefers-reduced-motion.
     */
    public function render_floating_icons(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        $svg = [
            'calendar' => '<rect x="3" y="4.5" width="18" height="17" rx="3"/><path d="M3 9.5h18"/><path d="M8 2.5v4M16 2.5v4"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
            'bell'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 20a2 2 0 0 1-3.4 0"/>',
            'star'     => '<path d="M12 2.5l2.9 5.9 6.5 1-4.7 4.6 1.1 6.5-5.8-3.1-5.8 3.1 1.1-6.5L2.6 9.4l6.5-1z"/>',
            'check'    => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.3l2.3 2.3 4.7-4.9"/>',
            'ticket'   => '<path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M13 5v2M13 11v2M13 17v2"/>',
        ];

        echo '<div class="nobatyar-login-icons" aria-hidden="true">';

        foreach ($svg as $name => $paths) {
            printf(
                '<span class="nobatyar-icon nobatyar-icon-%1$s"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">%2$s</svg></span>',
                esc_attr($name),
                $paths // Static SVG path markup defined above, not user input.
            );
        }

        echo '</div>';
    }

    private function is_interim_login(): bool
    {
        return isset($_REQUEST['interim-login']);
    }
}

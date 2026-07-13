<?php
/**
 * Plugin Name:       SignTeb Login — صفحه ورود اختصاصی
 * Plugin URI:        https://signteb.com
 * Description:       صفحه ورود وردپرس با طراحی اختصاصی VIP برای سایت‌هایی که SIGNTEB.com طراحی می‌کند: تم تیره، لهجه‌های طلایی و ایکون‌های شناور.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       signteb-login
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SIGNTEB_LOGIN_VERSION', '1.0.0');
define('SIGNTEB_LOGIN_URL', plugin_dir_url(__FILE__));

/**
 * Standalone white-label login screen for client sites built by SIGNTEB.com.
 * No dependency on any other plugin: drop the folder into wp-content/plugins,
 * activate, done. The stylesheet only loads on wp-login.php, the client
 * site's own name appears in the welcome copy, and a small "designed by
 * SIGNTEB.com" credit sits under the form.
 */
class SignTeb_Login
{
    public function register(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        add_action('login_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('login_footer', [$this, 'render_floating_icons']);
        add_action('login_footer', [$this, 'render_credit']);
        add_filter('login_headerurl', [$this, 'header_url']);
        add_filter('login_headertext', [$this, 'header_text']);
        add_filter('login_body_class', [$this, 'body_class'], 10, 2);
        add_filter('login_message', [$this, 'welcome_message']);
    }

    /**
     * Enabled by default on every site we ship; a client-specific opt-out is
     * one option or filter away, no code changes in the plugin itself.
     */
    private function is_enabled(): bool
    {
        $enabled = get_option('signteb_login_enabled', '1') === '1';

        return (bool) apply_filters('signteb_login_enabled', $enabled);
    }

    public function enqueue_styles(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        wp_enqueue_style(
            'signteb-login',
            SIGNTEB_LOGIN_URL . 'assets/css/login.css',
            [],
            SIGNTEB_LOGIN_VERSION
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
     * Scopes every style rule to body.signteb-login so nothing leaks into
     * the interim re-auth iframe or other plugins' login variants.
     */
    public function body_class(array $classes, string $action): array
    {
        if (! $this->is_interim_login()) {
            $classes[] = 'signteb-login';
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
            __('به پنل مدیریت %s خوش آمدید.', 'signteb-login'),
            get_bloginfo('name', 'display')
        );

        return '<p class="signteb-login-welcome">' . esc_html($welcome) . '</p>' . $message;
    }

    /**
     * Decorative web-design-themed icons drifting behind the form. Purely
     * visual: fixed-position, pointer-events:none, aria-hidden, and the
     * stylesheet freezes them under prefers-reduced-motion.
     */
    public function render_floating_icons(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        $svg = [
            'code'    => '<path d="M8 7l-5 5 5 5"/><path d="M16 7l5 5-5 5"/>',
            'pen'     => '<path d="M17.5 2.5l4 4L8 20l-5.5 1.5L4 16z"/><path d="M14.5 5.5l4 4"/>',
            'layers'  => '<path d="M12 2l10 6-10 6L2 8z"/><path d="M2 14l10 6 10-6"/>',
            'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'sparkle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
            'star'    => '<path d="M12 2.5l2.9 5.9 6.5 1-4.7 4.6 1.1 6.5-5.8-3.1-5.8 3.1 1.1-6.5L2.6 9.4l6.5-1z"/>',
        ];

        echo '<div class="signteb-login-icons" aria-hidden="true">';

        foreach ($svg as $name => $paths) {
            printf(
                '<span class="signteb-icon signteb-icon-%1$s"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">%2$s</svg></span>',
                esc_attr($name),
                $paths // Static SVG path markup defined above, not user input.
            );
        }

        echo '</div>';
    }

    /**
     * Small agency credit under the form. Hideable per client with the
     * signteb_login_show_credit option/filter.
     */
    public function render_credit(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        $show = get_option('signteb_login_show_credit', '1') === '1';

        if (! apply_filters('signteb_login_show_credit', $show)) {
            return;
        }

        printf(
            '<p class="signteb-login-credit">%s <a href="%s" target="_blank" rel="noopener">SIGNTEB.com</a></p>',
            esc_html__('طراحی و توسعه:', 'signteb-login'),
            esc_url('https://signteb.com')
        );
    }

    private function is_interim_login(): bool
    {
        return isset($_REQUEST['interim-login']);
    }
}

add_action('plugins_loaded', static function () {
    (new SignTeb_Login())->register();
});

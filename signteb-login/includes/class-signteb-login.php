<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The branded login screen. The stylesheet loads only on wp-login.php,
 * every rule is scoped to body.signteb-login, and the interim re-auth
 * iframe keeps the stock WordPress look. Colors, logo and the credit
 * line are read from the options set in Settings > SignTeb Login.
 */
class SignTeb_Login
{
    public const DEFAULT_ACCENT        = '#d4a72c';
    public const DEFAULT_ACCENT_BRIGHT = '#f2cc60';

    private const LOGO_HEIGHT_DEFAULT = 96;
    private const LOGO_HEIGHT_MIN     = 40;
    private const LOGO_HEIGHT_MAX     = 320;
    private const LOGO_WIDTH_MAX      = 320;

    public function register(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        add_action('login_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('login_head', [$this, 'print_override_styles'], PHP_INT_MAX);
        add_action('login_footer', [$this, 'render_floating_icons']);
        add_action('login_footer', [$this, 'render_credit']);
        add_filter('login_headerurl', [$this, 'header_url']);
        add_filter('login_headertext', [$this, 'header_text']);
        add_filter('login_body_class', [$this, 'body_class']);
        add_filter('login_message', [$this, 'welcome_message']);
    }

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

    /**
     * Prints the per-site overrides as the very last thing in the login
     * <head> so they also outrank login styles injected by themes or
     * other plugins.
     */
    public function print_override_styles(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        $overrides = $this->color_overrides() . $this->logo_overrides();

        if ($overrides !== '') {
            echo '<style id="signteb-login-overrides">' . $overrides . '</style>' . "\n";
        }
    }

    public function header_url(): string
    {
        return home_url('/');
    }

    public function header_text(): string
    {
        return get_bloginfo('name', 'display');
    }

    public function body_class(array $classes): array
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
     * Decorative icons drifting behind the form: fixed-position,
     * pointer-events:none, aria-hidden, frozen under
     * prefers-reduced-motion.
     */
    public function render_floating_icons(): void
    {
        if ($this->is_interim_login()) {
            return;
        }

        $icons = [
            'cross'   => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
            'pulse'   => '<path d="M3 12h4l2.5-6 4 12 2.5-6h5"/>',
            'pill'    => '<rect x="4" y="9" width="16" height="6" rx="3" transform="rotate(-45 12 12)"/><path d="M10.6 10.6l2.8 2.8"/>',
            'code'    => '<path d="M8 7l-5 5 5 5"/><path d="M16 7l5 5-5 5"/>',
            'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'star'    => '<path d="M12 2.5l2.9 5.9 6.5 1-4.7 4.6 1.1 6.5-5.8-3.1-5.8 3.1 1.1-6.5L2.6 9.4l6.5-1z"/>',
        ];

        echo '<div class="signteb-login-icons" aria-hidden="true">';

        foreach ($icons as $name => $paths) {
            printf(
                '<span class="signteb-icon signteb-icon-%1$s"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">%2$s</svg></span>',
                esc_attr($name),
                $paths
            );
        }

        echo '</div>';
    }

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
            '<p class="signteb-login-credit">%s <a href="%s" target="_blank" rel="noopener">SignTeb.com</a></p>',
            esc_html__('طراحی و توسعه:', 'signteb-login'),
            esc_url('https://signteb.com')
        );
    }

    private function color_overrides(): string
    {
        $accent = sanitize_hex_color(get_option('signteb_login_accent', '')) ?: self::DEFAULT_ACCENT;
        $bright = sanitize_hex_color(get_option('signteb_login_accent_bright', '')) ?: self::DEFAULT_ACCENT_BRIGHT;

        if ($accent === self::DEFAULT_ACCENT && $bright === self::DEFAULT_ACCENT_BRIGHT) {
            return '';
        }

        $css = sprintf(
            'body.signteb-login{--stb-accent:%1$s;--stb-accent-bright:%2$s;--stb-accent-rgb:%3$s;--stb-accent-bright-rgb:%4$s;}',
            $accent,
            $bright,
            $this->hex_to_rgb($accent),
            $this->hex_to_rgb($bright)
        );

        if ((string) get_option('signteb_login_logo_url', '') === '') {
            // Recolor the built-in mark so it doesn't stay gold.
            $css .= sprintf(
                'body.signteb-login #login h1 a,body.signteb-login .wp-login-logo a{background-image:url("%s");}',
                $this->default_mark_data_uri($bright)
            );
        }

        return $css;
    }

    /**
     * When a custom logo is set, its box replaces the square badge:
     * transparent, no border/glow, and sized to the image's own aspect
     * ratio when the dimensions are known. A box that matches the image
     * shape can never crop it, whatever background-size other login
     * styles try to force — a circular logo gets a square box, a wide
     * logo a wide one.
     */
    private function logo_overrides(): string
    {
        $logo = esc_url_raw((string) get_option('signteb_login_logo_url', ''));

        if ($logo === '') {
            return '';
        }

        [$width_css, $height_css] = $this->logo_box_dimensions($logo);

        return sprintf(
            'html body.login.signteb-login #login h1 a,html body.login.signteb-login .wp-login-logo a{display:block;%1$s%2$smin-width:0!important;margin:0 auto 14px!important;padding:0!important;background:transparent url("%3$s") center/contain no-repeat!important;border:none!important;border-radius:0!important;box-shadow:none!important;overflow:visible!important;}'
            . 'html body.login.signteb-login #login h1::after,html body.login.signteb-login .wp-login-logo::after{content:none;}',
            $width_css,
            $height_css,
            esc_url($logo)
        );
    }

    /**
     * @return array{0: string, 1: string} width and height declarations.
     */
    private function logo_box_dimensions(string $logo): array
    {
        $height = (int) get_option('signteb_login_logo_height', self::LOGO_HEIGHT_DEFAULT);
        $height = max(self::LOGO_HEIGHT_MIN, min(self::LOGO_HEIGHT_MAX, $height ?: self::LOGO_HEIGHT_DEFAULT));

        $ratio = $this->logo_aspect_ratio($logo);

        if ($ratio === null) {
            // Unknown dimensions (external URL, SVG without metadata):
            // full-width box; center/contain still shows the whole image.
            return [
                'width:100%!important;max-width:100%!important;',
                sprintf('height:%dpx!important;', $height),
            ];
        }

        $width = (int) round(min(self::LOGO_WIDTH_MAX, $height * $ratio));

        return [
            sprintf('width:%dpx!important;max-width:100%%!important;', $width),
            sprintf('height:%dpx!important;', (int) round($width / $ratio)),
        ];
    }

    private function logo_aspect_ratio(string $logo): ?float
    {
        $attachment_id = attachment_url_to_postid($logo);

        if (! $attachment_id) {
            return null;
        }

        $meta = wp_get_attachment_metadata($attachment_id);

        if (empty($meta['width']) || empty($meta['height'])) {
            return null;
        }

        return $meta['width'] / $meta['height'];
    }

    private function hex_to_rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return sprintf(
            '%d, %d, %d',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    private function default_mark_data_uri(string $color): string
    {
        $c = rawurlencode($color);

        return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='{$c}' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8.5 2.5h5v5.5H19v5h-5.5V19h-5v-6H3v-5h5.5z'/%3E%3Ccircle cx='15' cy='15.5' r='3.6'/%3E%3Cpath d='M17.6 13l2.7-2.7'/%3E%3Ccircle cx='21' cy='9.6' r='1.1' fill='{$c}' stroke='none'/%3E%3Cpath d='M14.6 11.9l-.4-3.2'/%3E%3Ccircle cx='14' cy='7.6' r='1.1' fill='{$c}' stroke='none'/%3E%3C/svg%3E";
    }

    private function is_interim_login(): bool
    {
        return isset($_REQUEST['interim-login']);
    }
}

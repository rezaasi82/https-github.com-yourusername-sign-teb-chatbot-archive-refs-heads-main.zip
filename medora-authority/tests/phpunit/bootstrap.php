<?php

declare(strict_types=1);

/**
 * Unit-test bootstrap.
 *
 * These tests deliberately run *without* a WordPress install. The classes
 * under test here — text processing, vector maths, scoring arithmetic,
 * citation formatting — are the parts where a bug is silent and expensive, and
 * they should be testable in milliseconds on any machine and in CI without
 * provisioning MySQL.
 *
 * The handful of WordPress functions those classes touch are shimmed below.
 * Anything that genuinely needs WordPress belongs in the integration suite,
 * which runs against a real install.
 *
 * @package Medora\Authority\Tests
 */

define('ABSPATH', __DIR__ . '/');
define('MEDORA_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
define('MEDORA_PLUGIN_URL', 'https://example.test/wp-content/plugins/medora-authority/');
define('MEDORA_PLUGIN_FILE', MEDORA_PLUGIN_DIR . 'medora-authority.php');
define('MEDORA_PLUGIN_BASENAME', 'medora-authority/medora-authority.php');

const MEDORA_VERSION    = '0.1.0';
const MEDORA_DB_VERSION = '1.0.0';

const MINUTE_IN_SECONDS = 60;
const HOUR_IN_SECONDS   = 3600;
const DAY_IN_SECONDS    = 86400;
const WEEK_IN_SECONDS   = 604800;
const MONTH_IN_SECONDS  = 2592000;
const YEAR_IN_SECONDS   = 31536000;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// --- WordPress shims ------------------------------------------------------
//
// Only the functions the unit-tested classes actually call. Each behaves
// closely enough to the real implementation for the assertions that depend on
// it; anything subtler is a sign the class needs an integration test instead.

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        return $number === 1 ? $single : $plural;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url_raw')) {
    /**
     * Mirrors the part of WordPress's behaviour the tests depend on: only the
     * protocols core allows survive, everything else becomes empty.
     */
    function esc_url_raw(string $url): string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '/')) {
            return $url;
        }

        return preg_match('#^(https?|ftp|mailto):#i', $url) === 1 ? $url : '';
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
    {
        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);

        return $removeBreaks ? trim((string) preg_replace('/[\r\n\t ]+/', ' ', $text)) : trim($text);
    }
}

if (! function_exists('strip_shortcodes')) {
    function strip_shortcodes(string $content): string
    {
        return (string) preg_replace('/\[[^\]]*\]/', ' ', $content);
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = '/'): string
    {
        return 'https://example.test' . $path;
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string
    {
        return $show === 'name' ? 'Example Site' : '';
    }
}

if (! function_exists('get_option')) {
    /**
     * Reads from `$GLOBALS['medora_test_options']` so a test can stand up a
     * configuration without replacing `Options`, which is final on purpose.
     */
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['medora_test_options'][$option] ?? $default;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/', ' ', strip_tags($value)));
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }
}

if (! class_exists('WP_Post')) {
    /**
     * Minimal stand-in for WordPress's `WP_Post`.
     *
     * Only the property-copying constructor matters here: the unit suite passes
     * posts to analysers that read `post_title` and `post_content` and nothing
     * else. The integration suite runs against the real class.
     */
    class WP_Post
    {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public string $post_status = 'publish';
        public string $post_type = 'post';
        public int $post_author = 0;

        public function __construct(object $post = new stdClass())
        {
            foreach (get_object_vars($post) as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
}

<?php
/**
 * Render the real admin views outside WordPress so screenshots can never drift
 * from the shipped markup. WordPress functions are stubbed to the minimum each
 * view touches; the view files themselves are included unmodified.
 *
 *   php render.php <view> > out.html
 */

define('ABSPATH', '/wp/');
define('PZK_DIR', dirname(__DIR__, 2) . '/pezhkam/');
define('PZK_URL', 'assets/');
define('PZK_VERSION', '1.0.0');
define('PZK_BASENAME', 'pezhkam/pezhkam.php');
define('HOUR_IN_SECONDS', 3600);

// ---------------------------------------------------------------- WP stubs
function esc_html($s)       { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s)       { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s)        { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($s)    { return (string) $s; }
function esc_textarea($s)   { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_kses_post($s)   { return (string) $s; }
function __($s, $d = null)          { return $s; }
function esc_html__($s, $d = null)  { return esc_html($s); }
function esc_attr__($s, $d = null)  { return esc_attr($s); }
function _e($s, $d = null)          { echo $s; }
function esc_html_e($s, $d = null)  { echo esc_html($s); }
function esc_attr_e($s, $d = null)  { echo esc_attr($s); }
function _n($a, $b, $n, $d = null)  { return $n === 1 ? $a : $b; }
function _x($s, $c, $d = null)      { return $s; }
function admin_url($p = '')         { return '#' . $p; }
function home_url($p = '')          { return '#'; }
function rest_url($p = '')          { return '#' . $p; }
function wp_create_nonce($a = '')   { return 'nonce'; }
function wp_nonce_field()           { echo ''; }
function current_user_can()         { return true; }
function get_option($k, $d = false) { return $d; }
function checked($a, $b = true, $e = true)  { $r = ((string) $a === (string) $b) ? ' checked' : ''; if ($e) { echo $r; } return $r; }
function selected($a, $b = true, $e = true) { $r = ((string) $a === (string) $b) ? ' selected' : ''; if ($e) { echo $r; } return $r; }
function disabled($a, $b = true, $e = true) { return ''; }
function add_query_arg($k, $v = null, $u = '') { return is_array($k) ? $u : $u . '&' . $k . '=' . $v; }
function number_format_i18n($n, $dec = 0) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace(range(0, 9), $fa, number_format((float) $n, $dec));
}
function wp_date($f, $t = null, $tz = null) { return '۱۴۰۴/۰۵/۱۸'; }
function mysql2date($f, $d, $tr = true)  { return '۱۴۰۴/۰۵/۱۸ - ۱۴:۳۰'; }
function paginate_links($a = []) {
    return '<span class="page-numbers current">۱</span>'
         . '<a class="page-numbers" href="#">۲</a>'
         . '<a class="page-numbers" href="#">۳</a>';
}
function get_bloginfo($k = '') { return 'کلینیک نمونه'; }
function date_i18n($f, $t = null)   { return '۱۴۰۴/۰۵/۱۸'; }
function current_time($t = '')      { return $t === 'Hi' ? '1430' : time(); }
function settings_errors($s = '')   { }
function submit_button($t = null)   { echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html($t ?: 'ذخیره تغییرات') . '</button></p>'; }
function do_action() { }
function apply_filters($t, $v) { return $v; }
function sanitize_text_field($s) { return $s; }
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE); }
function human_time_diff($a, $b = null) { return '۲ ساعت'; }
function get_locale() { return 'fa_IR'; }
function size_format($b) { return '۱۲ کیلوبایت'; }

// -------------------------------------------------- plugin classes we reuse
spl_autoload_register(static function ($class) {
    if (strpos($class, 'Pezhkam\\') !== 0) {
        return;
    }
    $parts = explode('\\', substr($class, 8));
    $short = array_pop($parts);
    $dir   = implode('/', array_map('strtolower', $parts));
    $slug  = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    $file  = PZK_DIR . 'includes/' . ($dir !== '' ? $dir . '/' : '') . 'class-' . $slug . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/stubs.php';



/** Enough of $wpdb for the list views: every query returns no rows. */
class PZK_Fake_Wpdb
{
    public $prefix = 'wp_';
    public function get_results($q = null, $o = null) { return []; }
    public function get_row($q = null, $o = null)     { return null; }
    public function get_var($q = null)                { return 0; }
    public function get_col($q = null)                { return []; }
    public function prepare($q, ...$a)                { return $q; }
    public function query($q)                         { return 0; }
}
$GLOBALS['wpdb'] = new PZK_Fake_Wpdb();

require_once __DIR__ . '/data.php';

$view = $argv[1] ?? 'premium-dashboard';
$viewTab = null;
if (strpos($view, ':') !== false) { [$view, $viewTab] = explode(':', $view, 2); }
$__d = demo_data($view);
if ($viewTab !== null) { $__d['tab'] = $viewTab; }
extract($__d, EXTR_SKIP);

if ($view === 'widget' || $view === 'widget-teaser') {
    $config = demo_widget_config();
    if ($view === 'widget-teaser') { $config['inline'] = false; }
    ob_start();
    require PZK_DIR . 'templates/widget.php';
    $markup = ob_get_clean();
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
       . '<link rel="stylesheet" href="assets/fonts/font.css">'
       . '<link rel="stylesheet" href="assets/css/widget.css">'
       . '<style>body{margin:0;background:#eef1f6;padding:26px;width:max-content;}'
       . '.pzk-root{position:static!important;}'
       // Let the panel grow to its content so the whole exchange and the
       // channel card are in frame instead of scrolled out of view.
       // .pzk-inline .pzk-panel pins the shortcode panel to 520px; the mock
       // needs the same selector weight to let it grow for the capture.
       . '.pzk-root.pzk-inline{max-width:400px;}'
       . '.pzk-inline .pzk-panel{position:static;width:400px;height:auto;max-height:none;animation:none;}'
       . '.pzk-body{display:block;}'
       . '.pzk-messages{overflow:visible;max-height:none;}'
       . ($view === 'widget-teaser'
            ? '.pzk-root{position:static!important;display:flex;align-items:center;gap:14px;}'
              . '.pzk-panel{display:none!important;}'
              . '.pzk-teaser{position:static!important;display:flex!important;}'
            : '.pzk-launcher,.pzk-teaser{display:none!important;}')
       . '</style></head><body>'
       . $markup . ($view === 'widget' ? file_get_contents(__DIR__ . '/widget-convo.html') : '') . '</body></html>';
    return;
}

[$chromeOpen, $chromeClose] = demo_chrome($view, $settings ?? null, $viewTab);

ob_start();
require PZK_DIR . 'includes/admin/views/' . $view . '.php';
$body = $chromeOpen . ob_get_clean() . $chromeClose;

$css = demo_css($view);
$links = '';
foreach ($css as $href) {
    $links .= '<link rel="stylesheet" href="' . $href . '">';
}

echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
   . '<link rel="stylesheet" href="assets/fonts/font.css">' . $links
   . '<style>' . demo_page_css($view) . '</style></head><body>'
   . $body
   . ($view === 'premium-dashboard' ? '<script src="assets/js/dashboard.js"></script>' : '')
   . ($view === 'crm-board' ? '<script src="assets/js/board.js"></script>' : '')
   . '</body></html>';

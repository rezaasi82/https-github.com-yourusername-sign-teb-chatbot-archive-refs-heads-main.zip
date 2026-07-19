<?php
/**
 * Plugin Name:       SignTeb Fatty Liver Calculator
 * Plugin URI:        https://signteb.com
 * Description:       Advanced clinical screening tool utilizing the Fatty Liver Index (FLI)
 *                    and lifestyle metrics. Premium standalone medical widget with lead
 *                    generation, dynamic CTA, and real-time BMI/FLI calculation.
 * Version:           1.0.0
 * Author:            SignTeb
 * Author URI:        https://signteb.com
 * Text Domain:       signteb-liver-calc
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * License:           GPL v2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'SIGNTEB_LIVER_VERSION', '1.0.0' );
define( 'SIGNTEB_LIVER_PATH',    plugin_dir_path( __FILE__ ) );
define( 'SIGNTEB_LIVER_URL',     plugin_dir_url( __FILE__ ) );
define( 'SIGNTEB_LIVER_TABLE',   'signteb_liver_leads' );

/* ── Load sub-classes ─────────────────────────────────────────── */
foreach ( array( 'class-signteb-db', 'class-signteb-sms', 'class-signteb-ajax' ) as $_st_cls ) {
    $p = SIGNTEB_LIVER_PATH . 'includes/' . $_st_cls . '.php';
    if ( file_exists( $p ) ) {
        require_once $p;
    }
}
unset( $_st_cls, $p );

/* ── Activation hook ──────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    if ( class_exists( 'SignTeb_Liver_DB' ) ) {
        SignTeb_Liver_DB::create_table();
    }
} );

/* ════════════════════════════════════════════════════════════════
   Main Plugin Class
   ════════════════════════════════════════════════════════════════ */
final class SignTeb_Fatty_Liver_Calculator {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',                              [ $this, 'load_textdomain' ] );
        add_shortcode( 'fatty_liver_calculator',         [ $this, 'render_calculator' ] );
        add_action( 'wp_enqueue_scripts',                [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu',                        [ $this, 'admin_menu' ] );
        add_action( 'admin_init',                        [ $this, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
                    [ $this, 'action_links' ] );
        if ( class_exists( 'SignTeb_Liver_Ajax' ) ) {
            new SignTeb_Liver_Ajax();
        }
    }

    /* ── i18n ─────────────────────────────────────────────────── */
    public function load_textdomain() {
        load_plugin_textdomain(
            'signteb-liver-calc',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /* ── Asset Enqueueing ─────────────────────────────────────── */
    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ||
             ! has_shortcode( $post->post_content, 'fatty_liver_calculator' ) ) {
            return;
        }

        /* Tailwind Play CDN — scoped to #signteb-calc-wrapper */
        wp_enqueue_script( 'tailwindcss-cdn', 'https://cdn.tailwindcss.com', [], null, false );
        wp_add_inline_script( 'tailwindcss-cdn',
            'tailwind.config = { important:"#signteb-calc-wrapper", theme:{ extend:{
                fontFamily:{ vazir:["Vazirmatn","Segoe UI","system-ui","sans-serif"] }
            }}};', 'after' );

        /* Vazirmatn Persian font */
        wp_enqueue_style( 'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
            [], null );

        /* Chart.js v4 */
        wp_enqueue_script( 'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [], '4.4.0', true );

        /* Plugin CSS */
        wp_enqueue_style( 'signteb-liver-calc',
            SIGNTEB_LIVER_URL . 'assets/css/signteb-calculator.css',
            [ 'vazirmatn-font' ], SIGNTEB_LIVER_VERSION );

        /* Plugin JS */
        wp_enqueue_script( 'signteb-liver-calc',
            SIGNTEB_LIVER_URL . 'assets/js/signteb-calculator.js',
            [ 'jquery', 'chartjs' ], SIGNTEB_LIVER_VERSION, true );

        /* Pass data to JS */
        global $wpdb;
        $st_tbl      = $wpdb->prefix . SIGNTEB_LIVER_TABLE;
        $st_real     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$st_tbl}`" ); // 0 if table absent
        $st_eval_cnt = (int) get_option( 'signteb_liver_eval_base', 1240 ) + $st_real;

        wp_localize_script( 'signteb-liver-calc', 'signTebConfig', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'signteb_liver_nonce' ),
            'waNumber' => esc_attr( get_option( 'signteb_liver_wa_number', '989191182649' ) ),
            'otpEnabled' => ( '1' === get_option( 'signteb_liver_sms_enabled', '0' ) ),
            'evalCount'  => $st_eval_cnt,
            'i18n'     => [
                'loading'       => __( 'Analysing clinical data...', 'signteb-liver-calc' ),
                'errorPhone'    => __( 'Invalid mobile number. Format: 09XXXXXXXXX', 'signteb-liver-calc' ),
                'errorRequired' => __( 'Please fill in all required fields.', 'signteb-liver-calc' ),
                'errorRange'    => __( 'Value is outside the valid range.', 'signteb-liver-calc' ),
                'errorServer'   => __( 'Server error. Please try again.', 'signteb-liver-calc' ),
                'submitting'    => __( 'Submitting...', 'signteb-liver-calc' ),
                'unlock'        => __( 'View Full Results — Free', 'signteb-liver-calc' ),
            ],
        ] );
    }

    /* ── Shortcode ────────────────────────────────────────────── */
    public function render_calculator() {
        ob_start();
        $tpl = SIGNTEB_LIVER_PATH . 'templates/calculator-template.php';
        file_exists( $tpl )
            ? include $tpl
            : print '<p style="color:red">SignTeb Liver Calc: template not found.</p>';
        return ob_get_clean();
    }

    /* ── Admin Menu ───────────────────────────────────────────── */
    public function admin_menu() {
        add_menu_page(
            'SignTeb Liver Leads', 'Liver Leads', 'manage_options',
            'signteb-liver-leads', [ $this, 'page_leads' ],
            'dashicons-heart', 56
        );
        add_submenu_page( 'signteb-liver-leads', 'Settings', 'Settings',
            'manage_options', 'signteb-liver-settings', [ $this, 'page_settings' ] );
    }

    /* ── Settings Registration ────────────────────────────────── */
    public function register_settings() {
        $options = [
            /* Contact & WhatsApp */
            'signteb_liver_wa_number'      => '989191182649',
            'signteb_liver_admin_email'    => get_option( 'admin_email' ),
            'signteb_liver_phone'          => '',
            /* CTA Block */
            'signteb_liver_cta_title'      => 'برای مشاوره تخصصی با ما تماس بگیرید',
            'signteb_liver_cta_desc'       => 'پس از مشاهده نتایج غربالگری، مشاوره با متخصص گوارش را جدی بگیرید.',
            'signteb_liver_cta_btn'        => 'مشاوره فوری',
            'signteb_liver_appointment_url'=> '#',
            'signteb_liver_address'        => '',
            'signteb_liver_hours'          => '',
            'signteb_liver_doctor_name'    => '',
            'signteb_liver_clinic_name'    => '',
            /* SMS / OTP — dynamic, provider-agnostic, pattern-based */
            'signteb_liver_sms_enabled'        => '0',
            'signteb_liver_sms_provider'       => 'kavenegar',
            'signteb_liver_sms_api_key'        => '',
            'signteb_liver_sms_api_secret'     => '',
            'signteb_liver_sms_sender'         => '',
            /* OTP pattern + body */
            'signteb_liver_sms_otp_pattern'    => '',
            'signteb_liver_sms_otp_text'       => 'کد تأیید شما {0} می‌باشد. لطفاً آن را در اختیار دیگران قرار ندهید',
            /* Admin line + pattern */
            'signteb_liver_sms_admin_mobile'   => '',
            'signteb_liver_sms_admin_pattern'  => '',
            'signteb_liver_sms_admin_text'     => 'درخواست مشاوره جدید. نام: {NAME} - موبایل: {MOBILE} - گرید: {GRADE}',
            /* Secretary line + its OWN pattern */
            'signteb_liver_sms_secretary_mobile'  => '',
            'signteb_liver_sms_secretary_pattern' => '',
            'signteb_liver_sms_secretary_text'    => 'بیمار جدید جهت پیگیری. نام: {NAME} - موبایل: {MOBILE} - گرید: {GRADE}',
            /* Custom webhook */
            'signteb_liver_sms_custom_url'     => '',
            'signteb_liver_sms_custom_method'  => 'POST',
            'signteb_liver_sms_custom_body'    => '',
            'signteb_liver_eval_base'          => '1240',
        ];

        foreach ( $options as $key => $default ) {
            register_setting( 'signteb_liver_settings', $key, [
                'sanitize_callback' => $key === 'signteb_liver_appointment_url'
                    ? 'esc_url_raw' : 'sanitize_text_field',
                'default' => $default,
            ] );
        }
    }

    /* ── Settings Page ────────────────────────────────────────── */
    public function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );

        // Regular closure (not an arrow fn) so the file parses on PHP 7.2 / 7.3 too.
        $o = function ( $k, $d = '' ) {
            return esc_attr( get_option( $k, $d ) );
        };
        ?>
        <div class="wrap" style="max-width:760px">
          <h1>⚙️ SignTeb Liver Calculator — Settings</h1>

          <form method="post" action="options.php">
            <?php settings_fields( 'signteb_liver_settings' ); ?>

            <h2 class="title">📞 Contact & Notifications</h2>
            <table class="form-table">
              <tr>
                <th><label for="signteb_liver_wa_number">WhatsApp Number <small>(intl. format)</small></label></th>
                <td>
                  <input type="text" id="signteb_liver_wa_number" name="signteb_liver_wa_number"
                         value="<?php echo $o('signteb_liver_wa_number','989191182649'); ?>"
                         class="regular-text" placeholder="989191182649">
                  <p class="description">Without + sign. e.g. 989191182649</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_admin_email">Lead Notification Email</label></th>
                <td><input type="email" id="signteb_liver_admin_email" name="signteb_liver_admin_email"
                           value="<?php echo $o('signteb_liver_admin_email',get_option('admin_email')); ?>"
                           class="regular-text"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_phone">Clinic Phone Number</label></th>
                <td><input type="text" id="signteb_liver_phone" name="signteb_liver_phone"
                           value="<?php echo $o('signteb_liver_phone'); ?>"
                           class="regular-text" placeholder="021-XXXXXXXX"></td>
              </tr>
            </table>

            <h2 class="title">🔲 Dynamic CTA Block</h2>
            <p class="description" style="margin-bottom:1rem">
              These values appear in the call-to-action block displayed after the calculator results.
              Leave the title/description empty to hide the CTA block entirely.
            </p>
            <table class="form-table">
              <tr>
                <th><label for="signteb_liver_doctor_name">Doctor / Specialist Name</label></th>
                <td><input type="text" id="signteb_liver_doctor_name" name="signteb_liver_doctor_name"
                           value="<?php echo $o('signteb_liver_doctor_name'); ?>"
                           class="regular-text" placeholder="دکتر حامد زمانی"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_clinic_name">Clinic / Center Name</label></th>
                <td><input type="text" id="signteb_liver_clinic_name" name="signteb_liver_clinic_name"
                           value="<?php echo $o('signteb_liver_clinic_name'); ?>"
                           class="regular-text" placeholder="کلینیک تخصصی گوارش"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_cta_title">CTA Title <code>{{cta_title}}</code></label></th>
                <td><input type="text" id="signteb_liver_cta_title" name="signteb_liver_cta_title"
                           value="<?php echo $o('signteb_liver_cta_title'); ?>"
                           class="large-text"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_cta_desc">CTA Description <code>{{cta_description}}</code></label></th>
                <td><textarea id="signteb_liver_cta_desc" name="signteb_liver_cta_desc"
                              class="large-text" rows="2"><?php
                    echo esc_textarea( get_option('signteb_liver_cta_desc','') ); ?></textarea></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_cta_btn">Button Label <code>{{cta_button_label}}</code></label></th>
                <td><input type="text" id="signteb_liver_cta_btn" name="signteb_liver_cta_btn"
                           value="<?php echo $o('signteb_liver_cta_btn'); ?>"
                           class="regular-text" placeholder="رزرو نوبت آنلاین"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_appointment_url">Appointment URL <code>{{appointment_url}}</code></label></th>
                <td><input type="url" id="signteb_liver_appointment_url" name="signteb_liver_appointment_url"
                           value="<?php echo esc_attr(get_option('signteb_liver_appointment_url','#')); ?>"
                           class="large-text" placeholder="https://example.com/book"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_address">Address <code>{{clinic_address}}</code></label></th>
                <td><input type="text" id="signteb_liver_address" name="signteb_liver_address"
                           value="<?php echo $o('signteb_liver_address'); ?>"
                           class="large-text"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_hours">Working Hours <code>{{working_hours}}</code></label></th>
                <td><input type="text" id="signteb_liver_hours" name="signteb_liver_hours"
                           value="<?php echo $o('signteb_liver_hours'); ?>"
                           class="regular-text" placeholder="شنبه تا چهارشنبه ۹–۱۸"></td>
              </tr>
            </table>

            <h2 class="title">📲 سامانه پیامک پویا (Dynamic SMS) — تأیید و اعلان‌ها</h2>
            <p class="description" style="margin-bottom:1rem">
              ارائه‌دهنده پیامک را انتخاب کنید و ارسال را به‌صورت <strong>پترن/الگو (Body Pattern)</strong> تنظیم کنید
              تا با مقررات سامانه‌های پیامکی ایران سازگار باشد. در متن الگو از توکن‌ها استفاده کنید:
              <code>{0}</code> (موقعیتی) یا <code>{NAME}</code>، <code>{MOBILE}</code>، <code>{GRADE}</code>، <code>{TYPE}</code> (نام‌دار).
              برای OTP توکن کد با <code>{0}</code> در دسترس است. برای هر سامانه دیگر از فیلتر
              <code>signteb_liver_send_sms</code> استفاده کنید.
            </p>
            <table class="form-table">
              <tr>
                <th><label for="signteb_liver_sms_provider">ارائه‌دهنده پیامک (SMS Provider)</label></th>
                <td>
                  <select id="signteb_liver_sms_provider" name="signteb_liver_sms_provider" class="regular-text">
                    <?php
                    $cur = get_option( 'signteb_liver_sms_provider', 'kavenegar' );
                    foreach ( SignTeb_Liver_SMS::providers() as $slug => $label ) {
                        printf( '<option value="%s" %s>%s</option>',
                            esc_attr( $slug ), selected( $cur, $slug, false ), esc_html( $label ) );
                    }
                    ?>
                  </select>
                  <p class="description">سامانه را به‌سادگی از این منو تعویض کنید — کاوه‌نگار، SMS.ir، ملی‌پیامک یا وب‌هوک سفارشی.</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_enabled">فعال‌سازی تأیید پیامکی (OTP)</label></th>
                <td>
                  <input type="hidden" name="signteb_liver_sms_enabled" value="0">
                  <label>
                    <input type="checkbox" id="signteb_liver_sms_enabled" name="signteb_liver_sms_enabled"
                           value="1" <?php checked( '1', get_option('signteb_liver_sms_enabled','0') ); ?>>
                    قبل از نمایش نتایج، کد یک‌بارمصرف پیامکی الزامی شود
                  </label>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_api_key">کلید API / نام‌کاربری</label></th>
                <td><input type="text" id="signteb_liver_sms_api_key" name="signteb_liver_sms_api_key"
                           value="<?php echo $o('signteb_liver_sms_api_key'); ?>"
                           class="large-text" autocomplete="off" dir="ltr" placeholder="API Key / Username">
                  <p class="description">کاوه‌نگار/SMS.ir: کلید API — ملی‌پیامک: نام‌کاربری.</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_api_secret">رمز / API Secret <small>(ملی‌پیامک)</small></label></th>
                <td><input type="password" id="signteb_liver_sms_api_secret" name="signteb_liver_sms_api_secret"
                           value="<?php echo $o('signteb_liver_sms_api_secret'); ?>"
                           class="regular-text" autocomplete="off" dir="ltr" placeholder="Password">
                  <p class="description">فقط برای ملی‌پیامک لازم است (رمز عبور حساب).</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_sender">شماره خط فرستنده <small>(اختیاری)</small></label></th>
                <td><input type="text" id="signteb_liver_sms_sender" name="signteb_liver_sms_sender"
                           value="<?php echo $o('signteb_liver_sms_sender'); ?>"
                           class="regular-text" dir="ltr" placeholder="10004346"></td>
              </tr>

              <tr><th colspan="2"><h3 style="margin:.5rem 0 0">🔐 الگوی کد تأیید (OTP)</h3></th></tr>
              <tr>
                <th><label for="signteb_liver_sms_otp_pattern">کد/شناسه پترن OTP</label></th>
                <td><input type="text" id="signteb_liver_sms_otp_pattern" name="signteb_liver_sms_otp_pattern"
                           value="<?php echo $o('signteb_liver_sms_otp_pattern'); ?>"
                           class="regular-text" dir="ltr" placeholder="verify / 123456 / bodyId">
                  <p class="description">نام الگوی کاوه‌نگار، شناسهٔ قالب SMS.ir یا bodyId ملی‌پیامک. خالی = ارسال متن آزاد.</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_otp_text">متن پترن OTP (Body Pattern)</label></th>
                <td><input type="text" id="signteb_liver_sms_otp_text" name="signteb_liver_sms_otp_text"
                           value="<?php echo $o('signteb_liver_sms_otp_text'); ?>"
                           class="large-text" dir="rtl"
                           placeholder="کد تأیید شما {0} می‌باشد. لطفاً آن را در اختیار دیگران قرار ندهید">
                  <p class="description">توکن <code>{0}</code> = کد تأیید. برای حالت متن آزاد و وب‌هوک سفارشی استفاده می‌شود.</p>
                </td>
              </tr>

              <tr><th colspan="2"><h3 style="margin:.5rem 0 0">👤 شماره مدیر (اعلان لید)</h3></th></tr>
              <tr>
                <th><label for="signteb_liver_sms_admin_mobile">موبایل مدیر</label></th>
                <td><input type="text" id="signteb_liver_sms_admin_mobile" name="signteb_liver_sms_admin_mobile"
                           value="<?php echo $o('signteb_liver_sms_admin_mobile'); ?>"
                           class="regular-text" dir="ltr" placeholder="09XXXXXXXXX"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_admin_pattern">کد/شناسه پترن مدیر</label></th>
                <td><input type="text" id="signteb_liver_sms_admin_pattern" name="signteb_liver_sms_admin_pattern"
                           value="<?php echo $o('signteb_liver_sms_admin_pattern'); ?>"
                           class="regular-text" dir="ltr" placeholder="(اختیاری)"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_admin_text">متن پترن مدیر</label></th>
                <td><input type="text" id="signteb_liver_sms_admin_text" name="signteb_liver_sms_admin_text"
                           value="<?php echo $o('signteb_liver_sms_admin_text'); ?>"
                           class="large-text" dir="rtl"
                           placeholder="نام: {NAME} - موبایل: {MOBILE} - گرید: {GRADE}">
                  <p class="description">توکن‌ها: <code>{NAME}</code> <code>{MOBILE}</code> <code>{GRADE}</code> <code>{TYPE}</code> — یا موقعیتی <code>{0}</code>،<code>{1}</code>،<code>{2}</code>.</p>
                </td>
              </tr>

              <tr><th colspan="2"><h3 style="margin:.5rem 0 0">🗂️ شماره منشی (Secretary) — با پترن اختصاصی</h3></th></tr>
              <tr>
                <th><label for="signteb_liver_sms_secretary_mobile">موبایل منشی</label></th>
                <td><input type="text" id="signteb_liver_sms_secretary_mobile" name="signteb_liver_sms_secretary_mobile"
                           value="<?php echo $o('signteb_liver_sms_secretary_mobile'); ?>"
                           class="regular-text" dir="ltr" placeholder="09XXXXXXXXX">
                  <p class="description">هر لید جدید علاوه بر مدیر، برای منشی نیز ارسال می‌شود.</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_secretary_pattern">کد/شناسه پترن منشی</label></th>
                <td><input type="text" id="signteb_liver_sms_secretary_pattern" name="signteb_liver_sms_secretary_pattern"
                           value="<?php echo $o('signteb_liver_sms_secretary_pattern'); ?>"
                           class="regular-text" dir="ltr" placeholder="(اختیاری — پترن مستقل منشی)"></td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_secretary_text">متن پترن منشی</label></th>
                <td><input type="text" id="signteb_liver_sms_secretary_text" name="signteb_liver_sms_secretary_text"
                           value="<?php echo $o('signteb_liver_sms_secretary_text'); ?>"
                           class="large-text" dir="rtl"
                           placeholder="بیمار جدید جهت پیگیری. نام: {NAME} - موبایل: {MOBILE} - گرید: {GRADE}"></td>
              </tr>

              <tr><th colspan="2"><h3 style="margin:.5rem 0 0">🔧 وب‌هوک سفارشی (فقط برای provider = Custom)</h3></th></tr>
              <tr>
                <th><label for="signteb_liver_sms_custom_url">آدرس وب‌هوک</label></th>
                <td><input type="text" id="signteb_liver_sms_custom_url" name="signteb_liver_sms_custom_url"
                           value="<?php echo $o('signteb_liver_sms_custom_url'); ?>"
                           class="large-text" dir="ltr"
                           placeholder="https://api.example.com/send?to={to}&text={message}">
                  <p class="description">پلیس‌هولدرها: <code>{to}</code> <code>{message}</code> <code>{pattern}</code> <code>{sender}</code> <code>{api_key}</code>.</p>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_custom_method">متد</label></th>
                <td>
                  <select id="signteb_liver_sms_custom_method" name="signteb_liver_sms_custom_method">
                    <?php $cm = get_option( 'signteb_liver_sms_custom_method', 'POST' ); ?>
                    <option value="POST" <?php selected( $cm, 'POST' ); ?>>POST</option>
                    <option value="GET"  <?php selected( $cm, 'GET'  ); ?>>GET</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th><label for="signteb_liver_sms_custom_body">بدنه JSON (برای POST)</label></th>
                <td><textarea id="signteb_liver_sms_custom_body" name="signteb_liver_sms_custom_body"
                              class="large-text" rows="3" dir="ltr"
                              placeholder='{"to":"{to}","message":"{message}","apikey":"{api_key}"}'><?php echo esc_textarea( get_option('signteb_liver_sms_custom_body','') ); ?></textarea></td>
              </tr>

              <tr><th colspan="2"><h3 style="margin:.5rem 0 0">📊 شمارنده ارزیابی</h3></th></tr>
              <tr>
                <th><label for="signteb_liver_eval_base">عدد پایه ارزیابی‌ها</label></th>
                <td><input type="number" id="signteb_liver_eval_base" name="signteb_liver_eval_base"
                           value="<?php echo $o('signteb_liver_eval_base'); ?>"
                           class="small-text" min="0">
                  <p class="description">شمارنده «ارزیابی انجام‌شده» = <strong>این عدد + تعداد واقعی لیدهای ثبت‌شده</strong>؛ پس به‌صورت زنده رشد می‌کند.</p>
                </td>
              </tr>
            </table>

            <hr>
            <p style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:.75rem 1rem;font-size:.85rem">
              <strong>Shortcode:</strong> <code>[fatty_liver_calculator]</code> — paste in any page or post.
            </p>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    /* ── Leads Page ───────────────────────────────────────────── */
    public function page_leads() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );

        if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] ) {
            check_admin_referer( 'signteb_export_csv' );
            $this->export_csv(); exit;
        }

        global $wpdb;
        $table    = $wpdb->prefix . SIGNTEB_LIVER_TABLE;
        $per_page = 25;
        $cur_pg   = max( 1, absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
        $offset   = ( $cur_pg - 1 ) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $leads = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        $ttl_pgs = (int) ceil( $total / $per_page );

        $grade_map = [
            'grade_0'=>'🟢 گرید ۰ – طبیعی','grade_1'=>'🟡 گرید ۱ – خفیف',
            'grade_2'=>'🟠 گرید ۲ – متوسط','grade_3'=>'🔴 گرید ۳ – شدید',
        ];
        ?>
        <div class="wrap">
          <h1>❤️ SignTeb Liver Leads <span class="title-count theme-count"><?php echo esc_html($total); ?></span></h1>
          <p>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=signteb-liver-leads&export=csv'),'signteb_export_csv')); ?>"
               class="button button-primary">⬇ Export CSV</a>
            &nbsp;
            <a href="<?php echo esc_url(admin_url('admin.php?page=signteb-liver-settings')); ?>"
               class="button">⚙ Settings</a>
          </p>
          <table class="wp-list-table widefat fixed striped">
            <thead><tr>
              <th>ID</th><th>نام</th><th>تلفن</th><th>نوع</th>
              <th>BMI</th><th>امتیاز</th><th>گرید</th><th>تاریخ</th>
            </tr></thead>
            <tbody>
            <?php if ( empty($leads) ) : ?>
              <tr><td colspan="8" style="text-align:center;padding:2rem;color:#888">هنوز لیدی ثبت نشده.</td></tr>
            <?php else : ?>
              <?php foreach ( $leads as $l ) :
                $m   = json_decode( $l->input_metrics, true );
                $bmi = isset($m['bmi']) ? number_format((float)$m['bmi'],1) : '—';
              ?>
              <tr>
                <td><?php echo absint($l->id); ?></td>
                <td><strong><?php echo esc_html($l->full_name); ?></strong></td>
                <td><?php echo esc_html($l->phone); ?></td>
                <td><?php echo ('clinical'===$l->test_type)?'🔬 بالینی':'🏃 سبک‌زندگی'; ?></td>
                <td><?php echo esc_html($bmi); ?></td>
                <td><?php echo esc_html(number_format((float)$l->calculated_score,2)); ?></td>
                <td><?php echo esc_html($grade_map[$l->result_grade]??$l->result_grade); ?></td>
                <td><?php echo esc_html(get_date_from_gmt($l->created_at,'Y/m/d H:i')); ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
          <?php if ( $ttl_pgs > 1 ) : ?>
          <div class="tablenav bottom"><div class="tablenav-pages">
            <?php echo wp_kses_post(paginate_links(['base'=>add_query_arg('paged','%#%'),'format'=>'','total'=>$ttl_pgs,'current'=>$cur_pg])); ?>
          </div></div>
          <?php endif; ?>
        </div>
        <?php
    }

    /* ── CSV Export ───────────────────────────────────────────── */
    private function export_csv() {
        global $wpdb;
        $table = $wpdb->prefix . SIGNTEB_LIVER_TABLE;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $leads = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY created_at DESC", ARRAY_A );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="liver-leads-' . gmdate('Y-m-d') . '.csv"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF";
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, ['ID','Name','Phone','Type','BMI','Score','Grade','IP','Date','Metrics'] );
        foreach ( $leads as $row ) {
            $m = json_decode( $row['input_metrics'], true );
            fputcsv( $out, [ $row['id'],$row['full_name'],$row['phone'],$row['test_type'],
                $m['bmi']??'',$row['calculated_score'],$row['result_grade'],
                $row['ip_address'],$row['created_at'],$row['input_metrics'] ] );
        }
        fclose( $out );
    }

    public function action_links( $links ) {
        array_unshift( $links,
            '<a href="' . esc_url(admin_url('admin.php?page=signteb-liver-settings')) . '">Settings</a>'
        );
        return $links;
    }

} // end class

SignTeb_Fatty_Liver_Calculator::get_instance();

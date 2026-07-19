<?php
/**
 * SignTeb License Client
 *
 * Validates the plugin license against the SignTeb License Server.
 * Uses WordPress transients as a local 24-hour cache to avoid
 * hitting the remote API on every page load.
 *
 * Flow:
 *  1. Admin enters license key + presses "Activate"
 *  2. We call License Server: POST /api.php {action:activate, key, domain}
 *  3. Server returns {valid, plan, expires, sites_remaining}
 *  4. Result stored in wp_options + 24h transient
 *  5. Daily WP-Cron re-validates silently
 *  6. Plugin features gate on SignTeb_License::is_valid()
 *
 * @package SignTeb_Fatty_Liver_Calculator
 */
defined( 'ABSPATH' ) || exit;

class SignTeb_License {

    /* ── Constants ────────────────────────────────────────────────────────── */
    const OPTION_KEY       = 'signteb_liver_license';
    const TRANSIENT_KEY    = 'signteb_liver_lic_cache';
    const TRANSIENT_TTL    = DAY_IN_SECONDS;
    const GRACE_PERIOD     = 3 * DAY_IN_SECONDS; // offline grace window
    const CRON_HOOK        = 'signteb_liver_license_check';

    /**
     * Remote License Server URL.
     * Change this to your deployed license server domain.
     */
    const SERVER_URL       = 'https://license.signteb.com/api.php';

    /* ── Singleton ────────────────────────────────────────────────────────── */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( self::CRON_HOOK,          [ $this, 'cron_verify' ] );
        add_action( 'admin_init',             [ $this, 'handle_form' ] );
        add_action( 'admin_notices',          [ $this, 'show_notices' ] );
        add_action( 'wp',                     [ $this, 'schedule_cron' ] );

        // Add License sub-menu under Liver Leads
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
    }

    /* ══════════════════════════════════════════════════════════════════════
     * PUBLIC API — used by the rest of the plugin
     * ══════════════════════════════════════════════════════════════════════ */

    /**
     * Is the current site licensed and within expiry?
     */
    public static function is_valid() {
        $data = self::get_instance()->load_local();
        if ( empty( $data['status'] ) || 'active' !== $data['status'] ) return false;
        if ( empty( $data['expires'] ) ) return false;
        return strtotime( $data['expires'] ) > time();
    }

    /**
     * Return plan slug: 'personal'|'business'|'agency'|'whitelabel'|''
     */
    public static function get_plan() {
        $data = self::get_instance()->load_local();
        return $data['plan'] ?? '';
    }

    /**
     * Human-readable plan label.
     */
    public static function plan_label() {
        $map = [
            'personal'   => __( 'Personal (1 site)',    'signteb-liver-calc' ),
            'business'   => __( 'Business (5 sites)',   'signteb-liver-calc' ),
            'agency'     => __( 'Agency (unlimited)',   'signteb-liver-calc' ),
            'whitelabel' => __( 'White-Label',          'signteb-liver-calc' ),
        ];
        return $map[ self::get_plan() ] ?? __( 'Not licensed', 'signteb-liver-calc' );
    }

    /**
     * Expiry date string (Y-m-d) or empty.
     */
    public static function get_expires() {
        $data = self::get_instance()->load_local();
        return $data['expires'] ?? '';
    }

    /* ══════════════════════════════════════════════════════════════════════
     * ACTIVATION / DEACTIVATION
     * ══════════════════════════════════════════════════════════════════════ */

    /**
     * Activate a license key for this domain.
     *
     * @param  string $key  Raw license key entered by admin.
     * @return array        {success:bool, message:string, data:array}
     */
    public function activate( $key ) {
        $key    = strtoupper( sanitize_text_field( $key ) );
        $domain = $this->get_domain();

        $response = $this->remote_request( 'activate', [
            'license_key' => $key,
            'domain'      => $domain,
            'plugin'      => 'signteb-liver-calc',
            'version'     => SIGNTEB_LIVER_VERSION,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        if ( ! $response['valid'] ) {
            return [
                'success' => false,
                'message' => $response['message'] ?? __( 'Invalid license key.', 'signteb-liver-calc' ),
            ];
        }

        // Persist locally
        $data = [
            'key'         => $key,
            'status'      => 'active',
            'plan'        => $response['plan']          ?? 'personal',
            'expires'     => $response['expires']       ?? '',
            'max_sites'   => $response['max_sites']     ?? 1,
            'activated'   => current_time( 'mysql' ),
            'domain'      => $domain,
            'last_check'  => current_time( 'mysql' ),
        ];
        $this->save_local( $data );

        return [
            'success' => true,
            'message' => sprintf(
                __( 'License activated! Plan: %s — Expires: %s', 'signteb-liver-calc' ),
                $data['plan'],
                $data['expires']
            ),
            'data'    => $data,
        ];
    }

    /**
     * Deactivate (release) this site's license seat.
     *
     * @return array {success:bool, message:string}
     */
    public function deactivate() {
        $data = $this->load_local();
        if ( empty( $data['key'] ) ) {
            return [ 'success' => false, 'message' => __( 'No active license found.', 'signteb-liver-calc' ) ];
        }

        $this->remote_request( 'deactivate', [
            'license_key' => $data['key'],
            'domain'      => $this->get_domain(),
        ] );

        // Clear locally regardless of server response
        $this->clear_local();

        return [ 'success' => true, 'message' => __( 'License deactivated.', 'signteb-liver-calc' ) ];
    }

    /* ══════════════════════════════════════════════════════════════════════
     * PERIODIC VERIFICATION
     * ══════════════════════════════════════════════════════════════════════ */

    /**
     * Called by WP-Cron once daily.
     */
    public function cron_verify() {
        $data = $this->load_local();
        if ( empty( $data['key'] ) ) return;

        $response = $this->remote_request( 'verify', [
            'license_key' => $data['key'],
            'domain'      => $this->get_domain(),
        ] );

        if ( is_wp_error( $response ) ) {
            // Network error: apply grace period
            $last = isset( $data['last_check'] ) ? strtotime( $data['last_check'] ) : 0;
            if ( time() - $last > self::GRACE_PERIOD ) {
                $data['status'] = 'grace_expired';
                $this->save_local( $data );
            }
            return;
        }

        $data['status']     = $response['valid'] ? 'active' : 'invalid';
        $data['expires']    = $response['expires']   ?? $data['expires'];
        $data['plan']       = $response['plan']      ?? $data['plan'];
        $data['last_check'] = current_time( 'mysql' );
        $this->save_local( $data );
    }

    public function schedule_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
     * ADMIN UI
     * ══════════════════════════════════════════════════════════════════════ */

    public function add_menu() {
        add_submenu_page(
            'signteb-liver-leads',
            __( 'License', 'signteb-liver-calc' ),
            __( 'License 🔑', 'signteb-liver-calc' ),
            'manage_options',
            'signteb-liver-license',
            [ $this, 'render_page' ]
        );
    }

    public function handle_form() {
        if ( ! isset( $_POST['signteb_license_action'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'signteb_license_form' );

        $action = sanitize_key( $_POST['signteb_license_action'] );
        delete_transient( self::TRANSIENT_KEY );

        if ( 'activate' === $action ) {
            $key    = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
            $result = $this->activate( $key );
            set_transient( 'signteb_lic_notice', $result, 60 );
        }

        if ( 'deactivate' === $action ) {
            $result = $this->deactivate();
            set_transient( 'signteb_lic_notice', $result, 60 );
        }

        wp_redirect( admin_url( 'admin.php?page=signteb-liver-license' ) );
        exit;
    }

    public function show_notices() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'signteb' ) === false ) return;

        // License expiry warning (30-day)
        if ( self::is_valid() ) {
            $expires = strtotime( self::get_expires() );
            if ( $expires && ( $expires - time() ) < 30 * DAY_IN_SECONDS ) {
                printf(
                    '<div class="notice notice-warning"><p>⚠️ <strong>SignTeb Liver Calc:</strong> %s <strong>%s</strong>.</p></div>',
                    esc_html__( 'Your license expires on', 'signteb-liver-calc' ),
                    esc_html( date_i18n( get_option( 'date_format' ), $expires ) )
                );
            }
        }

        // No license notice (only on our pages)
        if ( ! self::is_valid() && strpos( $screen->id, 'signteb-liver-leads' ) !== false ) {
            printf(
                '<div class="notice notice-error"><p>🔑 <strong>SignTeb Liver Calc:</strong> %s <a href="%s">%s</a></p></div>',
                esc_html__( 'No valid license detected. The calculator shortcode is disabled.', 'signteb-liver-calc' ),
                esc_url( admin_url( 'admin.php?page=signteb-liver-license' ) ),
                esc_html__( 'Activate license →', 'signteb-liver-calc' )
            );
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        $data    = $this->load_local();
        $notice  = get_transient( 'signteb_lic_notice' );
        delete_transient( 'signteb_lic_notice' );
        $valid   = self::is_valid();
        $plan    = self::plan_label();
        $expires = self::get_expires();
        ?>
        <div class="wrap" style="max-width:680px">
          <h1 style="display:flex;align-items:center;gap:10px">🔑 <?php esc_html_e('SignTeb — License', 'signteb-liver-calc'); ?></h1>

          <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo $notice['success'] ? 'success' : 'error'; ?> is-dismissible">
              <p><?php echo esc_html( $notice['message'] ); ?></p>
            </div>
          <?php endif; ?>

          <!-- Status Card -->
          <div style="background:<?php echo $valid ? '#f0fdf4' : '#fef2f2'; ?>;border:1px solid <?php echo $valid ? '#bbf7d0' : '#fecaca'; ?>;border-radius:12px;padding:1.5rem;margin:1.5rem 0;">
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
              <div style="font-size:2.5rem"><?php echo $valid ? '✅' : '❌'; ?></div>
              <div>
                <div style="font-size:1.1rem;font-weight:700;color:<?php echo $valid ? '#166534' : '#991b1b'; ?>">
                  <?php echo $valid ? esc_html__('License Active', 'signteb-liver-calc') : esc_html__('Not Licensed', 'signteb-liver-calc'); ?>
                </div>
                <?php if ( $valid ) : ?>
                  <div style="font-size:.85rem;color:#374151;margin-top:.25rem">
                    <strong><?php esc_html_e('Plan:', 'signteb-liver-calc'); ?></strong> <?php echo esc_html($plan); ?> &nbsp;|&nbsp;
                    <strong><?php esc_html_e('Expires:', 'signteb-liver-calc'); ?></strong> <?php echo esc_html($expires); ?> &nbsp;|&nbsp;
                    <strong><?php esc_html_e('Domain:', 'signteb-liver-calc'); ?></strong> <?php echo esc_html($data['domain'] ?? $this->get_domain()); ?>
                  </div>
                  <?php if ( !empty($data['key']) ) : ?>
                    <div style="font-size:.78rem;color:#6b7280;margin-top:.2rem;font-family:monospace">
                      <?php echo esc_html( substr($data['key'],0,4) . '-****-****-****-' . substr($data['key'],-4) ); ?>
                    </div>
                  <?php endif; ?>
                <?php else : ?>
                  <div style="font-size:.85rem;color:#6b7280;margin-top:.25rem">
                    <?php esc_html_e('Enter your license key below to unlock all features.', 'signteb-liver-calc'); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if ( ! $valid ) : ?>
          <!-- Activation Form -->
          <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem">
            <h3 style="margin-top:0"><?php esc_html_e('Activate License', 'signteb-liver-calc'); ?></h3>
            <form method="post">
              <?php wp_nonce_field('signteb_license_form'); ?>
              <input type="hidden" name="signteb_license_action" value="activate">
              <table class="form-table" style="margin-top:0">
                <tr>
                  <th><label for="license_key"><?php esc_html_e('License Key', 'signteb-liver-calc'); ?></label></th>
                  <td>
                    <input type="text" id="license_key" name="license_key"
                           value="" class="regular-text"
                           placeholder="STEB-XXXXXXXX-XXXX-XXXX-XXXXXXXXXXXX"
                           style="font-family:monospace;letter-spacing:.05em"
                           required>
                    <p class="description">
                      <?php esc_html_e('Enter the key you received after purchase.', 'signteb-liver-calc'); ?>
                    </p>
                  </td>
                </tr>
              </table>
              <?php submit_button( __('Activate License', 'signteb-liver-calc'), 'primary', 'submit', true ); ?>
            </form>
          </div>
          <?php else : ?>
          <!-- Deactivation -->
          <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem">
            <h3 style="margin-top:0"><?php esc_html_e('Deactivate License', 'signteb-liver-calc'); ?></h3>
            <p style="color:#6b7280;font-size:.9rem">
              <?php esc_html_e('Deactivating releases this site\'s seat so you can use the key on another domain.', 'signteb-liver-calc'); ?>
            </p>
            <form method="post" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to deactivate?', 'signteb-liver-calc'); ?>')">
              <?php wp_nonce_field('signteb_license_form'); ?>
              <input type="hidden" name="signteb_license_action" value="deactivate">
              <?php submit_button( __('Deactivate This Site', 'signteb-liver-calc'), 'secondary', 'submit', true ); ?>
            </form>
          </div>
          <?php endif; ?>

          <!-- Plans Info -->
          <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:1.25rem">
            <h3 style="margin-top:0;font-size:.95rem"><?php esc_html_e('License Plans', 'signteb-liver-calc'); ?></h3>
            <table class="widefat" style="font-size:.82rem">
              <thead>
                <tr>
                  <th><?php esc_html_e('Plan', 'signteb-liver-calc'); ?></th>
                  <th><?php esc_html_e('Sites', 'signteb-liver-calc'); ?></th>
                  <th><?php esc_html_e('Support', 'signteb-liver-calc'); ?></th>
                  <th><?php esc_html_e('White-Label', 'signteb-liver-calc'); ?></th>
                </tr>
              </thead>
              <tbody>
                <tr><td>Personal</td><td>1</td><td>6 months</td><td>❌</td></tr>
                <tr><td>Business</td><td>5</td><td>1 year</td><td>❌</td></tr>
                <tr><td>Agency</td><td>Unlimited</td><td>1 year</td><td>❌</td></tr>
                <tr><td>White-Label</td><td>Unlimited</td><td>2 years</td><td>✅</td></tr>
              </tbody>
            </table>
            <p style="margin-bottom:0;margin-top:.75rem;font-size:.78rem;color:#6b7280">
              <?php printf(
                  esc_html__('Purchase at %s', 'signteb-liver-calc'),
                  '<a href="https://signteb.com/shop" target="_blank">signteb.com/shop</a>'
              ); ?>
            </p>
          </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════════════════
     * REMOTE API
     * ══════════════════════════════════════════════════════════════════════ */

    /**
     * Send a request to the License Server.
     *
     * @param  string $action   'activate'|'deactivate'|'verify'
     * @param  array  $payload  Additional POST fields.
     * @return array|WP_Error   Decoded JSON or WP_Error on network failure.
     */
    private function remote_request( $action, $payload = [] ) {
        $payload['action'] = $action;

        $response = wp_remote_post( self::SERVER_URL, [
            'timeout'   => 15,
            'sslverify' => true,
            'body'      => $payload,
            'headers'   => [
                'User-Agent' => 'SignTeb-License-Client/' . SIGNTEB_LIVER_VERSION,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );

        if ( $code !== 200 || ! is_array( $json ) ) {
            return new WP_Error(
                'server_error',
                sprintf( __( 'License server error (HTTP %d).', 'signteb-liver-calc' ), $code )
            );
        }

        return $json;
    }

    /* ══════════════════════════════════════════════════════════════════════
     * LOCAL STORAGE HELPERS
     * ══════════════════════════════════════════════════════════════════════ */

    /** Load license data from option (cached in transient). */
    private function load_local() {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( false !== $cached ) return $cached;

        $data = get_option( self::OPTION_KEY, [] );
        if ( ! empty( $data ) ) {
            set_transient( self::TRANSIENT_KEY, $data, self::TRANSIENT_TTL );
        }
        return $data;
    }

    /** Persist license data. */
    private function save_local( $data ) {
        update_option( self::OPTION_KEY, $data, false );
        set_transient( self::TRANSIENT_KEY, $data, self::TRANSIENT_TTL );
    }

    /** Remove all local license data. */
    private function clear_local() {
        delete_option( self::OPTION_KEY );
        delete_transient( self::TRANSIENT_KEY );
    }

    /** Clean domain string (scheme-stripped, lowercase). */
    private function get_domain() {
        $home = strtolower( home_url() );
        $home = preg_replace( '#^https?://#', '', $home );
        $home = rtrim( $home, '/' );
        return $home;
    }
}

/* ── Boot ──────────────────────────────────────────────────────────────────── */
SignTeb_License::get_instance();

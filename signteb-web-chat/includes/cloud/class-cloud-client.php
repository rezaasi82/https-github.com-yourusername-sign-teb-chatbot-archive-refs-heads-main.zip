<?php
/**
 * \Medora\Cloud\CloudClient — the plugin side of the Medora Cloud platform (Level 2).
 *
 * Opt-in telemetry only. When enabled with an endpoint it registers the
 * installation once and sends a signed daily heartbeat with technical counters
 * (no patient data ever). When disabled it is a complete no-op and clears its
 * own cron. The transmitted payload is signed so the cloud can verify origin.
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Cloud;

if (! defined('ABSPATH')) {
    exit;
}

class CloudClient
{
    public const OPTION_SECRET   = 'swc_cloud_secret_enc';
    public const OPTION_REGISTERED = 'swc_cloud_registered';
    private const CRON_HEARTBEAT = 'swc_cloud_heartbeat';
    private const CRON_INSTALL   = 'swc_cloud_install';

    private \Medora\Core\Settings $settings;

    public function __construct(?\Medora\Core\Settings $settings = null)
    {
        $this->settings = $settings ?? new \Medora\Core\Settings();
    }

    public function register(): void
    {
        add_action(self::CRON_HEARTBEAT, [$this, 'heartbeat']);
        add_action(self::CRON_INSTALL, [$this, 'register_install']);

        if (! $this->is_enabled()) {
            $this->unschedule();
            return;
        }

        if (! wp_next_scheduled(self::CRON_HEARTBEAT)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', self::CRON_HEARTBEAT);
        }
        if (get_option(self::OPTION_REGISTERED) !== '1' && ! wp_next_scheduled(self::CRON_INSTALL)) {
            wp_schedule_single_event(time() + 30, self::CRON_INSTALL);
        }
    }

    public function is_enabled(): bool
    {
        return (int) $this->settings->get('cloud_enabled', 0) === 1 && $this->endpoint() !== '';
    }

    public function endpoint(): string
    {
        return esc_url_raw((string) $this->settings->get('cloud_endpoint', ''));
    }

    public function secret(): string
    {
        $enc = get_option(self::OPTION_SECRET, '');
        return is_string($enc) && $enc !== '' ? \Medora\Core\Encryption::decrypt($enc) : '';
    }

    public static function save_secret(string $plain): void
    {
        $plain = trim($plain);
        if ($plain === '') {
            delete_option(self::OPTION_SECRET);
            return;
        }
        update_option(self::OPTION_SECRET, \Medora\Core\Encryption::encrypt($plain), false);
    }

    public function register_install(): void
    {
        try {
            $ok = $this->send('install', $this->telemetry());
            if ($ok) {
                update_option(self::OPTION_REGISTERED, '1', false);
            }
        } catch (\Throwable $e) {
            $this->log($e);
        }
    }

    public function heartbeat(): void
    {
        try {
            $this->send('heartbeat', $this->telemetry());
        } catch (\Throwable $e) {
            $this->log($e);
        }
    }

    /**
     * Technical telemetry only — never any patient/lead content.
     *
     * @return array<string,mixed>
     */
    private function telemetry(): array
    {
        return [
            'domain'         => hash('sha256', strtolower((string) (wp_parse_url(home_url(), PHP_URL_HOST) ?: ''))),
            'plugin_version' => defined('SWC_VERSION') ? SWC_VERSION : '',
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'locale'         => get_locale(),
            'timezone'       => wp_timezone_string(),
            'counts'         => $this->counts(),
            'time'           => current_time('mysql'),
        ];
    }

    /**
     * @return array{messages:int,leads:int,bookings:int,active_24h:int}
     */
    private function counts(): array
    {
        global $wpdb;
        $conv = \Medora\Database\Schema::conversations_table();
        $msg  = \Medora\Database\Schema::messages_table();
        return [
            'messages'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$msg}"),           // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'leads'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$conv} WHERE is_lead = 1"), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'bookings'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$conv} WHERE booking_status <> 'none'"), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'active_24h' => (new \Medora\Database\ConversationRepository())->active_count(24),
        ];
    }

    /**
     * Signed POST to the cloud. Returns true on a 2xx response.
     */
    private function send(string $type, array $payload): bool
    {
        if (! $this->is_enabled()) {
            return false;
        }
        $payload['type'] = $type;
        $body            = (string) wp_json_encode($payload);
        $secret          = $this->secret();

        $headers = [
            'Content-Type'   => 'application/json',
            'X-Medora-Type'  => $type,
            'X-Medora-Time'  => (string) time(),
        ];
        if ($secret !== '') {
            $headers['X-Medora-Sign'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        $response = wp_remote_post($this->endpoint(), [
            'timeout'  => 15,
            'blocking' => true,
            'headers'  => $headers,
            'body'     => $body,
        ]);

        if (is_wp_error($response)) {
            return false;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    private function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HEARTBEAT);
        wp_clear_scheduled_hook(self::CRON_INSTALL);
    }

    private function log(\Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Medora AI] cloud client: ' . $e->getMessage());
        }
    }
}

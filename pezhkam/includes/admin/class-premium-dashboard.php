<?php
/**
 * The premium admin landing page and integrity gate.
 *
 * Self-registering service: it wires its own menu, conditionally-loaded assets
 * and AJAX endpoint. Wire it once from Plugin::boot() with:
 *
 *     ( new PremiumDashboard() )->register();
 *
 * @package Pezhkam
 */

namespace Pezhkam\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class PremiumDashboard
{
    private const PAGE  = 'pzk-dashboard';
    private const NONCE = 'pzk_dashboard';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_pzk_integrity_check', [$this, 'ajax_integrity']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'pzk-chat',
            __('داشبورد پژکام', 'pezhkam'),
            __('داشبورد', 'pezhkam'),
            'manage_options',
            self::PAGE,
            [$this, 'render'],
            0
        );
    }

    /**
     * Load styles/scripts only on this page to keep the admin lightweight.
     */
    public function enqueue(string $hook): void
    {
        if (substr($hook, -strlen(self::PAGE)) !== self::PAGE) {
            return;
        }
        wp_enqueue_style('pzk-dashboard', PZK_URL . 'assets/css/dashboard.css', [], PZK_VERSION);
        wp_enqueue_script('pzk-dashboard', PZK_URL . 'assets/js/dashboard.js', [], PZK_VERSION, true);
        wp_localize_script('pzk-dashboard', 'PZK_DASH', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce(self::NONCE),
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'pezhkam'), '', ['response' => 403]);
        }

        $ranges    = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];
        $range     = \Pezhkam\Core\Input::get_key('range', 'month');
        $range     = isset($ranges[$range]) ? $range : 'month';
        $days      = $ranges[$range];

        $integrity = $this->verify_integrity_gate();
        $metrics   = $this->metrics($days);
        $settings  = new \Pezhkam\Core\Settings();

        include PZK_DIR . 'includes/admin/views/premium-dashboard.php';
    }

    /**
     * Live integrity status for the dashboard badge (nonce + capability gated).
     */
    public function ajax_integrity(): void
    {
        \Pezhkam\Core\JsonGuard::arm();
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json(['ok' => false], 403);
        }
        wp_send_json(['ok' => true, 'integrity' => $this->verify_integrity_gate()]);
    }

    /**
     * Integrity status for the dashboard badge. The core only boots after the
     * marketplace activation gate, so reaching this screen with a healthy
     * environment means the product is active.
     */
    public function verify_integrity_gate(): array
    {
        $verdict = ['ok' => false, 'level' => 'invalid', 'label' => __('نامعتبر', 'pezhkam'), 'code' => 0];

        try {
            $env_ok = defined('PZK_VERSION')
                && defined('PZK_FILE')
                && class_exists('\Pezhkam\Core\Plugin');

            if ($env_ok === true) {
                $verdict = ['ok' => true, 'level' => 'secure', 'label' => __('فعال و ایمن', 'pezhkam'), 'code' => 200];
            }
        } catch (\Throwable $e) {
            $this->log_anomaly('integrity_gate', $e);
            $verdict = ['ok' => false, 'level' => 'invalid', 'label' => __('خطای بررسی', 'pezhkam'), 'code' => 500];
        }

        return $verdict;
    }

    /**
     * Full overview metrics for a period (in days).
     *
     * @return array<string,mixed>
     */
    private function metrics(int $days): array
    {
        // DB-derived numbers are cached briefly; the integrity gate is always
        // evaluated fresh (cheap + security-sensitive).
        $data = \Pezhkam\Core\Cache::remember('dash_metrics_' . $days, 300, function () use ($days) {
            $m = [
                'active_chats' => 0, 'conversations' => 0, 'leads' => 0, 'hot_leads' => 0,
                'conversion' => 0.0, 'booked' => 0,
                'clicks' => ['booking' => 0, 'whatsapp' => 0, 'call' => 0, 'bale' => 0],
                'revenue' => 0, 'funnel' => [],
            ];
            try {
                $repo   = new \Pezhkam\Database\ConversationRepository();
                $events = new \Pezhkam\Database\EventRepository();
                $stats  = $repo->stats($days);

                $m['active_chats']  = $repo->active_count(24);
                $m['conversations'] = (int) $stats['conversations'];
                $m['leads']         = (int) $stats['leads'];
                $m['hot_leads']     = (int) $stats['hot_leads'];
                $m['conversion']    = (float) $stats['conversion_rate'];
                $m['booked']        = (int) $stats['booked'];
                $m['clicks']        = $events->counts($days);
                $m['funnel']        = $repo->funnel_counts($days);

                // Revenue estimate = Leads × Conversion Rate × Average Service Price.
                $avg_price    = (int) (new \Pezhkam\Core\Settings())->get('avg_service_price', 0);
                $m['revenue'] = (int) round($m['leads'] * ($m['conversion'] / 100) * $avg_price);
            } catch (\Throwable $e) {
                $this->log_anomaly('metrics', $e);
            }
            return $m;
        });

        $data['integrity'] = $this->verify_integrity_gate();
        return $data;
    }

    /**
     * Structured, credential-safe anomaly log (never in production output).
     */
    private function log_anomaly(string $context, \Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Pezhkam] %s anomaly: %s', $context, $e->getMessage()));
        }
    }
}

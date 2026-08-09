<?php
/**
 * Admin-ajax endpoints for the export module.
 *
 * Every handler is capability-checked and nonce-verified. The PDF download
 * streams the stored file after validating it lives inside the plugin's upload
 * folder (no arbitrary file access).
 *
 * @package Medora
 */

namespace Medora\Ajax;

if (! defined('ABSPATH')) {
    exit;
}

class ExportAjaxHandler
{
    private const NONCE = 'mdr_export';

    public function register(): void
    {
        add_action('wp_ajax_mdr_export_pdf', [$this, 'export_pdf']);
        add_action('wp_ajax_mdr_download_pdf', [$this, 'download_pdf']);
        add_action('wp_ajax_mdr_export_webhook', [$this, 'export_webhook']);
        add_action('wp_ajax_mdr_export_gsheet', [$this, 'export_gsheet']);
        add_action('wp_ajax_mdr_test_webhook', [$this, 'test_webhook']);
        add_action('wp_ajax_mdr_test_gsheet', [$this, 'test_gsheet']);
        add_action('wp_ajax_mdr_test_sms', [$this, 'test_sms']);
        add_action('wp_ajax_mdr_sms_diag', [$this, 'sms_diag']);
        add_action('wp_ajax_mdr_ai_diag', [$this, 'ai_diag']);
        add_action('wp_ajax_mdr_send_sms', [$this, 'send_sms']);
        add_action('wp_ajax_mdr_export_bulk', [$this, 'bulk']);
    }

    /**
     * Connection diagnosis for the SMS panel (credential check, no SMS sent).
     */
    public function sms_diag(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        $diag = (new \Medora\Notifications\SmsManager())->diagnose();
        wp_send_json(['ok' => $diag['ok'], 'report' => implode("\n", $diag['lines'])]);
    }

    /**
     * Ask the configured AI provider one throwaway question and report exactly
     * what came back. This is the only way an admin can tell an unset key from
     * a blocked host, a wrong model id, or an exhausted quota — all of which
     * look identical from the widget, which just says it cannot answer.
     */
    public function ai_diag(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();

        $settings = new \Medora\Core\Settings();
        $factory  = new \Medora\Ai\ProviderFactory($settings);
        $id       = $settings->active_provider();
        $model    = $settings->active_model();

        $lines = [
            sprintf(__('سرویس‌دهنده: %s', 'medora'), $id),
            sprintf(__('مدل: %s', 'medora'), $model),
            sprintf(
                __('کلید API: %s', 'medora'),
                $settings->has_api_key($id) ? __('ذخیره شده', 'medora') : __('ذخیره نشده', 'medora')
            ),
        ];

        $provider = $factory->create($id);
        if ($provider === null) {
            $lines[] = __('نتیجه: کلید API این سرویس ذخیره نشده، پس هیچ درخواستی ارسال نمی‌شود.', 'medora');
            wp_send_json(['ok' => false, 'report' => implode("\n", $lines)]);
        }

        $started = microtime(true);
        $result  = $provider->generate_reply('ping', [
            'system'     => 'Reply with the single word: ok',
            'history'    => [],
            'model'      => $model,
            'max_tokens' => 16,
        ]);
        $ms = (int) round((microtime(true) - $started) * 1000);

        $lines[] = sprintf(__('زمان پاسخ: %d میلی‌ثانیه', 'medora'), $ms);

        if (empty($result['ok'])) {
            $lines[] = sprintf(__('نتیجه: ناموفق — %s', 'medora'), (string) ($result['error'] ?? 'unknown'));
            wp_send_json(['ok' => false, 'report' => implode("\n", $lines)]);
        }

        delete_option(\Medora\Ai\AiManager::LAST_ERROR_OPTION);
        $lines[] = __('نتیجه: موفق — سرویس پاسخ داد.', 'medora');
        wp_send_json(['ok' => true, 'report' => implode("\n", $lines)]);
    }

    /**
     * Send a one-off test SMS through the configured panel. When the welcome
     * template carries a pattern code, the test uses the pattern path too, so
     * shared-service-line setups (no dedicated sender) can be tested for real.
     */
    public function test_sms(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        $to = \Medora\Core\Input::post_text('to');
        if ($to === '') {
            wp_send_json(['ok' => false, 'error' => __('شماره مقصد را وارد کنید.', 'medora')], 400);
        }

        $sms = new \Medora\Notifications\SmsManager();
        if ($sms->template_code('welcome') !== '') {
            $sample = (object) [
                'patient_name'  => __('کاربر آزمایشی', 'medora'),
                'patient_phone' => $to,
                'lead_score'    => 'warm',
                'lead_status'   => 'new',
                'summary'       => '',
            ];
            wp_send_json($sms->send_lead($to, 'welcome', $sms->vars_for_lead($sample)));
        }

        $clinic = (string) (new \Medora\Core\Settings())->get('clinic_name', get_bloginfo('name'));
        $text   = sprintf(__('پیام آزمایشی از %s (Medora).', 'medora'), $clinic);
        wp_send_json($sms->send($to, $text));
    }

    /**
     * Send a lead-related SMS from a chosen template (used by CRM referral).
     */
    public function send_sms(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();

        $lead_id  = \Medora\Core\Input::post_int('lead_id');
        $to       = \Medora\Core\Input::post_text('to');
        $tpl_key  = \Medora\Core\Input::post_key('template', 'referral');
        if ($lead_id <= 0 || $to === '') {
            wp_send_json(['ok' => false, 'error' => __('لید یا شماره مقصد نامعتبر است.', 'medora')], 400);
        }

        $c = (new \Medora\Database\ConversationRepository())->get($lead_id);
        if (! $c) {
            wp_send_json(['ok' => false, 'error' => 'not_found'], 404);
        }

        $sms = new \Medora\Notifications\SmsManager();
        wp_send_json($sms->send_lead($to, $tpl_key, $sms->vars_for_lead($c)));
    }

    private function guard(): void
    {
        if (\Medora\Security\Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            \Medora\Security\Security::note_failure('export_ajax');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }
    }

    public function export_pdf(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        $lead_id = \Medora\Core\Input::post_int('lead_id');
        wp_send_json((new \Medora\Export\ExportManager())->export_pdf($lead_id));
    }

    public function export_webhook(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        $lead_id = \Medora\Core\Input::post_int('lead_id');
        wp_send_json((new \Medora\Export\ExportManager())->export_webhook($lead_id, 'manual'));
    }

    public function export_gsheet(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        $lead_id = \Medora\Core\Input::post_int('lead_id');
        wp_send_json((new \Medora\Export\ExportManager())->export_google_sheet($lead_id));
    }

    public function test_webhook(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        wp_send_json((new \Medora\Export\WebhookManager())->test());
    }

    public function test_gsheet(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();
        wp_send_json((new \Medora\Export\GoogleSheets())->test());
    }

    public function bulk(): void
    {
        \Medora\Core\JsonGuard::arm();
        $this->guard();

        $op  = \Medora\Core\Input::post_key('op');
        $ids = array_map('absint', (array) (\Medora\Core\Input::post_fields()['ids'] ?? []));
        $ids = array_filter($ids);
        if ($ids === []) {
            wp_send_json(['ok' => false, 'error' => 'no_ids'], 400);
        }

        // Deleting files is fast and stays synchronous; heavy export/sync jobs
        // are queued so the request returns immediately and cron drains them.
        if ($op === 'delete_files') {
            $done = 0;
            foreach ($ids as $id) {
                if ($this->delete_files($id)) {
                    $done++;
                }
            }
            wp_send_json(['ok' => true, 'processed' => $done, 'total' => count($ids)]);
        }

        if (! in_array($op, ['pdf', 'webhook', 'resend', 'gsheet'], true)) {
            wp_send_json(['ok' => false, 'error' => 'bad_op'], 400);
        }

        $queue = new \Medora\Jobs\JobQueue();
        foreach ($ids as $id) {
            $queue->enqueue('export', ['op' => $op, 'lead_id' => $id]);
        }
        $queue->schedule_soon();

        wp_send_json(['ok' => true, 'queued' => count($ids), 'total' => count($ids)]);
    }

    /**
     * Stream the stored PDF/HTML for a lead after validating the path.
     */
    public function download_pdf(): void
    {
        if (\Medora\Security\Security::is_locked()) {
            wp_die(esc_html__('دسترسی موقتاً مسدود شده است.', 'medora'), '', ['response' => 429]);
        }
        if (! current_user_can('manage_options') || ! check_admin_referer(self::NONCE, 'nonce')) {
            \Medora\Security\Security::note_failure('pdf_download');
            wp_die(esc_html__('دسترسی غیرمجاز.', 'medora'), '', ['response' => 403]);
        }
        $lead_id = \Medora\Core\Input::get_int('lead_id');

        $manager = new \Medora\Export\ExportManager();
        $conv    = (new \Medora\Database\ConversationRepository())->get($lead_id);
        $url     = $conv ? (string) ($conv->pdf_url ?? '') : '';
        if ($url === '') {
            $gen = $manager->export_pdf($lead_id);
            $url = $gen['ok'] ? (string) $gen['url'] : '';
        }

        $path = $this->url_to_path($url);
        if ($path === '' || ! is_readable($path)) {
            wp_die(esc_html__('فایل یافت نشد.', 'medora'), '', ['response' => 404]);
        }

        $is_pdf   = str_ends_with($path, '.pdf');
        $filename = 'medora-lead-' . $lead_id . ($is_pdf ? '.pdf' : '.html');
        nocache_headers();
        header('Content-Type: ' . ($is_pdf ? 'application/pdf' : 'text/html; charset=utf-8'));
        header('Content-Disposition: ' . ($is_pdf ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * Map a stored upload URL back to its absolute path, and confirm it is
     * inside the plugin's medora-pdf folder.
     */
    private function url_to_path(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $uploads = wp_upload_dir();
        if (strpos($url, $uploads['baseurl'] . '/medora-pdf/') !== 0) {
            return '';
        }
        $path = $uploads['basedir'] . substr($url, strlen($uploads['baseurl']));
        $real = realpath($path);
        $base = realpath($uploads['basedir'] . '/medora-pdf');
        if ($real === false || $base === false || strpos($real, $base) !== 0) {
            return '';
        }
        return $real;
    }

    private function delete_files(int $lead_id): bool
    {
        $conv = (new \Medora\Database\ConversationRepository())->get($lead_id);
        $url  = $conv ? (string) ($conv->pdf_url ?? '') : '';
        $path = $this->url_to_path($url);
        if ($path !== '' && is_writable($path)) {
            @unlink($path);
        }
        (new \Medora\Database\ConversationRepository())->set_pdf_url($lead_id, '');
        return true;
    }
}

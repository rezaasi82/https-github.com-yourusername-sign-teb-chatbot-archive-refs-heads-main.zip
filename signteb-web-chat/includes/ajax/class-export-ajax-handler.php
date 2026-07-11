<?php
/**
 * SWC_Export_Ajax_Handler — admin-ajax endpoints for the export module.
 *
 * Every handler is capability-checked and nonce-verified. The PDF download
 * streams the stored file after validating it lives inside the plugin's upload
 * folder (no arbitrary file access).
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Export_Ajax_Handler
{
    private const NONCE = 'swc_export';

    public function register(): void
    {
        add_action('wp_ajax_swc_export_pdf', [$this, 'export_pdf']);
        add_action('wp_ajax_swc_download_pdf', [$this, 'download_pdf']);
        add_action('wp_ajax_swc_export_webhook', [$this, 'export_webhook']);
        add_action('wp_ajax_swc_export_gsheet', [$this, 'export_gsheet']);
        add_action('wp_ajax_swc_test_webhook', [$this, 'test_webhook']);
        add_action('wp_ajax_swc_test_gsheet', [$this, 'test_gsheet']);
        add_action('wp_ajax_swc_export_bulk', [$this, 'bulk']);
    }

    private function guard(): void
    {
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }
    }

    public function export_pdf(): void
    {
        SWC_Json_Guard::arm();
        $this->guard();
        $lead_id = absint($_POST['lead_id'] ?? 0);
        wp_send_json((new SWC_Export_Manager())->export_pdf($lead_id));
    }

    public function export_webhook(): void
    {
        SWC_Json_Guard::arm();
        $this->guard();
        $lead_id = absint($_POST['lead_id'] ?? 0);
        wp_send_json((new SWC_Export_Manager())->export_webhook($lead_id, 'manual'));
    }

    public function export_gsheet(): void
    {
        SWC_Json_Guard::arm();
        $this->guard();
        $lead_id = absint($_POST['lead_id'] ?? 0);
        wp_send_json((new SWC_Export_Manager())->export_google_sheet($lead_id));
    }

    public function test_webhook(): void
    {
        SWC_Json_Guard::arm();
        $this->guard();
        wp_send_json((new SWC_Webhook_Manager())->test());
    }

    public function test_gsheet(): void
    {
        SWC_Json_Guard::arm();
        $this->guard();
        wp_send_json((new SWC_Google_Sheets())->test());
    }

    public function bulk(): void
    {
        SWC_Json_Guard::arm();
        $this->guard();

        $op  = sanitize_key($_POST['op'] ?? '');
        $ids = array_map('absint', (array) ($_POST['ids'] ?? []));
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

        $queue = new SWC_Job_Queue();
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
        if (! current_user_can('manage_options') || ! check_admin_referer(self::NONCE, 'nonce')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'signteb-web-chat'), '', ['response' => 403]);
        }
        $lead_id = absint($_GET['lead_id'] ?? 0);

        $manager = new SWC_Export_Manager();
        $conv    = (new SWC_Conversation_Repository())->get($lead_id);
        $url     = $conv ? (string) ($conv->pdf_url ?? '') : '';
        if ($url === '') {
            $gen = $manager->export_pdf($lead_id);
            $url = $gen['ok'] ? (string) $gen['url'] : '';
        }

        $path = $this->url_to_path($url);
        if ($path === '' || ! is_readable($path)) {
            wp_die(esc_html__('فایل یافت نشد.', 'signteb-web-chat'), '', ['response' => 404]);
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
        $conv = (new SWC_Conversation_Repository())->get($lead_id);
        $url  = $conv ? (string) ($conv->pdf_url ?? '') : '';
        $path = $this->url_to_path($url);
        if ($path !== '' && is_writable($path)) {
            @unlink($path);
        }
        (new SWC_Conversation_Repository())->set_pdf_url($lead_id, '');
        return true;
    }
}

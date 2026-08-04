<?php
/**
 * Orchestrates every export target.
 *
 * Single entry point used by the REST controller, the admin-ajax handler and
 * the automatic event triggers. Owns the "generate PDF then sync" flow and the
 * guard that stops a lead being sent to the same target twice automatically.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Export;

if (! defined('ABSPATH')) {
    exit;
}

class ExportManager
{
    private \SignTeb\WebChat\Core\Settings $settings;
    private \SignTeb\WebChat\Database\ConversationRepository $conversations;
    private \SignTeb\WebChat\Database\SyncLogRepository $logs;

    public function __construct()
    {
        $this->settings      = new \SignTeb\WebChat\Core\Settings();
        $this->conversations = new \SignTeb\WebChat\Database\ConversationRepository();
        $this->logs          = new \SignTeb\WebChat\Database\SyncLogRepository();
    }

    /**
     * Register cron + automatic triggers.
     */
    public function register(): void
    {
        add_action('swc_webhook_retry', [$this, 'run_webhook_retry'], 10, 4);

        // Fire when a conversation is first detected as a lead.
        add_action('swc_lead_detected', [$this, 'on_lead_detected'], 20, 2);
    }

    public function run_webhook_retry(int $lead_id, string $event, string $pdf_url = '', int $attempt = 2): void
    {
        (new \SignTeb\WebChat\Export\WebhookManager($this->settings))->handle_retry($lead_id, $event, $pdf_url, $attempt);
    }

    /**
     * Automatic export on the "lead created" event, guarded against duplicates.
     */
    public function on_lead_detected(int $lead_id, string $cta): void
    {
        $webhook = new \SignTeb\WebChat\Export\WebhookManager($this->settings);
        if ($webhook->is_enabled() && $webhook->event_enabled('lead_created') && ! $this->logs->has_success($lead_id, 'webhook')) {
            $webhook->dispatch($lead_id, 'lead_created', (string) $this->pdf_url($lead_id));
        }

        $sheets = new \SignTeb\WebChat\Export\GoogleSheets($this->settings);
        if ($sheets->is_enabled() && (int) $this->settings->get('gsheet_auto', 0) === 1 && ! $this->logs->has_success($lead_id, 'google_sheets')) {
            $sheets->dispatch($lead_id, (string) $this->pdf_url($lead_id));
        }
    }

    /**
     * Generate the PDF, store its URL on the lead and log the result.
     *
     * @return array{ok:bool,url?:string,type?:string,error?:string}
     */
    public function export_pdf(int $lead_id): array
    {
        $logger  = new \SignTeb\WebChat\Export\ExportLogger();
        $log_id  = $logger->begin($lead_id, 'pdf', 'manual');
        $started = microtime(true);

        $result = (new \SignTeb\WebChat\Export\PdfGenerator($this->settings))->generate($lead_id);

        if (empty($result['ok'])) {
            $logger->finish($log_id, 'failed', ['response' => $result['error'] ?? 'error', 'attempts' => 1, 'started' => $started]);
            return ['ok' => false, 'error' => $result['error'] ?? 'error'];
        }

        $this->conversations->set_pdf_url($lead_id, (string) $result['url']);
        $logger->finish($log_id, 'success', ['response' => $result['url'], 'attempts' => 1, 'started' => $started]);
        do_action('swc_pdf_generated', $lead_id, $result['url']);

        return ['ok' => true, 'url' => $result['url'], 'type' => $result['type']];
    }

    /**
     * @return array{ok:bool,status:string,response:string}
     */
    public function export_webhook(int $lead_id, string $event = 'manual'): array
    {
        return (new \SignTeb\WebChat\Export\WebhookManager($this->settings))->dispatch($lead_id, $event, (string) $this->pdf_url($lead_id));
    }

    /**
     * @return array{ok:bool,status:string,response:string}
     */
    public function export_google_sheet(int $lead_id): array
    {
        return (new \SignTeb\WebChat\Export\GoogleSheets($this->settings))->dispatch($lead_id, (string) $this->pdf_url($lead_id));
    }

    private function pdf_url(int $lead_id): string
    {
        $lead = $this->conversations->get($lead_id);
        return $lead ? (string) ($lead->pdf_url ?? '') : '';
    }
}

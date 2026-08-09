<?php
/**
 * Appends leads to a Google Sheet.
 *
 * Writes go through a Google Apps Script Web App URL (deployed by the clinic),
 * which is the supported keyless way to append rows — the Google Sheets REST
 * API requires OAuth for writes, so a bare API key cannot append. The tiny
 * Apps Script to deploy is documented in the integration guide.
 *
 * @package Medora
 */

namespace Medora\Export;

if (! defined('ABSPATH')) {
    exit;
}

class GoogleSheets
{
    public const OPTION_SECRET = 'mdr_gsheet_secret_enc';

    private const COLUMNS = [
        'Lead ID', 'Patient Name', 'Phone', 'Request Type',
        'Lead Status', 'Created Date', 'Summary', 'PDF URL', 'Sync Status',
    ];

    private \Medora\Core\Settings $settings;
    private \Medora\Export\ExportLogger $logger;
    private \Medora\Export\LeadPayload $payloads;

    public function __construct(?\Medora\Core\Settings $settings = null, ?\Medora\Export\ExportLogger $logger = null, ?\Medora\Export\LeadPayload $payloads = null)
    {
        $this->settings = $settings ?? new \Medora\Core\Settings();
        $this->logger   = $logger ?? new \Medora\Export\ExportLogger();
        $this->payloads = $payloads ?? new \Medora\Export\LeadPayload();
    }

    public function is_enabled(): bool
    {
        return (int) $this->settings->get('gsheet_enabled', 0) === 1 && $this->url() !== '';
    }

    public function url(): string
    {
        return esc_url_raw((string) $this->settings->get('gsheet_webapp_url', ''));
    }

    public function sheet_name(): string
    {
        return (string) $this->settings->get('gsheet_name', 'Leads');
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

    /**
     * Append one lead as a row.
     *
     * @return array{ok:bool,status:string,response:string}
     */
    public function dispatch(int $lead_id, string $pdf_url = ''): array
    {
        if (! $this->is_enabled()) {
            return ['ok' => false, 'status' => 'disabled', 'response' => 'gsheet_disabled'];
        }

        $payload = $this->payloads->build($lead_id, $pdf_url);
        if ($payload === null) {
            return ['ok' => false, 'status' => 'failed', 'response' => 'lead_not_found'];
        }

        $row = [
            $payload['lead_id'],
            $payload['patient_name'],
            $payload['phone'],
            $payload['request_type'],
            $payload['lead_score'],
            $payload['created_at'],
            $this->one_line($payload['summary']),
            $payload['pdf_url'],
            'success',
        ];

        $log_id  = $this->logger->begin($lead_id, 'google_sheets', 'sync');
        $started = microtime(true);
        $result  = $this->send($row);

        $status = $result['ok'] ? 'success' : 'failed';
        $this->logger->finish($log_id, $status, [
            'response' => $result['ok'] ? 'HTTP ' . $result['code'] : $result['error'],
            'attempts' => 1,
            'started'  => $started,
        ]);

        return ['ok' => $result['ok'], 'status' => $status, 'response' => $result['ok'] ? 'HTTP ' . $result['code'] : $result['error']];
    }

    /**
     * @param array<int,mixed> $row
     * @return array{ok:bool,code:int,error:string}
     */
    public function send(array $row): array
    {
        $headers = ['Content-Type' => 'application/json'];
        $secret  = $this->secret();
        if ($secret !== '') {
            $headers['X-Medora-Secret'] = $secret;
        }

        $response = wp_remote_post($this->url(), [
            'timeout' => 20,
            'headers' => $headers,
            'body'    => wp_json_encode([
                'sheet'   => $this->sheet_name(),
                'columns' => self::COLUMNS,
                'row'     => array_values($row),
                'secret'  => $secret,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'code' => 0, 'error' => $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $ok   = $code >= 200 && $code < 300;
        return ['ok' => $ok, 'code' => $code, 'error' => $ok ? '' : ('HTTP ' . $code . ' ' . mb_substr((string) wp_remote_retrieve_body($response), 0, 300))];
    }

    /**
     * @return array{ok:bool,code:int,error:string}
     */
    public function test(): array
    {
        return $this->send(['TEST', 'Medora', '-', 'test', '-', current_time('mysql'), 'connection test', '-', 'test']);
    }

    private function one_line(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}

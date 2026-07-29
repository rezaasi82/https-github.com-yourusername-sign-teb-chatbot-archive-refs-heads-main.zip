<?php
/**
 * Generic outbound webhook engine.
 *
 * Sends a signed JSON payload to a configured endpoint (n8n, Make.com, Zapier,
 * a custom CRM, …) on lead events. Failures are logged and optionally retried
 * on a scheduled single event.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Export;

if (! defined('ABSPATH')) {
    exit;
}

class WebhookManager
{
    public const OPTION_SECRET = 'swc_webhook_secret_enc';
    public const MAX_ATTEMPTS  = 3;

    private \SignTeb\WebChat\Core\Settings $settings;
    private \SignTeb\WebChat\Export\ExportLogger $logger;
    private \SignTeb\WebChat\Export\LeadPayload $payloads;

    public function __construct(?\SignTeb\WebChat\Core\Settings $settings = null, ?\SignTeb\WebChat\Export\ExportLogger $logger = null, ?\SignTeb\WebChat\Export\LeadPayload $payloads = null)
    {
        $this->settings = $settings ?? new \SignTeb\WebChat\Core\Settings();
        $this->logger   = $logger ?? new \SignTeb\WebChat\Export\ExportLogger();
        $this->payloads = $payloads ?? new \SignTeb\WebChat\Export\LeadPayload();
    }

    public function is_enabled(): bool
    {
        return (int) $this->settings->get('webhook_enabled', 0) === 1
            && $this->url() !== '';
    }

    public function url(): string
    {
        return esc_url_raw((string) $this->settings->get('webhook_url', ''));
    }

    public function retry_enabled(): bool
    {
        return (int) $this->settings->get('webhook_retry', 1) === 1;
    }

    /**
     * Whether a given trigger event is enabled (empty setting = all events).
     */
    public function event_enabled(string $event): bool
    {
        $raw = trim((string) $this->settings->get('webhook_events', ''));
        if ($raw === '') {
            return true;
        }
        $enabled = array_filter(array_map('trim', explode(',', $raw)));
        return in_array($event, $enabled, true);
    }

    public function secret(): string
    {
        $enc = get_option(self::OPTION_SECRET, '');
        return is_string($enc) && $enc !== '' ? \SignTeb\WebChat\Core\Encryption::decrypt($enc) : '';
    }

    public static function save_secret(string $plain): void
    {
        $plain = trim($plain);
        if ($plain === '') {
            delete_option(self::OPTION_SECRET);
            return;
        }
        update_option(self::OPTION_SECRET, \SignTeb\WebChat\Core\Encryption::encrypt($plain), false);
    }

    /**
     * Build the payload for a lead and send it.
     *
     * @return array{ok:bool,status:string,response:string}
     */
    public function dispatch(int $lead_id, string $event, string $pdf_url = '', int $attempt = 1): array
    {
        if (! $this->is_enabled()) {
            return ['ok' => false, 'status' => 'disabled', 'response' => 'webhook_disabled'];
        }

        $payload = $this->payloads->build($lead_id, $pdf_url);
        if ($payload === null) {
            return ['ok' => false, 'status' => 'failed', 'response' => 'lead_not_found'];
        }
        $payload['event'] = $event;

        $log_id  = $this->logger->begin($lead_id, 'webhook', $event);
        $started = microtime(true);
        $result  = $this->send($payload, $event);

        if ($result['ok']) {
            $this->logger->finish($log_id, 'success', ['response' => 'HTTP ' . $result['code'], 'attempts' => $attempt, 'started' => $started]);
            return ['ok' => true, 'status' => 'success', 'response' => 'HTTP ' . $result['code']];
        }

        $should_retry = $this->retry_enabled() && $attempt < self::MAX_ATTEMPTS;
        $status       = $should_retry ? 'queued' : 'failed';
        $this->logger->finish($log_id, $status, ['response' => $result['error'], 'attempts' => $attempt, 'started' => $started]);

        if ($should_retry) {
            wp_schedule_single_event(time() + 300 * $attempt, 'swc_webhook_retry', [$lead_id, $event, $pdf_url, $attempt + 1]);
        }

        return ['ok' => false, 'status' => $status, 'response' => $result['error']];
    }

    /**
     * Low-level signed POST.
     *
     * @return array{ok:bool,code:int,body:string,error:string}
     */
    public function send(array $payload, string $event = 'manual'): array
    {
        $body   = wp_json_encode($payload);
        $secret = $this->secret();

        $headers = [
            'Content-Type'    => 'application/json',
            'X-Medora-Event'  => $event,
        ];
        if ($secret !== '') {
            $headers['X-Medora-Signature'] = 'sha256=' . hash_hmac('sha256', (string) $body, $secret);
        }

        $response = wp_remote_post($this->url(), [
            'timeout'     => 20,
            'headers'     => $headers,
            'body'        => $body,
            'blocking'    => true,
            'redirection' => 2,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'code' => 0, 'body' => '', 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $ok   = $code >= 200 && $code < 300;
        return [
            'ok'    => $ok,
            'code'  => $code,
            'body'  => (string) wp_remote_retrieve_body($response),
            'error' => $ok ? '' : ('HTTP ' . $code . ' ' . mb_substr((string) wp_remote_retrieve_body($response), 0, 300)),
        ];
    }

    /**
     * Fire a test ping so the admin can validate the endpoint.
     *
     * @return array{ok:bool,code:int,body:string,error:string}
     */
    public function test(): array
    {
        return $this->send([
            'event'   => 'test',
            'message' => 'Medora AI webhook test',
            'site'    => home_url(),
            'time'    => current_time('mysql'),
        ], 'test');
    }

    /**
     * WP-cron retry handler.
     */
    public function handle_retry(int $lead_id, string $event, string $pdf_url = '', int $attempt = 2): void
    {
        $this->dispatch($lead_id, $event, $pdf_url, $attempt);
    }
}

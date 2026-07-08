<?php
/**
 * SWC_Export_Logger — records the lifecycle of an export job.
 *
 * Thin layer over SWC_Sync_Log_Repository that also measures execution time
 * and mirrors failures to the PHP error log when WP_DEBUG is on.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Export_Logger
{
    private SWC_Sync_Log_Repository $repo;

    public function __construct(?SWC_Sync_Log_Repository $repo = null)
    {
        $this->repo = $repo ?? new SWC_Sync_Log_Repository();
    }

    /**
     * Open a log row in the 'pending' state. Returns its id.
     */
    public function begin(int $lead_id, string $provider, string $event): int
    {
        return $this->repo->add($lead_id, $provider, $event, 'pending');
    }

    /**
     * Close a log row with the final status.
     *
     * @param array{response?:mixed,attempts?:int,started?:float} $extra
     */
    public function finish(int $log_id, string $status, array $extra = []): void
    {
        $update = [];
        if (isset($extra['attempts'])) {
            $update['attempts'] = (int) $extra['attempts'];
        }
        if (isset($extra['response'])) {
            $update['response'] = is_scalar($extra['response']) ? (string) $extra['response'] : wp_json_encode($extra['response']);
        }
        if (isset($extra['started'])) {
            $update['duration_ms'] = (int) round((microtime(true) - (float) $extra['started']) * 1000);
        }

        $this->repo->update($log_id, $status, $update);

        if ($status === 'failed' && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Medora AI export] log #' . $log_id . ' failed: ' . ($update['response'] ?? ''));
        }
    }
}

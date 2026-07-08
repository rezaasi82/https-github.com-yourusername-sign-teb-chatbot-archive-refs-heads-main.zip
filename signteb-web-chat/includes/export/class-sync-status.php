<?php
/**
 * SWC_Sync_Status — resolves the latest sync state per lead for the admin UI.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Sync_Status
{
    private SWC_Sync_Log_Repository $logs;

    public function __construct(?SWC_Sync_Log_Repository $logs = null)
    {
        $this->logs = $logs ?? new SWC_Sync_Log_Repository();
    }

    /**
     * @return array<string,string> provider => status
     */
    public function for_lead(int $lead_id): array
    {
        $out = ['webhook' => 'none', 'google_sheets' => 'none', 'pdf' => 'none'];
        foreach ($this->logs->latest_for_lead($lead_id) as $provider => $row) {
            if (isset($out[$provider])) {
                $out[$provider] = (string) $row->status;
            }
        }
        return $out;
    }

    /**
     * Render a WordPress-native status badge.
     */
    public static function badge(string $status): string
    {
        $map = [
            'success' => ['بروزرسانی شد', '#e6f4ea', '#1a7f37'],
            'failed'  => ['ناموفق', '#fbeaea', '#d63638'],
            'pending' => ['در انتظار', '#fef8e7', '#8a6d1b'],
            'queued'  => ['در صف', '#eef1f7', '#50607a'],
            'none'    => ['—', 'transparent', '#7a869e'],
        ];
        [$label, $bg, $fg] = $map[$status] ?? $map['none'];
        if ($status === 'none') {
            return '<span style="color:' . esc_attr($fg) . '">—</span>';
        }
        return '<span class="swc-pill" style="background:' . esc_attr($bg) . ';color:' . esc_attr($fg) . '">' . esc_html($label) . '</span>';
    }
}

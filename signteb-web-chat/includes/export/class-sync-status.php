<?php
/**
 * Resolves the latest sync state per lead for the admin UI.
 *
 * @package Medora
 */

namespace Medora\Export;

if (! defined('ABSPATH')) {
    exit;
}

class SyncStatus
{
    private \Medora\Database\SyncLogRepository $logs;

    public function __construct(?\Medora\Database\SyncLogRepository $logs = null)
    {
        $this->logs = $logs ?? new \Medora\Database\SyncLogRepository();
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
     * Batched variant for a whole page of leads (single query, no N+1).
     *
     * @param array<int,int> $lead_ids
     * @return array<int,array<string,string>> lead_id => [provider => status]
     */
    public function for_leads(array $lead_ids): array
    {
        $defaults = ['webhook' => 'none', 'google_sheets' => 'none', 'pdf' => 'none'];
        $map      = $this->logs->latest_for_leads($lead_ids);

        $out = [];
        foreach ($lead_ids as $id) {
            $id       = (int) $id;
            $out[$id] = array_merge($defaults, $map[$id] ?? []);
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

<?php
/**
 * SWC_Lead_CRM — lead pipeline vocabulary and the secure save endpoint.
 *
 * Self-registering admin service: owns the CRM lead statuses and the AJAX
 * handler that persists status / email / notes / tags for a lead. Wire once
 * from SWC_Plugin::boot() with ( new SWC_Lead_CRM() )->register().
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Lead_CRM
{
    private const NONCE = 'swc_crm';

    /** Ordered pipeline stages. Keys are stored; labels are shown. */
    public const STATUSES = [
        'new'       => ['لید جدید', '#6366f1'],
        'contacted' => ['تماس گرفته‌شد', '#0ea5e9'],
        'follow_up' => ['پیگیری', '#f59e0b'],
        'booked'    => ['رزرو شد', '#c8a04e'],
        'visited'   => ['مراجعه کرد', '#10b981'],
        'closed'    => ['بسته‌شده (موفق)', '#059669'],
        'lost'      => ['ازدست‌رفته', '#9ca3af'],
    ];

    public function register(): void
    {
        add_action('wp_ajax_swc_lead_update', [$this, 'ajax_update']);
    }

    public static function nonce(): string
    {
        return wp_create_nonce(self::NONCE);
    }

    public static function label(string $status): string
    {
        return isset(self::STATUSES[$status]) ? self::STATUSES[$status][0] : self::STATUSES['new'][0];
    }

    public static function color(string $status): string
    {
        return isset(self::STATUSES[$status]) ? self::STATUSES[$status][1] : self::STATUSES['new'][1];
    }

    public static function is_valid(string $status): bool
    {
        return isset(self::STATUSES[$status]);
    }

    public function ajax_update(): void
    {
        SWC_Json_Guard::arm();

        if (SWC_Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            SWC_Security::note_failure('crm_update');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }

        $lead_id = absint($_POST['lead_id'] ?? 0);
        if ($lead_id <= 0) {
            wp_send_json(['ok' => false, 'error' => 'bad_lead'], 400);
        }

        $repo   = new SWC_Conversation_Repository();
        $fields = [];

        if (isset($_POST['lead_status'])) {
            $status = sanitize_key(wp_unslash($_POST['lead_status']));
            if (self::is_valid($status)) {
                $fields['lead_status'] = $status;
            }
        }
        if (isset($_POST['email'])) {
            $fields['email'] = sanitize_email(wp_unslash($_POST['email']));
        }
        if (isset($_POST['tags'])) {
            $fields['tags'] = sanitize_text_field(wp_unslash($_POST['tags']));
        }
        if (isset($_POST['notes'])) {
            $fields['notes'] = sanitize_textarea_field(wp_unslash($_POST['notes']));
        }
        if (isset($_POST['branch_id'])) {
            $branch = absint($_POST['branch_id']);
            if ($branch === 0 || (new SWC_Branch_Repository())->exists($branch)) {
                $fields['branch_id'] = $branch;
            }
        }

        if ($fields === []) {
            wp_send_json(['ok' => false, 'error' => 'nothing_to_update'], 400);
        }

        $repo->update_crm($lead_id, $fields);
        do_action('swc_lead_crm_updated', $lead_id, $fields);

        wp_send_json(['ok' => true, 'lead_id' => $lead_id, 'fields' => array_keys($fields)]);
    }
}

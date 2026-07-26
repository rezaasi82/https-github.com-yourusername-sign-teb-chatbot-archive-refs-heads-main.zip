<?php
/**
 * Lead pipeline vocabulary and the secure save endpoint.
 *
 * Self-registering admin service: owns the CRM lead statuses and the AJAX
 * handler that persists status / email / notes / tags for a lead. Wire once
 * from Plugin::boot() with ( new LeadCrm() )->register().
 *
 * @package Medora
 */

namespace Medora\Crm;

if (! defined('ABSPATH')) {
    exit;
}

class LeadCrm
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
        add_action('wp_ajax_swc_lead_refer', [$this, 'ajax_refer']);
    }

    /**
     * A shareable plain-text summary of a lead — reused for the email body and
     * the SMS deep-link. Contains only what a colleague needs to follow up.
     */
    public static function referral_text(object $c): string
    {
        $scores = [
            'hot'  => __('داغ', 'signteb-web-chat'),
            'warm' => __('متوسط', 'signteb-web-chat'),
            'cold' => __('سرد', 'signteb-web-chat'),
        ];
        $lines = [
            __('ارجاع لید از Medora AI', 'signteb-web-chat'),
            sprintf(__('نام: %s', 'signteb-web-chat'), trim((string) ($c->patient_name ?? '')) ?: '—'),
            sprintf(__('موبایل: %s', 'signteb-web-chat'), trim((string) ($c->patient_phone ?? '')) ?: '—'),
            sprintf(__('امتیاز: %s', 'signteb-web-chat'), $scores[$c->lead_score ?? ''] ?? '—'),
            sprintf(__('وضعیت: %s', 'signteb-web-chat'), self::label((string) ($c->lead_status ?? 'new'))),
        ];
        $summary = trim((string) ($c->summary ?? ''));
        if ($summary !== '') {
            $lines[] = "\n" . __('خلاصه گفتگو:', 'signteb-web-chat') . "\n" . $summary;
        }
        return implode("\n", $lines);
    }

    /**
     * Forward a lead to a colleague by email (wp_mail). No external gateway —
     * uses whatever mailer the WordPress install is configured with.
     */
    public function ajax_refer(): void
    {
        \Medora\Core\JsonGuard::arm();

        if (\Medora\Security\Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            \Medora\Security\Security::note_failure('crm_refer');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }

        $lead_id = \Medora\Core\Input::post_int('lead_id');
        $to      = \Medora\Core\Input::post_email('to');
        if ($lead_id <= 0) {
            wp_send_json(['ok' => false, 'error' => 'bad_lead'], 400);
        }
        if (! is_email($to)) {
            wp_send_json(['ok' => false, 'error' => __('ایمیل مقصد نامعتبر است.', 'signteb-web-chat')], 400);
        }

        $c = (new \Medora\Database\ConversationRepository())->get($lead_id);
        if (! $c) {
            wp_send_json(['ok' => false, 'error' => 'not_found'], 404);
        }

        $clinic  = (string) (new \Medora\Core\Settings())->get('clinic_name', get_bloginfo('name'));
        $subject = sprintf(__('[%s] ارجاع لید — %s', 'signteb-web-chat'), $clinic, trim((string) ($c->patient_name ?? '')) ?: ('#' . $lead_id));
        $body    = self::referral_text($c) . "\n\n" . admin_url('admin.php?page=swc-chat&tab=conversations&conversation=' . $lead_id);

        $sent = wp_mail($to, $subject, $body);
        \Medora\Security\AuditLog::record('lead_refer', ['object' => (string) $lead_id, 'severity' => $sent ? 'info' : 'warning']);

        if (! $sent) {
            wp_send_json(['ok' => false, 'error' => __('ارسال ایمیل ناموفق بود. تنظیمات ایمیل سایت را بررسی کنید.', 'signteb-web-chat')], 500);
        }
        wp_send_json(['ok' => true, 'lead_id' => $lead_id]);
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
        \Medora\Core\JsonGuard::arm();

        if (\Medora\Security\Security::is_locked()) {
            wp_send_json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            \Medora\Security\Security::note_failure('crm_update');
            wp_send_json(['ok' => false, 'error' => 'unauthorized'], 403);
        }

        $lead_id = \Medora\Core\Input::post_int('lead_id');
        if ($lead_id <= 0) {
            wp_send_json(['ok' => false, 'error' => 'bad_lead'], 400);
        }

        $repo   = new \Medora\Database\ConversationRepository();
        $fields = [];

        if (\Medora\Core\Input::has_post('lead_status')) {
            $status = \Medora\Core\Input::post_key('lead_status');
            if (self::is_valid($status)) {
                $fields['lead_status'] = $status;
            }
        }
        if (\Medora\Core\Input::has_post('email')) {
            $fields['email'] = \Medora\Core\Input::post_email('email');
        }
        if (\Medora\Core\Input::has_post('tags')) {
            $fields['tags'] = \Medora\Core\Input::post_text('tags');
        }
        if (\Medora\Core\Input::has_post('notes')) {
            $fields['notes'] = \Medora\Core\Input::post_textarea('notes');
        }
        if (\Medora\Core\Input::has_post('branch_id')) {
            $branch = \Medora\Core\Input::post_int('branch_id');
            if ($branch === 0 || (new \Medora\Database\BranchRepository())->exists($branch)) {
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

<?php

namespace Nobatyar\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

class SmsLogRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_sms_logs';
    }

    public function log(
        ?int $booking_id,
        string $provider_name,
        string $recipient_phone,
        string $message,
        string $status,
        ?string $response_payload,
        ?string $sent_at
    ): int {
        global $wpdb;

        $wpdb->insert(
            $this->table(),
            [
                'booking_id'       => $booking_id,
                'provider_name'    => $provider_name,
                'recipient_phone'  => $recipient_phone,
                'message'          => $message,
                'status'           => $status,
                'response_payload' => $response_payload,
                'sent_at'          => $sent_at,
                'created_at'       => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }
}

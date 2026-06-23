<?php

namespace Nobatyar\Notifications;

use Nobatyar\Booking\BookingRepository;
use Nobatyar\Booking\BookingStatus;
use Nobatyar\Calendar\JalaliConverter;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Listens for booking lifecycle events and the reminder cron, then fans each
 * one out across the active SMS/WhatsApp channel (SmsProviderFactory) and
 * email - every SMS attempt is logged to nby_sms_logs regardless of outcome.
 */
class NotificationDispatcher
{
    private BookingRepository $booking_repository;
    private ProviderRepository $provider_repository;
    private ServiceRepository $service_repository;
    private SmsLogRepository $sms_log_repository;
    private EmailNotifier $email_notifier;

    public function __construct(
        BookingRepository $booking_repository,
        ProviderRepository $provider_repository,
        ServiceRepository $service_repository,
        SmsLogRepository $sms_log_repository,
        EmailNotifier $email_notifier
    ) {
        $this->booking_repository  = $booking_repository;
        $this->provider_repository = $provider_repository;
        $this->service_repository  = $service_repository;
        $this->sms_log_repository  = $sms_log_repository;
        $this->email_notifier      = $email_notifier;
    }

    public function register(): void
    {
        add_action('nobatyar_booking_created', [$this, 'on_booking_created']);
        add_action('nobatyar_booking_status_changed', [$this, 'on_booking_status_changed'], 10, 3);
        add_action('nobatyar_send_reminders', [$this, 'send_due_reminders']);
    }

    public function on_booking_created(int $booking_id): void
    {
        $booking = $this->booking_repository->find($booking_id);

        if ($booking) {
            $this->notify($booking, 'created');
        }
    }

    public function on_booking_status_changed(int $booking_id, string $old_status, string $new_status): void
    {
        if (! in_array($new_status, [BookingStatus::CONFIRMED, BookingStatus::CANCELLED], true)) {
            return;
        }

        $booking = $this->booking_repository->find($booking_id);

        if ($booking) {
            $this->notify($booking, $new_status === BookingStatus::CONFIRMED ? 'confirmed' : 'cancelled');
        }
    }

    public function send_due_reminders(): void
    {
        $hours_before = (int) apply_filters('nobatyar_reminder_hours_before', 24);

        foreach ($this->booking_repository->get_due_reminders($hours_before) as $booking) {
            $this->notify($booking, 'reminder');
            $this->booking_repository->mark_reminder_sent((int) $booking['id']);
        }
    }

    private function notify(array $booking, string $event): void
    {
        $message = $this->render_message($event, $booking);

        foreach (SmsProviderFactory::active_providers() as $provider) {
            $result = $provider->send($booking['customer_phone'], $message);

            $this->sms_log_repository->log(
                (int) $booking['id'],
                $provider->get_name(),
                $booking['customer_phone'],
                $message,
                $result->is_success() ? 'sent' : 'failed',
                $result->response_payload(),
                $result->is_success() ? current_time('mysql') : null
            );
        }

        $settings = apply_filters('nobatyar_notification_settings', get_option('nobatyar_notification_settings', [
            'email_customer' => true,
            'email_admin'    => true,
        ]));

        $subject = $this->subject_for($event);

        if (! empty($settings['email_customer']) && ! empty($booking['customer_email'])) {
            $this->email_notifier->send($booking['customer_email'], $subject, $message);
        }

        $admin_email = get_option('admin_email');

        if (! empty($settings['email_admin']) && $admin_email) {
            $this->email_notifier->send($admin_email, $subject . ' (ادمین)', $message);
        }
    }

    private function subject_for(string $event): string
    {
        $subjects = [
            'created'   => __('ثبت نوبت جدید', 'nobatyar-booking'),
            'confirmed' => __('تأیید نوبت', 'nobatyar-booking'),
            'cancelled' => __('لغو نوبت', 'nobatyar-booking'),
            'reminder'  => __('یادآوری نوبت', 'nobatyar-booking'),
        ];

        return $subjects[$event] ?? __('اطلاع‌رسانی نوبت', 'nobatyar-booking');
    }

    private function render_message(string $event, array $booking): string
    {
        $provider = $this->provider_repository->find((int) $booking['provider_id']);
        $service  = $this->service_repository->find((int) $booking['service_id']);

        $templates = [
            'created'   => __('%1$s عزیز، نوبت شما برای %2$s نزد %3$s در تاریخ %4$s ثبت شد.', 'nobatyar-booking'),
            'confirmed' => __('%1$s عزیز، نوبت شما برای %2$s نزد %3$s در تاریخ %4$s تأیید شد.', 'nobatyar-booking'),
            'cancelled' => __('%1$s عزیز، نوبت شما برای %2$s نزد %3$s در تاریخ %4$s لغو شد.', 'nobatyar-booking'),
            'reminder'  => __('%1$s عزیز، یادآوری می‌شود نوبت شما برای %2$s نزد %3$s در تاریخ %4$s است.', 'nobatyar-booking'),
        ];

        $message = sprintf(
            $templates[$event] ?? '%1$s %2$s %3$s %4$s',
            $booking['customer_name'],
            $service['name'] ?? '',
            $provider['name'] ?? '',
            $this->format_jalali_datetime($booking['booking_datetime'])
        );

        return apply_filters('nobatyar_notification_message', $message, $event, $booking);
    }

    private function format_jalali_datetime(string $booking_datetime): string
    {
        [$date, $time] = explode(' ', $booking_datetime);

        return sprintf('%s ساعت %s', JalaliConverter::gregorian_to_jalali_string($date), substr($time, 0, 5));
    }
}

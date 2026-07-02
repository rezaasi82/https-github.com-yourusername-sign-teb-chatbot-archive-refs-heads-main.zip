<?php
/**
 * SignTeb Medical Core — SMS Notifier
 *
 * قالب پیام‌ها و trigger ارسال در نقاط چرخه نوبت:
 * - تأیید ثبت نوبت (بلافاصله)
 * - تغییر وضعیت (تأیید شده / لغو شده)
 * - یادآوری ۲۴ ساعت قبل (توسط Cron)
 * - یادآوری ۲ ساعت قبل (توسط Cron)
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Sms;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Notifier {

	private MeliPayamakClient $client;

	public function __construct( private readonly Loader $loader ) {
		$this->client = new MeliPayamakClient();

		// نوبت جدید ثبت شد → SMS تأیید دریافت
		$this->loader->add_action( 'stmc_appointment_created', $this, 'on_appointment_created', 10, 2 );

		// تغییر وضعیت توسط ادمین → SMS اطلاع‌رسانی
		$this->loader->add_action( 'stmc_appointment_status_changed', $this, 'on_status_changed', 10, 3 );

		// Cron برای یادآوری‌ها
		$this->loader->add_action( 'stmc_sms_reminder_cron', $this, 'process_reminders' );

		// ثبت بازه زمانی سفارشی برای cron (هر ۱۵ دقیقه)
		add_filter( 'cron_schedules', [ $this, 'register_cron_interval' ] ); // phpcs:ignore
	}

	public function register_cron_interval( array $schedules ): array {
		$schedules['stmc_fifteen_minutes'] = [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'هر ۱۵ دقیقه', 'signteb-medical-core' ),
		];
		return $schedules;
	}

	// ─── Trigger: نوبت جدید ثبت شد ─────────────────────────────────────────────

	public function on_appointment_created( int $appt_id, array $data ): void {
		if ( '1' !== get_option( 'stmc_sms_confirmation_enabled', '1' ) ) {
			return;
		}
		if ( ! $this->client->is_configured() ) {
			return;
		}
		if ( empty( $data['phone'] ) ) {
			return;
		}

		$doctor_name = $data['doctor_id'] ? get_the_title( $data['doctor_id'] ) : '';
		$clinic_name = get_option( 'stmc_clinic_name', get_bloginfo( 'name' ) );

		if ( ! empty( $data['appt_date'] ) && ! empty( $data['appt_time'] ) ) {
			// نوبت با تاریخ/ساعت مشخص رزرو شده
			$date_fa = $this->format_date_fa( $data['appt_date'] );
			$time_fa = $this->format_time_fa( $data['appt_time'] );

			$text = sprintf(
				"%s\nنوبت شما ثبت شد ✅\nپزشک: %s\nتاریخ: %s\nساعت: %s\nکد پیگیری: #%d\nدر صورت نیاز به تغییر، با ما تماس بگیرید.",
				$clinic_name,
				$doctor_name ?: '—',
				$date_fa,
				$time_fa,
				$appt_id
			);
		} else {
			// نوبت بدون زمان دقیق (حالت قدیمی درخواست تماس)
			$text = sprintf(
				"%s\nدرخواست نوبت شما دریافت شد ✅\nپزشک: %s\nبه زودی برای هماهنگی زمان با شما تماس می‌گیریم.\nکد پیگیری: #%d",
				$clinic_name,
				$doctor_name ?: '—',
				$appt_id
			);
		}

		$this->dispatch( $appt_id, $data['phone'], 'confirmation', $text );
	}

	// ─── Trigger: تغییر وضعیت نوبت ─────────────────────────────────────────────

	public function on_status_changed( int $appt_id, string $new_status, array $appt ): void {
		if ( ! $this->client->is_configured() ) {
			return;
		}
		if ( empty( $appt['phone'] ) ) {
			return;
		}

		$clinic_name = get_option( 'stmc_clinic_name', get_bloginfo( 'name' ) );
		$doctor_name = $appt['doctor_id'] ? get_the_title( (int) $appt['doctor_id'] ) : '';

		$text = match ( $new_status ) {
			'confirmed' => sprintf(
				"%s\nنوبت شما تأیید شد ✅\nپزشک: %s\nتاریخ: %s\nساعت: %s\nلطفاً ۱۵ دقیقه زودتر حضور داشته باشید.",
				$clinic_name, $doctor_name ?: '—',
				$this->format_date_fa( $appt['appt_date'] ?? '' ),
				$this->format_time_fa( $appt['appt_time'] ?? '' )
			),
			'cancelled' => sprintf(
				"%s\nنوبت شما لغو شد ❌\nکد پیگیری: #%d\nبرای رزرو مجدد با ما تماس بگیرید.",
				$clinic_name, $appt_id
			),
			default => null,
		};

		if ( null === $text ) {
			return;
		}

		$type = 'cancelled' === $new_status ? 'cancellation' : 'status_change';
		$this->dispatch( $appt_id, $appt['phone'], $type, $text );
	}

	// ─── Cron: پردازش یادآوری‌های ۲۴ ساعته و ۲ ساعته ──────────────────────────

	public function process_reminders(): void {
		if ( ! $this->client->is_configured() ) {
			return;
		}

		global $wpdb;
		$now = current_time( 'timestamp' );

		// ── یادآوری ۲۴ ساعت قبل ──
		if ( '1' === get_option( 'stmc_sms_reminder_24h_enabled', '1' ) ) {
			$target_date = wp_date( 'Y-m-d', $now + DAY_IN_SECONDS );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}stmc_appointments
				 WHERE appt_date = %s AND status = 'confirmed' AND reminder_24h_sent = 0",
				$target_date
			), ARRAY_A );

			foreach ( $rows as $row ) {
				$this->send_reminder( $row, 'reminder_24h' );
				$wpdb->update(
					$wpdb->prefix . 'stmc_appointments',
					[ 'reminder_24h_sent' => 1 ],
					[ 'id' => $row['id'] ],
					[ '%d' ], [ '%d' ]
				);
			}
		}

		// ── یادآوری ۲ ساعت قبل ──
		if ( '1' === get_option( 'stmc_sms_reminder_2h_enabled', '1' ) ) {
			$window_start = $now + ( 2 * HOUR_IN_SECONDS ) - ( 7 * MINUTE_IN_SECONDS );
			$window_end   = $now + ( 2 * HOUR_IN_SECONDS ) + ( 8 * MINUTE_IN_SECONDS );

			$today = wp_date( 'Y-m-d', $now );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}stmc_appointments
				 WHERE appt_date = %s AND status = 'confirmed' AND reminder_2h_sent = 0
				 AND appt_time IS NOT NULL",
				$today
			), ARRAY_A );

			foreach ( $rows as $row ) {
				$appt_ts = strtotime( $row['appt_date'] . ' ' . $row['appt_time'] );
				if ( $appt_ts >= $window_start && $appt_ts <= $window_end ) {
					$this->send_reminder( $row, 'reminder_2h' );
					$wpdb->update(
						$wpdb->prefix . 'stmc_appointments',
						[ 'reminder_2h_sent' => 1 ],
						[ 'id' => $row['id'] ],
						[ '%d' ], [ '%d' ]
					);
				}
			}
		}
	}

	private function send_reminder( array $row, string $type ): void {
		$clinic_name = get_option( 'stmc_clinic_name', get_bloginfo( 'name' ) );
		$doctor_name = $row['doctor_id'] ? get_the_title( (int) $row['doctor_id'] ) : '';

		$hours_label = 'reminder_24h' === $type ? 'فردا' : 'تا ۲ ساعت دیگر';

		$text = sprintf(
			"⏰ یادآوری نوبت\n%s\n%s نوبت دارید نزد %s\nساعت: %s\nلطفاً به موقع حضور داشته باشید.",
			$clinic_name,
			$hours_label,
			$doctor_name ?: 'کلینیک',
			$this->format_time_fa( $row['appt_time'] ?? '' )
		);

		$this->dispatch( (int) $row['id'], $row['phone'], $type, $text );
	}

	// ─── Dispatch + Log ────────────────────────────────────────────────────────

	private function dispatch( int $appt_id, string $phone, string $type, string $text ): void {
		global $wpdb;

		$result = $this->client->send( $phone, $text );

		$wpdb->insert(
			$wpdb->prefix . 'stmc_sms_log',
			[
				'appointment_id'    => $appt_id,
				'phone'             => $phone,
				'type'              => $type,
				'message'           => $text,
				'provider_response' => $result['raw'],
				'status'            => $result['success'] ? 'sent' : 'failed',
				'sent_at'           => $result['success'] ? current_time( 'mysql' ) : null,
				'created_at'        => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function format_date_fa( string $date ): string {
		if ( ! $date ) {
			return '—';
		}
		$ts = strtotime( $date );
		$weekdays = [ 'یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه' ];
		$weekday  = $weekdays[ (int) wp_date( 'w', $ts ) ];
		return $weekday . ' ' . wp_date( 'Y/m/d', $ts );
	}

	private function format_time_fa( string $time ): string {
		if ( ! $time ) {
			return '—';
		}
		return substr( $time, 0, 5 );
	}
}

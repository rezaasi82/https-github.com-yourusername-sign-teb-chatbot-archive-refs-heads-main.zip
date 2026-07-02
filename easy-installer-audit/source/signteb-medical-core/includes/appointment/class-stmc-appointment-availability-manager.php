<?php
/**
 * SignTeb Medical Core — Availability Manager
 *
 * موتور اصلی محاسبه Slot‌های خالی نوبت‌دهی:
 * - خواندن availability هفتگی پزشک از stmc_doctor_availability
 * - اعمال استثناها (تعطیلی/ساعت خاص) از stmc_doctor_exceptions
 * - حذف Slot‌هایی که از قبل رزرو شده‌اند (pending/confirmed)
 * - رعایت stmc_booking_lead_hours و stmc_booking_max_days
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Appointment;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class AvailabilityManager {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'wp_ajax_stmc_get_available_days',   $this, 'ajax_get_available_days' );
		$this->loader->add_action( 'wp_ajax_nopriv_stmc_get_available_days', $this, 'ajax_get_available_days' );

		$this->loader->add_action( 'wp_ajax_stmc_get_available_slots',  $this, 'ajax_get_available_slots' );
		$this->loader->add_action( 'wp_ajax_nopriv_stmc_get_available_slots', $this, 'ajax_get_available_slots' );
	}

	// ─── AJAX: لیست روزهای دارای ظرفیت در یک ماه ──────────────────────────────

	public function ajax_get_available_days(): void {
		check_ajax_referer( 'stmc_appointment_nonce', 'nonce' );

		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );
		$month     = sanitize_text_field( $_POST['month'] ?? wp_date( 'Y-m' ) ); // فرمت Y-m

		if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) ) {
			wp_send_json_error( [ 'message' => __( 'پزشک نامعتبر است.', 'signteb-medical-core' ) ] );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = wp_date( 'Y-m' );
		}

		$days = $this->get_days_with_capacity( $doctor_id, $month );

		wp_send_json_success( [ 'days' => $days ] );
	}

	// ─── AJAX: لیست Slot‌های خالی یک روز خاص ──────────────────────────────────

	public function ajax_get_available_slots(): void {
		check_ajax_referer( 'stmc_appointment_nonce', 'nonce' );

		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );
		$date      = sanitize_text_field( $_POST['date'] ?? '' );

		if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) ) {
			wp_send_json_error( [ 'message' => __( 'پزشک نامعتبر است.', 'signteb-medical-core' ) ] );
		}

		if ( ! $this->is_valid_date( $date ) ) {
			wp_send_json_error( [ 'message' => __( 'تاریخ نامعتبر است.', 'signteb-medical-core' ) ] );
		}

		$slots = $this->get_available_slots( $doctor_id, $date );

		wp_send_json_success( [ 'slots' => $slots ] );
	}

	// ─── Core: روزهای دارای ظرفیت در یک ماه ───────────────────────────────────

	/**
	 * @return array<string,bool> ['2025-01-15' => true, ...]
	 */
	public function get_days_with_capacity( int $doctor_id, string $month_str ): array {
		[$year, $month] = array_map( 'intval', explode( '-', $month_str ) );

		$first_day   = sprintf( '%04d-%02d-01', $year, $month );
		$days_in_mo  = (int) wp_date( 't', strtotime( $first_day ) );
		$today       = wp_date( 'Y-m-d' );
		$max_date    = wp_date( 'Y-m-d', strtotime( '+' . (int) get_option( 'stmc_booking_max_days', 30 ) . ' days' ) );

		$result = [];

		for ( $d = 1; $d <= $days_in_mo; $d++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $d );

			// رد کردن روزهای گذشته یا فراتر از سقف رزرو
			if ( $date < $today || $date > $max_date ) {
				continue;
			}

			$slots = $this->get_available_slots( $doctor_id, $date );

			if ( ! empty( $slots ) ) {
				$result[ $date ] = true;
			}
		}

		return $result;
	}

	// ─── Core: تولید Slot‌های خالی یک روز ──────────────────────────────────────

	/**
	 * @return array<array{time:string,label:string}>
	 */
	public function get_available_slots( int $doctor_id, string $date ): array {
		global $wpdb;

		// ۱. بررسی استثنا برای این روز
		$exception = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}stmc_doctor_exceptions WHERE doctor_id=%d AND exception_date=%s",
			$doctor_id, $date
		) );

		if ( $exception && 'closed' === $exception->type ) {
			return []; // روز تعطیل اعلام شده
		}

		// ۲. تعیین بازه ساعتی و مدت اسلات
		if ( $exception && 'custom' === $exception->type ) {
			$start_time   = $exception->start_time;
			$end_time     = $exception->end_time;
			$slot_minutes = (int) ( $exception->slot_minutes ?: get_option( 'stmc_default_slot_minutes', 30 ) );
		} else {
			$weekday = (int) wp_date( 'w', strtotime( $date ) ); // 0=Sunday

			$avail = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}stmc_doctor_availability
				 WHERE doctor_id=%d AND weekday=%d AND is_active=1",
				$doctor_id, $weekday
			) );

			if ( ! $avail ) {
				return []; // این روز هفته اصلاً تعریف نشده — یعنی پزشک کار نمی‌کند
			}

			$start_time   = $avail->start_time;
			$end_time     = $avail->end_time;
			$slot_minutes = (int) $avail->slot_minutes;
		}

		if ( $slot_minutes <= 0 ) {
			$slot_minutes = 30;
		}

		// ۳. تولید لیست کامل Slot‌ها بین start_time و end_time
		$all_slots = $this->generate_time_slots( $start_time, $end_time, $slot_minutes );

		// ۴. حذف Slot‌های رزرو شده (pending یا confirmed)
		$booked = $wpdb->get_col( $wpdb->prepare(
			"SELECT appt_time FROM {$wpdb->prefix}stmc_appointments
			 WHERE doctor_id=%d AND appt_date=%s AND status IN ('pending','confirmed')",
			$doctor_id, $date
		) );

		$booked_times = array_map( fn( $t ) => substr( (string) $t, 0, 5 ), $booked ); // HH:MM

		// ۵. حذف Slot‌هایی که کمتر از stmc_booking_lead_hours فاصله دارند (فقط برای امروز)
		$lead_hours = (int) get_option( 'stmc_booking_lead_hours', 2 );
		$now_ts     = current_time( 'timestamp' );
		$is_today   = ( $date === wp_date( 'Y-m-d' ) );

		$result = [];

		foreach ( $all_slots as $slot ) {
			if ( in_array( $slot, $booked_times, true ) ) {
				continue;
			}

			if ( $is_today ) {
				$slot_ts = strtotime( $date . ' ' . $slot . ':00' );
				if ( $slot_ts - $now_ts < $lead_hours * HOUR_IN_SECONDS ) {
					continue;
				}
			}

			$result[] = [
				'time'  => $slot,
				'label' => $this->format_time_fa( $slot ),
			];
		}

		return $result;
	}

	/**
	 * تولید لیست ساعت‌ها بین دو بازه با گام مشخص
	 * @return string[] فرمت HH:MM
	 */
	private function generate_time_slots( string $start, string $end, int $step_minutes ): array {
		$slots     = [];
		$start_min = $this->time_to_minutes( $start );
		$end_min   = $this->time_to_minutes( $end );

		for ( $m = $start_min; $m + $step_minutes <= $end_min; $m += $step_minutes ) {
			$slots[] = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
		}

		return $slots;
	}

	private function time_to_minutes( string $time ): int {
		[$h, $m] = array_map( 'intval', explode( ':', substr( $time, 0, 5 ) ) );
		return $h * 60 + $m;
	}

	private function format_time_fa( string $time ): string {
		[$h, $m] = explode( ':', $time );
		$h = (int) $h;
		$period = $h >= 12 ? 'ب.ظ' : 'ق.ظ';
		$h12    = $h % 12 === 0 ? 12 : $h % 12;
		$fa_digits = [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ];
		$formatted = str_replace( range( 0, 9 ), $fa_digits, sprintf( '%d:%s', $h12, $m ) );
		return $formatted . ' ' . $period;
	}

	private function is_valid_date( string $date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		[$y, $m, $d] = array_map( 'intval', explode( '-', $date ) );
		return checkdate( $m, $d, $y );
	}

	// ─── Public helper: آیا یک slot هنوز خالی است؟ (برای جلوگیری از race condition) ──

	public function is_slot_available( int $doctor_id, string $date, string $time ): bool {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}stmc_appointments
			 WHERE doctor_id=%d AND appt_date=%s AND appt_time=%s AND status IN ('pending','confirmed')",
			$doctor_id, $date, $time . ':00'
		) );

		return 0 === (int) $exists;
	}
}

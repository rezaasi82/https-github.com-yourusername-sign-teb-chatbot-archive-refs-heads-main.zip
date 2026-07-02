<?php
/**
 * SignTeb Medical Core — Appointment AJAX Handler
 *
 * ثبت نوبت با تاریخ/ساعت دقیق، ارسال SMS، ذخیره در DB
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Appointment;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Ajax {

	private AvailabilityManager $availability;

	public function __construct( private readonly Loader $loader ) {
		$this->availability = new AvailabilityManager( $loader );

		$this->loader->add_action( 'wp_ajax_stmc_submit_appointment',        $this, 'handle' );
		$this->loader->add_action( 'wp_ajax_nopriv_stmc_submit_appointment', $this, 'handle' );
	}

	// ─── Main Handler ─────────────────────────────────────────────────────────

	public function handle(): void {
		// Verify nonce
		$nonce = sanitize_text_field( wp_unslash( $_POST['stmc_appointment_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'stmc_appointment_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'خطای امنیتی. لطفاً صفحه را رفرش کنید.', STMC_TEXT ) ], 403 );
		}

		// Validate & sanitize
		$data = $this->validate_and_sanitize( $_POST );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( [ 'message' => $data->get_error_message() ] );
		}

		// Rate limit: 3 submissions per IP per hour
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error( [ 'message' => __( 'درخواست زیادی ثبت کرده‌اید. لطفاً بعداً امتحان کنید.', STMC_TEXT ) ] );
		}

		// بررسی double-booking — اگر تاریخ/ساعت انتخاب شده، باید هنوز خالی باشد
		if ( $data['appt_date'] && $data['appt_time'] && $data['doctor_id'] ) {
			if ( ! $this->availability->is_slot_available( $data['doctor_id'], $data['appt_date'], $data['appt_time'] ) ) {
				wp_send_json_error( [
					'message'    => __( 'متأسفانه این زمان همین الان توسط شخص دیگری رزرو شد. لطفاً زمان دیگری انتخاب کنید.', STMC_TEXT ),
					'slot_taken' => true,
				] );
			}
		}

		// Save to DB
		$insert_id = $this->save_to_db( $data );
		if ( ! $insert_id ) {
			wp_send_json_error( [ 'message' => __( 'خطا در ثبت اطلاعات. لطفاً مجدداً تلاش کنید.', STMC_TEXT ) ] );
		}

		// Send emails
		$this->send_admin_email( $data, $insert_id );
		$this->send_patient_email( $data );

		// Hook for SMS notifier + WhatsApp etc.
		do_action( 'stmc_appointment_created', $insert_id, $data );

		$message = ( $data['appt_date'] && $data['appt_time'] )
			? __( '✅ نوبت شما با موفقیت رزرو شد. پیامک تأیید برای شما ارسال می‌شود.', STMC_TEXT )
			: __( '✅ درخواست نوبت شما با موفقیت ثبت شد. به زودی با شما تماس خواهیم گرفت.', STMC_TEXT );

		wp_send_json_success( [
			'message' => $message,
			'id'      => $insert_id,
		] );
	}

	// ─── Validate ─────────────────────────────────────────────────────────────

	private function validate_and_sanitize( array $post ): array|\WP_Error {
		$name  = sanitize_text_field( wp_unslash( $post['stmc_name'] ?? '' ) );
		$phone = preg_replace( '/[^0-9+\-\s]/', '', $post['stmc_phone'] ?? '' );
		$email = sanitize_email( $post['stmc_email'] ?? '' );

		if ( empty( $name ) || mb_strlen( $name ) < 2 ) {
			return new \WP_Error( 'invalid_name', __( 'لطفاً نام کامل خود را وارد کنید.', STMC_TEXT ) );
		}

		if ( empty( $phone ) || strlen( $phone ) < 10 ) {
			return new \WP_Error( 'invalid_phone', __( 'شماره تلفن نامعتبر است.', STMC_TEXT ) );
		}

		$doctor_id = absint( $post['stmc_doctor_id'] ?? 0 );

		// تاریخ و ساعت انتخابی (اختیاری — برای حالت قدیمی بدون تقویم)
		$appt_date = sanitize_text_field( wp_unslash( $post['stmc_appt_date'] ?? '' ) );
		$appt_time = sanitize_text_field( wp_unslash( $post['stmc_appt_time'] ?? '' ) );

		if ( $appt_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appt_date ) ) {
			return new \WP_Error( 'invalid_date', __( 'تاریخ نامعتبر است.', STMC_TEXT ) );
		}

		if ( $appt_time && ! preg_match( '/^\d{2}:\d{2}$/', $appt_time ) ) {
			return new \WP_Error( 'invalid_time', __( 'ساعت نامعتبر است.', STMC_TEXT ) );
		}

		// اگر تاریخ انتخاب شده اما پزشک مشخص نیست → خطا
		if ( $appt_date && ! $doctor_id ) {
			return new \WP_Error( 'missing_doctor', __( 'برای انتخاب زمان، ابتدا پزشک را انتخاب کنید.', STMC_TEXT ) );
		}

		return [
			'doctor_id'    => $doctor_id,
			'name'         => $name,
			'phone'        => $phone,
			'email'        => $email,
			'specialty'    => sanitize_text_field( wp_unslash( $post['stmc_specialty'] ?? '' ) ),
			'message'      => sanitize_textarea_field( wp_unslash( $post['stmc_message'] ?? '' ) ),
			'appt_date'    => $appt_date ?: null,
			'appt_time'    => $appt_time ?: null,
			'duration_min' => absint( $post['stmc_duration'] ?? get_option( 'stmc_default_slot_minutes', 30 ) ),
			'source'       => 'form',
			'ip'           => $this->get_client_ip(),
		];
	}

	// ─── DB ───────────────────────────────────────────────────────────────────

	private function save_to_db( array $data ): int {
		global $wpdb;

		// وضعیت اولیه: اگر زمان دقیق انتخاب شده → pending (نیاز به تأیید نهایی منشی)
		// این رفتار قابل تغییر است؛ برخی کلینیک‌ها می‌خواهند خودکار confirmed شود
		$auto_confirm = apply_filters( 'stmc_auto_confirm_slot_booking', false );
		$status       = ( $data['appt_date'] && $auto_confirm ) ? 'confirmed' : 'pending';

		$result = $wpdb->insert(
			$wpdb->prefix . 'stmc_appointments',
			[
				'doctor_id'    => $data['doctor_id'],
				'name'         => $data['name'],
				'phone'        => $data['phone'],
				'email'        => $data['email'] ?: null,
				'specialty'    => $data['specialty'] ?: null,
				'message'      => $data['message'] ?: null,
				'appt_date'    => $data['appt_date'],
				'appt_time'    => $data['appt_time'] ? $data['appt_time'] . ':00' : null,
				'duration_min' => $data['duration_min'],
				'status'       => $status,
				'source'       => $data['source'],
				'created_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	// ─── Email ────────────────────────────────────────────────────────────────

	private function send_admin_email( array $data, int $id ): void {
		$admin_email = get_option( 'stmc_appointment_email', get_option( 'admin_email' ) );
		$doctor_name = $data['doctor_id'] ? get_the_title( $data['doctor_id'] ) : __( 'نامشخص', STMC_TEXT );

		$subject = sprintf( __( '[نوبت جدید #%d] %s — %s', STMC_TEXT ), $id, $data['name'], $doctor_name );

		$body  = sprintf( __( 'نوبت جدید ثبت شد (شناسه: %d)', STMC_TEXT ), $id ) . "\n\n";
		$body .= __( 'بیمار:', STMC_TEXT )    . ' ' . $data['name']      . "\n";
		$body .= __( 'تلفن:', STMC_TEXT )     . ' ' . $data['phone']     . "\n";
		$body .= __( 'ایمیل:', STMC_TEXT )    . ' ' . ( $data['email'] ?: '—' ) . "\n";
		$body .= __( 'تخصص:', STMC_TEXT )     . ' ' . ( $data['specialty'] ?: '—' ) . "\n";
		$body .= __( 'پزشک:', STMC_TEXT )     . ' ' . $doctor_name       . "\n";
		if ( $data['appt_date'] ) {
			$body .= __( 'تاریخ نوبت:', STMC_TEXT ) . ' ' . $data['appt_date'] . "\n";
			$body .= __( 'ساعت نوبت:', STMC_TEXT )  . ' ' . $data['appt_time'] . "\n";
		}
		$body .= __( 'پیام:', STMC_TEXT )     . ' ' . ( $data['message'] ?: '—' ) . "\n\n";
		$body .= admin_url( 'admin.php?page=stmc-appointments' );

		wp_mail( $admin_email, $subject, $body );
	}

	private function send_patient_email( array $data ): void {
		if ( empty( $data['email'] ) ) {
			return;
		}

		$subject = __( 'تأیید دریافت درخواست نوبت شما', STMC_TEXT );
		$body    = sprintf( __( 'سلام %s،', STMC_TEXT ), $data['name'] ) . "\n\n";

		if ( $data['appt_date'] ) {
			$body .= sprintf(
				__( 'نوبت شما برای تاریخ %s ساعت %s ثبت شد.', STMC_TEXT ),
				$data['appt_date'],
				$data['appt_time']
			) . "\n\n";
		} else {
			$body .= __( 'درخواست نوبت شما با موفقیت دریافت شد. تیم ما در اسرع وقت با شما تماس خواهد گرفت.', STMC_TEXT ) . "\n\n";
		}

		$body   .= __( 'با تشکر،', STMC_TEXT ) . "\n";
		$body   .= get_option( 'stmc_clinic_name', get_bloginfo( 'name' ) );

		wp_mail( $data['email'], $subject, $body );
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function check_rate_limit(): bool {
		$ip  = $this->get_client_ip();
		$key = 'stmc_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 3 ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	private function get_client_ip(): string {
		$keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return sanitize_text_field( explode( ',', $_SERVER[ $key ] )[0] );
			}
		}
		return '';
	}
}

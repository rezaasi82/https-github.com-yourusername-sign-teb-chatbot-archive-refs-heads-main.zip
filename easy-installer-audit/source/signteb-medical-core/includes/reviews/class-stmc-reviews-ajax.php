<?php
/**
 * SignTeb Medical Core — Review AJAX Handler
 *
 * ثبت نظر عمومی بیمار با محافظت در برابر اسپم:
 * - Honeypot field (فیلد مخفی که فقط بات‌ها پر می‌کنند)
 * - Rate limiting (۲ نظر در ساعت برای هر IP)
 * - وضعیت همیشه pending — نیاز به تأیید دستی
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Reviews;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Ajax {

	private Repository $repo;

	public function __construct( private readonly Loader $loader ) {
		$this->repo = new Repository();

		$this->loader->add_action( 'wp_ajax_stmc_submit_review',        $this, 'handle' );
		$this->loader->add_action( 'wp_ajax_nopriv_stmc_submit_review', $this, 'handle' );
	}

	public function handle(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['stmc_review_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'stmc_review_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'خطای امنیتی. لطفاً صفحه را رفرش کنید.', STMC_TEXT ) ], 403 );
		}

		// Honeypot — اگر این فیلد مخفی پر شده باشد، درخواست از یک بات است
		if ( ! empty( $_POST['stmc_website'] ) ) {
			wp_send_json_error( [ 'message' => __( 'خطا در ارسال.', STMC_TEXT ) ] );
		}

		$data = $this->validate_and_sanitize( $_POST );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( [ 'message' => $data->get_error_message() ] );
		}

		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error( [ 'message' => __( 'تعداد درخواست شما زیاد است. لطفاً بعداً تلاش کنید.', STMC_TEXT ) ] );
		}

		$insert_id = $this->repo->create( $data );
		if ( ! $insert_id ) {
			wp_send_json_error( [ 'message' => __( 'خطا در ثبت نظر. لطفاً مجدداً تلاش کنید.', STMC_TEXT ) ] );
		}

		do_action( 'stmc_review_submitted', $insert_id, $data );

		wp_send_json_success( [
			'message' => __( '✅ با تشکر! نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.', STMC_TEXT ),
			'id'      => $insert_id,
		] );
	}

	private function validate_and_sanitize( array $post ): array|\WP_Error {
		$name    = sanitize_text_field( wp_unslash( $post['stmc_reviewer_name'] ?? '' ) );
		$content = sanitize_textarea_field( wp_unslash( $post['stmc_content'] ?? '' ) );
		$rating  = absint( $post['stmc_rating'] ?? 5 );

		if ( empty( $name ) || mb_strlen( $name ) < 2 ) {
			return new \WP_Error( 'invalid_name', __( 'لطفاً نام خود را وارد کنید.', STMC_TEXT ) );
		}

		if ( empty( $content ) || mb_strlen( $content ) < 10 ) {
			return new \WP_Error( 'invalid_content', __( 'لطفاً نظر خود را با جزئیات بیشتری بنویسید (حداقل ۱۰ حرف).', STMC_TEXT ) );
		}

		if ( mb_strlen( $content ) > 2000 ) {
			return new \WP_Error( 'content_too_long', __( 'متن نظر بیش از حد طولانی است.', STMC_TEXT ) );
		}

		$doctor_id = absint( $post['stmc_doctor_id'] ?? 0 );
		if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) ) {
			return new \WP_Error( 'invalid_doctor', __( 'پزشک نامعتبر است.', STMC_TEXT ) );
		}

		return [
			'doctor_id'     => $doctor_id,
			'reviewer_name' => $name,
			'reviewer_city' => sanitize_text_field( wp_unslash( $post['stmc_reviewer_city'] ?? '' ) ) ?: null,
			'rating'        => max( 1, min( 5, $rating ) ),
			'content'       => $content,
			'treatment'     => sanitize_text_field( wp_unslash( $post['stmc_treatment'] ?? '' ) ) ?: null,
			'source'        => 'public_form',
		];
	}

	private function check_rate_limit(): bool {
		$ip    = $this->get_client_ip();
		$key   = 'stmc_review_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 2 ) {
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

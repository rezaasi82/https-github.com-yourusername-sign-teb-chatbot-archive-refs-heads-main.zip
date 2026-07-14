<?php
/**
 * SignTeb Setup Wizard — Uninstaller (فاز ۶)
 *
 * حذف کاملِ داده‌هایی که ویزارد ساخته است، بدون باقی‌گذاشتن رکورد یتیم — و
 * مهم‌تر: **فقط** چیزی که خودِ ویزارد ساخته (تگ‌شده با _stwiz_demo /
 * _stwiz_media_key) پاک می‌شود، نه محتوای واقعی کاربر.
 *
 * همه‌ی عملیات دفاعی و idempotent است.
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard\Setup;

defined( 'ABSPATH' ) || exit;

final class Uninstaller {

	/** نوع پست‌هایی که دمو ممکن است ساخته باشد. */
	private const POST_TYPES = [ 'doctor', 'medical-service', 'medical-faq', 'post', 'page' ];

	/** آپشن‌های وضعیتِ خودِ ویزارد (همیشه حذف می‌شوند). */
	private const WIZARD_OPTIONS = [
		'stwiz_setup_state', 'stwiz_completed_steps', 'stwiz_demo_installed',
		'stwiz_setup_complete', 'stwiz_selected_demo',
		'stwiz_step_welcome', 'stwiz_step_brand', 'stwiz_step_clinic',
		'stwiz_step_contact', 'stwiz_step_demo',
	];

	/** آپشن‌های stmc که توسط apply_options دمو ست شده‌اند (نه آپشن‌های هسته‌ی وردپرس). */
	private const DEMO_STMC_OPTIONS = [
		'stmc_clinic_name', 'stmc_clinic_phone', 'stmc_clinic_whatsapp',
		'stmc_clinic_email', 'stmc_clinic_address', 'stmc_market',
		'stmc_geo_placename', 'stmc_geo_region', 'stmc_country_code',
		'stmc_brand_primary', 'stmc_primary_language', 'stmc_appointment_email',
		'stmc_social_instagram', 'stmc_social_linkedin', 'stmc_social_youtube',
	];

	/**
	 * اجرای حذف. آرایه‌ی شمارش نتایج را برمی‌گرداند.
	 *
	 * @param array $opts فعلاً همه‌ی بخش‌ها اجرا می‌شوند؛ برای توسعه‌ی آینده.
	 */
	public function run( array $opts = [] ): array {
		$counts = [
			'posts'       => 0,
			'media'       => 0,
			'reviews'     => 0,
			'menus'       => 0,
			'options'     => 0,
			'transients'  => 0,
		];

		// ترتیب مهم است: قبل از حذف پست‌های پزشک، نظرات آن‌ها را پاک کن.
		$doctor_ids = $this->demo_post_ids( [ 'doctor' ] );
		$counts['reviews'] = $this->remove_reviews( $doctor_ids );
		$counts['posts']   = $this->remove_posts();
		$counts['media']   = $this->remove_media();
		$counts['menus']   = $this->remove_menus();
		$counts['options'] = $this->reset_options();
		$this->reset_reading();
		$counts['transients'] = $this->clear_transients();

		return $counts;
	}

	// ─── Posts ──────────────────────────────────────────────────────────────────

	/** @param string[] $types @return int[] */
	private function demo_post_ids( array $types ): array {
		return get_posts( [
			'post_type'      => $types,
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'meta_key'       => '_stwiz_demo',
			'meta_compare'   => 'EXISTS',
		] );
	}

	private function remove_posts(): int {
		$ids   = $this->demo_post_ids( self::POST_TYPES );
		$count = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) { // force delete (bypass trash)
				$count++;
			}
		}
		return $count;
	}

	// ─── Media ──────────────────────────────────────────────────────────────────

	private function remove_media(): int {
		$ids = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'meta_key'       => '_stwiz_media_key',
			'meta_compare'   => 'EXISTS',
		] );
		$count = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_attachment( (int) $id, true ) ) { // حذف فایل از دیسک هم
				$count++;
			}
		}
		return $count;
	}

	// ─── Reviews (جدول سفارشی Medical Core) ──────────────────────────────────────

	/** @param int[] $doctor_ids */
	private function remove_reviews( array $doctor_ids ): int {
		if ( empty( $doctor_ids ) || ! class_exists( '\STMC\Reviews\Repository' ) ) {
			return 0;
		}
		$count = 0;
		try {
			$repo = new \STMC\Reviews\Repository();
			foreach ( $doctor_ids as $did ) {
				foreach ( $repo->get_approved( (int) $did, 9999 ) as $rev ) {
					if ( isset( $rev->id ) && $repo->delete( (int) $rev->id ) ) {
						$count++;
					}
				}
			}
		} catch ( \Throwable $e ) {
			return $count;
		}
		return $count;
	}

	// ─── Menus ──────────────────────────────────────────────────────────────────

	private function remove_menus(): int {
		$count = 0;
		$menu  = wp_get_nav_menu_object( __( 'منوی اصلی', STWIZ_TEXT ) );
		if ( $menu && ! is_wp_error( $menu ) ) {
			if ( wp_delete_nav_menu( $menu->term_id ) ) {
				$count++;
			}
		}
		// پاک‌کردن اختصاص جایگاه منو.
		$locations = get_theme_mod( 'nav_menu_locations', [] );
		if ( isset( $locations['primary'] ) ) {
			unset( $locations['primary'] );
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		return $count;
	}

	// ─── Options ──────────────────────────────────────────────────────────────────

	private function reset_options(): int {
		$count = 0;
		foreach ( array_merge( self::WIZARD_OPTIONS, self::DEMO_STMC_OPTIONS ) as $opt ) {
			if ( false !== get_option( $opt, false ) ) {
				delete_option( $opt );
				$count++;
			}
		}
		return $count;
	}

	/** بازگرداندن تنظیمات صفحه‌ی اصلی/بلاگ (چون صفحات مربوطه حذف شده‌اند). */
	private function reset_reading(): void {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );
	}

	// ─── Transients ───────────────────────────────────────────────────────────────

	private function clear_transients(): int {
		global $wpdb;
		$count = 0;
		if ( isset( $wpdb ) ) {
			$like = $wpdb->esc_like( '_transient_stwiz_' ) . '%';
			$rows = $wpdb->get_col(
				$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
			);
			foreach ( (array) $rows as $name ) {
				$key = preg_replace( '/^_transient_/', '', (string) $name );
				if ( $key && delete_transient( $key ) ) {
					$count++;
				}
			}
		}
		// transientهای شناخته‌شده‌ی مستقیم.
		delete_transient( 'stwiz_redirect' );
		return $count;
	}
}

<?php
/**
 * SignTeb Medical Core — Activator
 *
 * اجرا می‌شود وقتی پلاگین activate می‌شود.
 * - ساخت جداول سفارشی
 * - ذخیره DB version
 * - flush rewrite rules
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		self::create_tables();
		self::set_defaults();

		// Flush rewrite rules پس از ثبت CPT‌ها
		// NOTE: CPT‌ها باید قبل از flush ثبت شده باشند
		// به همین دلیل register_activation_hook قبل از plugins_loaded اجرا می‌شود
		flush_rewrite_rules();

		update_option( 'stmc_db_version', STMC_DB_VERSION );
		update_option( 'stmc_activated_at', current_time( 'mysql' ) );
	}

	// ─── Database Tables ──────────────────────────────────────────────────────

	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = [];

		// جدول نوبت‌های رزرو شده
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_appointments (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name          VARCHAR(100)    NOT NULL DEFAULT '',
			phone         VARCHAR(25)     NOT NULL DEFAULT '',
			email         VARCHAR(150)             DEFAULT NULL,
			specialty     VARCHAR(100)             DEFAULT NULL,
			message       TEXT,
			appt_date     DATE                     DEFAULT NULL,
			appt_time     TIME                     DEFAULT NULL,
			duration_min  SMALLINT UNSIGNED        DEFAULT 30,
			status        ENUM('pending','confirmed','cancelled','done','no_show') NOT NULL DEFAULT 'pending',
			source        VARCHAR(50)              DEFAULT 'form',
			admin_note    TEXT,
			reminder_24h_sent TINYINT(1)   NOT NULL DEFAULT 0,
			reminder_2h_sent  TINYINT(1)   NOT NULL DEFAULT 0,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_doctor  (doctor_id),
			KEY idx_status  (status),
			KEY idx_created (created_at),
			KEY idx_appt_datetime (doctor_id, appt_date, appt_time),
			KEY idx_reminders (appt_date, reminder_24h_sent, reminder_2h_sent)
		) $charset;";

		// جدول نظرات بیماران (جدا از WP Comments)
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_reviews (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id     BIGINT UNSIGNED NOT NULL,
			reviewer_name VARCHAR(100)    NOT NULL DEFAULT '',
			reviewer_city VARCHAR(100)             DEFAULT NULL,
			rating        TINYINT         NOT NULL DEFAULT 5,
			content       TEXT,
			treatment     VARCHAR(100)             DEFAULT NULL,
			verified      TINYINT(1)               DEFAULT 0,
			status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_doctor (doctor_id),
			KEY idx_status (status)
		) $charset;";

		// جدول Topic Cluster mapping
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_topic_clusters (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			pillar_id  BIGINT UNSIGNED NOT NULL,
			cluster_id BIGINT UNSIGNED NOT NULL,
			weight     TINYINT         NOT NULL DEFAULT 5,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uk_pair (pillar_id, cluster_id),
			KEY idx_pillar (pillar_id)
		) $charset;";

		// جدول cache سکو برای اجتناب از محاسبه مجدد
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_seo_scores (
			post_id    BIGINT UNSIGNED NOT NULL,
			score      TINYINT         NOT NULL DEFAULT 0,
			issues     TEXT,
			computed   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (post_id)
		) $charset;";

		// جدول availability پزشک — روزهای هفته + بازه ساعتی + مدت هر نوبت
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_doctor_availability (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id     BIGINT UNSIGNED NOT NULL,
			weekday       TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday ... 6=Saturday',
			start_time    TIME            NOT NULL DEFAULT '09:00:00',
			end_time      TIME            NOT NULL DEFAULT '17:00:00',
			slot_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
			is_active     TINYINT(1)      NOT NULL DEFAULT 1,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_doctor_day (doctor_id, weekday)
		) $charset;";

		// جدول استثناها — روزهای تعطیل/مرخصی یا بازکردن یک روز خاص با ساعت خاص
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_doctor_exceptions (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id     BIGINT UNSIGNED NOT NULL,
			exception_date DATE          NOT NULL,
			type          ENUM('closed','custom') NOT NULL DEFAULT 'closed',
			start_time    TIME                     DEFAULT NULL,
			end_time      TIME                     DEFAULT NULL,
			slot_minutes  SMALLINT UNSIGNED        DEFAULT NULL,
			note          VARCHAR(255)             DEFAULT NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_doctor_date (doctor_id, exception_date)
		) $charset;";

		// جدول لاگ SMS — برای جلوگیری از ارسال تکراری و پیگیری وضعیت تحویل
		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stmc_sms_log (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			appointment_id BIGINT UNSIGNED NOT NULL,
			phone         VARCHAR(20)     NOT NULL,
			type          ENUM('confirmation','reminder_24h','reminder_2h','status_change','cancellation') NOT NULL,
			message       TEXT,
			provider_response TEXT,
			status        ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
			sent_at       DATETIME                 DEFAULT NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_appt (appointment_id),
			KEY idx_status_type (status, type)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	// ─── Default Options ──────────────────────────────────────────────────────

	private static function set_defaults(): void {
		$defaults = [
			'stmc_clinic_name'       => get_bloginfo( 'name' ),
			'stmc_clinic_phone'      => '',
			'stmc_clinic_whatsapp'   => '',
			'stmc_clinic_email'      => get_option( 'admin_email' ),
			'stmc_clinic_address'    => '',
			'stmc_schema_enabled'    => '1',
			'stmc_internal_links'    => '1',
			'stmc_appointment_email' => get_option( 'admin_email' ),
			// ── SMS / ملی‌پیامک ──
			'stmc_sms_enabled'           => '0',
			'stmc_sms_username'          => '',
			'stmc_sms_password'          => '',
			'stmc_sms_sender_line'       => '',
			'stmc_sms_confirmation_enabled' => '1',
			'stmc_sms_reminder_24h_enabled' => '1',
			'stmc_sms_reminder_2h_enabled'  => '1',
			// ── Booking ──
			'stmc_default_slot_minutes' => '30',
			'stmc_booking_lead_hours'   => '2',   // حداقل فاصله تا نوبت
			'stmc_booking_max_days'     => '30',  // حداکثر روزهای آینده قابل رزرو
		];

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				update_option( $key, $value );
			}
		}

		// زمان‌بند یادآوری SMS (هر ۱۵ دقیقه)
		if ( ! wp_next_scheduled( 'stmc_sms_reminder_cron' ) ) {
			wp_schedule_event( time(), 'stmc_fifteen_minutes', 'stmc_sms_reminder_cron' );
		}
	}
}

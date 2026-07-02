<?php
/**
 * SignTeb Medical Core — Reviews Repository
 *
 * یک نقطه واحد دسترسی به جدول stmc_reviews.
 * هم فرم عمومی بیمار و هم پنل دستی منشی از همین کلاس استفاده می‌کنند
 * تا منطق ذخیره، اعتبارسنجی، و خوانش در یک جا متمرکز باشد.
 *
 * این کلاس جایگزین کامل CPT «testimonial» است — سبک‌تر، بدون overhead
 * پست/متا/revision وردپرس، و با ایندکس‌گذاری مستقیم روی doctor_id و status.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Reviews;

defined( 'ABSPATH' ) || exit;

final class Repository {

	private const TABLE = 'stmc_reviews';

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	// ─── Create ───────────────────────────────────────────────────────────────

	/**
	 * ثبت نظر جدید — همیشه با وضعیت pending (نیاز به تأیید منشی/پزشک)
	 *
	 * @param array{doctor_id:int, reviewer_name:string, reviewer_city?:string,
	 *              rating:int, content:string, treatment?:string, source?:string} $data
	 */
	public function create( array $data ): int {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			[
				'doctor_id'     => $data['doctor_id'],
				'reviewer_name' => $data['reviewer_name'],
				'reviewer_city' => $data['reviewer_city'] ?? null,
				'rating'        => max( 1, min( 5, (int) $data['rating'] ) ),
				'content'       => $data['content'] ?? '',
				'treatment'     => $data['treatment'] ?? null,
				'verified'      => ! empty( $data['verified'] ) ? 1 : 0,
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	// ─── Read ─────────────────────────────────────────────────────────────────

	public function get( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table()} WHERE id = %d", $id
		) );
		return $row ?: null;
	}

	/**
	 * نظرات تأیید شده برای نمایش عمومی (مثلاً بلوک Testimonials)
	 *
	 * @return object[]
	 */
	public function get_approved( int $doctor_id = 0, int $limit = 20 ): array {
		global $wpdb;

		if ( $doctor_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$this->table()}
				 WHERE doctor_id = %d AND status = 'approved'
				 ORDER BY created_at DESC LIMIT %d",
				$doctor_id, $limit
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table()}
			 WHERE status = 'approved'
			 ORDER BY created_at DESC LIMIT %d",
			$limit
		) );
	}

	/**
	 * برای پنل مدیریت در پیشخوان — با فیلتر وضعیت
	 *
	 * @return array{rows: object[], total: int}
	 */
	public function get_for_admin( string $status = 'all', int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$where  = 'all' !== $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
		$offset = max( 0, ( $page - 1 ) * $per_page );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()} {$where}" );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table()} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page, $offset
		) );

		return [ 'rows' => $rows, 'total' => $total ];
	}

	public function count_pending(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table()} WHERE status = 'pending'"
		);
	}

	/**
	 * میانگین امتیاز و تعداد نظرات تأیید شده یک پزشک — برای AggregateRating در Schema
	 */
	public function get_doctor_stats( int $doctor_id ): array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG(rating) as avg_rating, COUNT(*) as total
			 FROM {$this->table()}
			 WHERE doctor_id = %d AND status = 'approved'",
			$doctor_id
		) );

		return [
			'avg_rating' => $row ? round( (float) $row->avg_rating, 1 ) : 0.0,
			'total'      => $row ? (int) $row->total : 0,
		];
	}

	// ─── Update ───────────────────────────────────────────────────────────────

	public function update_status( int $id, string $status ): bool {
		global $wpdb;

		$allowed = [ 'pending', 'approved', 'rejected' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table(),
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * ثبت دستی توسط منشی/پزشک — می‌تواند مستقیماً approved باشد
	 */
	public function create_manual( array $data, bool $auto_approve = false ): int {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			[
				'doctor_id'     => $data['doctor_id'],
				'reviewer_name' => $data['reviewer_name'],
				'reviewer_city' => $data['reviewer_city'] ?? null,
				'rating'        => max( 1, min( 5, (int) $data['rating'] ) ),
				'content'       => $data['content'] ?? '',
				'treatment'     => $data['treatment'] ?? null,
				'verified'      => 1, // ثبت دستی همیشه verified است
				'status'        => $auto_approve ? 'approved' : 'pending',
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	// ─── Delete ───────────────────────────────────────────────────────────────

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
	}
}

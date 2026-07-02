<?php
/**
 * SignTeb Medical Core — Admin Availability Calendar
 *
 * مدیریت تقویم کاری پزشکان:
 * - تنظیم روزهای هفته + بازه ساعتی + مدت هر نوبت
 * - استثناها: تعطیلی یک روز خاص یا تغییر ساعت آن روز
 *
 * @package SignTeb_Medical_Core
 */
declare( strict_types=1 );
namespace STMC\Admin;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Availability {

	private const WEEKDAYS = [
		0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه',
		4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه',
	];

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'admin_post_stmc_save_availability', $this, 'handle_save_availability' );
		$this->loader->add_action( 'admin_post_stmc_save_exception',    $this, 'handle_save_exception' );
		$this->loader->add_action( 'admin_post_stmc_delete_exception',  $this, 'handle_delete_exception' );
	}

	public static function render_page(): void {
		global $wpdb;

		$doctors = get_posts( [
			'post_type'      => 'doctor',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$selected_doctor = absint( $_GET['doctor_id'] ?? ( $doctors[0]->ID ?? 0 ) );

		?>
		<div class="wrap">
			<h1>🗓️ <?php esc_html_e( 'مدیریت تقویم کاری پزشکان', STMC_TEXT ); ?></h1>

			<?php if ( empty( $doctors ) ) : ?>
				<p><?php esc_html_e( 'ابتدا یک پروفایل پزشک ایجاد کنید.', STMC_TEXT ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=doctor' ) ); ?>" class="button button-primary"><?php esc_html_e( 'افزودن پزشک', STMC_TEXT ); ?></a>
				<?php return; ?>
			<?php endif; ?>

			<!-- Doctor selector -->
			<form method="get" style="margin-bottom:20px;">
				<input type="hidden" name="page" value="stmc-availability">
				<select name="doctor_id" onchange="this.form.submit()" style="font-size:14px;padding:8px 12px;">
					<?php foreach ( $doctors as $doc ) : ?>
						<option value="<?php echo esc_attr( $doc->ID ); ?>" <?php selected( $selected_doctor, $doc->ID ); ?>>
							<?php echo esc_html( $doc->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( '✅ تقویم بروزرسانی شد.', STMC_TEXT ); ?></p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

				<!-- ── ساعات کاری هفتگی ── -->
				<div class="postbox" style="padding:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'ساعات کاری هفتگی', STMC_TEXT ); ?></h2>
					<p class="description"><?php esc_html_e( 'برای هر روز هفته، بازه ساعتی و مدت هر نوبت را تعیین کنید. روزهایی که تیک نخورده‌اند به عنوان «تعطیل ثابت» در نظر گرفته می‌شوند.', STMC_TEXT ); ?></p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'stmc_save_availability_' . $selected_doctor ); ?>
						<input type="hidden" name="action" value="stmc_save_availability">
						<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $selected_doctor ); ?>">

						<table class="wp-list-table widefat" style="margin-top:12px;">
							<thead>
								<tr>
									<th style="width:40px;"></th>
									<th><?php esc_html_e( 'روز', STMC_TEXT ); ?></th>
									<th><?php esc_html_e( 'ساعت شروع', STMC_TEXT ); ?></th>
									<th><?php esc_html_e( 'ساعت پایان', STMC_TEXT ); ?></th>
									<th><?php esc_html_e( 'مدت هر نوبت (دقیقه)', STMC_TEXT ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( self::WEEKDAYS as $wd => $label ) :
									$row = $wpdb->get_row( $wpdb->prepare(
										"SELECT * FROM {$wpdb->prefix}stmc_doctor_availability WHERE doctor_id=%d AND weekday=%d",
										$selected_doctor, $wd
									) );
									$is_active = $row && $row->is_active;
								?>
								<tr>
									<td><input type="checkbox" name="active[<?php echo $wd; ?>]" value="1" <?php checked( $is_active ); ?>></td>
									<td><strong><?php echo esc_html( $label ); ?></strong></td>
									<td><input type="time" name="start[<?php echo $wd; ?>]" value="<?php echo esc_attr( $row ? substr( $row->start_time, 0, 5 ) : '09:00' ); ?>"></td>
									<td><input type="time" name="end[<?php echo $wd; ?>]" value="<?php echo esc_attr( $row ? substr( $row->end_time, 0, 5 ) : '17:00' ); ?>"></td>
									<td>
										<select name="slot[<?php echo $wd; ?>]">
											<?php foreach ( [ 15, 20, 30, 45, 60 ] as $min ) : ?>
												<option value="<?php echo $min; ?>" <?php selected( $row ? (int) $row->slot_minutes : 30, $min ); ?>><?php echo $min; ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( '💾 ذخیره ساعات کاری', STMC_TEXT ); ?></button></p>
					</form>
				</div>

				<!-- ── استثناها ── -->
				<div class="postbox" style="padding:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'استثناها (تعطیلی / مرخصی)', STMC_TEXT ); ?></h2>
					<p class="description"><?php esc_html_e( 'برای یک روز خاص، تعطیلی اعلام کنید یا ساعت متفاوتی تنظیم کنید — مثلاً مرخصی یا روز کاری ویژه.', STMC_TEXT ); ?></p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:16px;">
						<?php wp_nonce_field( 'stmc_save_exception_' . $selected_doctor ); ?>
						<input type="hidden" name="action" value="stmc_save_exception">
						<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $selected_doctor ); ?>">

						<p><label><strong><?php esc_html_e( 'تاریخ', STMC_TEXT ); ?></strong><br>
							<input type="date" name="exception_date" required style="width:100%;"></label></p>

						<p>
							<label><input type="radio" name="type" value="closed" checked> <?php esc_html_e( 'تعطیل کامل', STMC_TEXT ); ?></label>
							&nbsp;&nbsp;
							<label><input type="radio" name="type" value="custom"> <?php esc_html_e( 'ساعت خاص', STMC_TEXT ); ?></label>
						</p>

						<div id="stmc-custom-time-fields" style="display:none;">
							<p><label><?php esc_html_e( 'از ساعت', STMC_TEXT ); ?> <input type="time" name="start_time" value="09:00"></label></p>
							<p><label><?php esc_html_e( 'تا ساعت', STMC_TEXT ); ?> <input type="time" name="end_time" value="13:00"></label></p>
						</div>

						<p><label><?php esc_html_e( 'یادداشت (اختیاری)', STMC_TEXT ); ?><br>
							<input type="text" name="note" placeholder="<?php esc_attr_e( 'مثال: مرخصی استحقاقی', STMC_TEXT ); ?>" style="width:100%;"></label></p>

						<button type="submit" class="button button-secondary"><?php esc_html_e( 'افزودن استثنا', STMC_TEXT ); ?></button>
					</form>

					<!-- لیست استثناهای آینده -->
					<h4><?php esc_html_e( 'استثناهای ثبت‌شده', STMC_TEXT ); ?></h4>
					<?php
					$exceptions = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}stmc_doctor_exceptions
						 WHERE doctor_id=%d AND exception_date >= %s ORDER BY exception_date ASC",
						$selected_doctor, wp_date( 'Y-m-d' )
					) );

					if ( empty( $exceptions ) ) :
						echo '<p style="color:#999;font-size:13px;">' . esc_html__( 'استثنایی ثبت نشده است.', STMC_TEXT ) . '</p>';
					else :
						foreach ( $exceptions as $exc ) :
							$label = 'closed' === $exc->type
								? __( 'تعطیل', STMC_TEXT )
								: sprintf( '%s - %s', substr( $exc->start_time, 0, 5 ), substr( $exc->end_time, 0, 5 ) );
							?>
							<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:6px;font-size:13px;">
								<span>
									<strong><?php echo esc_html( wp_date( 'Y/m/d', strtotime( $exc->exception_date ) ) ); ?></strong>
									— <?php echo esc_html( $label ); ?>
									<?php if ( $exc->note ) : ?><br><small style="color:#888;"><?php echo esc_html( $exc->note ); ?></small><?php endif; ?>
								</span>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'stmc_delete_exception_' . $exc->id ); ?>
									<input type="hidden" name="action" value="stmc_delete_exception">
									<input type="hidden" name="exception_id" value="<?php echo esc_attr( $exc->id ); ?>">
									<button type="submit" class="button button-small" style="color:#dc2626;">✕</button>
								</form>
							</div>
						<?php endforeach;
					endif;
					?>
				</div>

			</div>
		</div>

		<script>
		document.querySelectorAll('input[name="type"]').forEach(function(radio) {
			radio.addEventListener('change', function() {
				document.getElementById('stmc-custom-time-fields').style.display =
					document.querySelector('input[name="type"]:checked').value === 'custom' ? 'block' : 'none';
			});
		});
		</script>
		<?php
	}

	// ─── Save weekly availability ────────────────────────────────────────────

	public function handle_save_availability(): void {
		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );

		if ( ! $doctor_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_save_availability_' . $doctor_id ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'stmc_doctor_availability';

		for ( $wd = 0; $wd <= 6; $wd++ ) {
			$is_active = ! empty( $_POST['active'][ $wd ] ) ? 1 : 0;
			$start     = sanitize_text_field( $_POST['start'][ $wd ] ?? '09:00' );
			$end       = sanitize_text_field( $_POST['end'][ $wd ]   ?? '17:00' );
			$slot      = absint( $_POST['slot'][ $wd ] ?? 30 );

			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE doctor_id=%d AND weekday=%d",
				$doctor_id, $wd
			) );

			$data = [
				'doctor_id'    => $doctor_id,
				'weekday'      => $wd,
				'start_time'   => $start . ':00',
				'end_time'     => $end . ':00',
				'slot_minutes' => $slot,
				'is_active'    => $is_active,
			];

			if ( $existing ) {
				$wpdb->update( $table, $data, [ 'id' => $existing ] );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $table, $data );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=stmc-availability&doctor_id=' . $doctor_id . '&saved=1' ) );
		exit;
	}

	// ─── Save exception ───────────────────────────────────────────────────────

	public function handle_save_exception(): void {
		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );

		if ( ! $doctor_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_save_exception_' . $doctor_id ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		global $wpdb;

		$date = sanitize_text_field( $_POST['exception_date'] ?? '' );
		$type = sanitize_key( $_POST['type'] ?? 'closed' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_die( esc_html__( 'تاریخ نامعتبر است.', STMC_TEXT ) );
		}

		$wpdb->replace(
			$wpdb->prefix . 'stmc_doctor_exceptions',
			[
				'doctor_id'      => $doctor_id,
				'exception_date' => $date,
				'type'           => $type,
				'start_time'     => 'custom' === $type ? sanitize_text_field( $_POST['start_time'] ?? '09:00' ) . ':00' : null,
				'end_time'       => 'custom' === $type ? sanitize_text_field( $_POST['end_time']   ?? '17:00' ) . ':00' : null,
				'note'           => sanitize_text_field( $_POST['note'] ?? '' ),
				'created_at'     => current_time( 'mysql' ),
			]
		);

		wp_redirect( admin_url( 'admin.php?page=stmc-availability&doctor_id=' . $doctor_id . '&saved=1' ) );
		exit;
	}

	// ─── Delete exception ─────────────────────────────────────────────────────

	public function handle_delete_exception(): void {
		$exception_id = absint( $_POST['exception_id'] ?? 0 );

		if ( ! $exception_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_delete_exception_' . $exception_id ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		global $wpdb;

		$doctor_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT doctor_id FROM {$wpdb->prefix}stmc_doctor_exceptions WHERE id=%d",
			$exception_id
		) );

		$wpdb->delete( $wpdb->prefix . 'stmc_doctor_exceptions', [ 'id' => $exception_id ], [ '%d' ] );

		wp_redirect( admin_url( 'admin.php?page=stmc-availability&doctor_id=' . $doctor_id . '&saved=1' ) );
		exit;
	}
}

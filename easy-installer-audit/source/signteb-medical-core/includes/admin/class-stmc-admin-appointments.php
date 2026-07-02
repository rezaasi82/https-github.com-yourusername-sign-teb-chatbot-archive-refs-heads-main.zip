<?php
/**
 * SignTeb Medical Core — Admin Appointments
 */
declare( strict_types=1 );
namespace STMC\Admin;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Appointments {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'admin_post_stmc_update_appointment', $this, 'handle_status_update' );
	}

	public static function render_page(): void {
		global $wpdb;

		$status_filter = sanitize_key( $_GET['status'] ?? 'all' );
		$page_num      = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page      = 20;
		$offset        = ( $page_num - 1 ) * $per_page;

		$where = 'all' !== $status_filter ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}stmc_appointments {$where}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}stmc_appointments {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$status_labels = [
			'pending'   => __( 'در انتظار', STMC_TEXT ),
			'confirmed' => __( 'تأیید شده', STMC_TEXT ),
			'done'      => __( 'انجام شده', STMC_TEXT ),
			'no_show'   => __( 'عدم حضور', STMC_TEXT ),
			'cancelled' => __( 'لغو شده', STMC_TEXT ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '📅 مدیریت نوبت‌ها', STMC_TEXT ); ?></h1>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-availability' ) ); ?>" class="button">
					🗓️ <?php esc_html_e( 'مدیریت تقویم پزشکان', STMC_TEXT ); ?>
				</a>
			</p>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-appointments' ) ); ?>" <?php if ( 'all' === $status_filter ) echo 'class="current"'; ?>><?php esc_html_e( 'همه', STMC_TEXT ); ?></a> |</li>
				<?php foreach ( $status_labels as $st => $label ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-appointments&status=' . $st ) ); ?>" <?php if ( $status_filter === $st ) echo 'class="current"'; ?>>
						<?php echo esc_html( $label ); ?>
					</a> |
				</li>
				<?php endforeach; ?>
			</ul>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'شناسه', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'بیمار', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'تلفن', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'پزشک', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'تاریخ / ساعت نوبت', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'وضعیت', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'SMS', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'عملیات', STMC_TEXT ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8" style="text-align:center;padding:20px;"><?php esc_html_e( 'نوبتی یافت نشد.', STMC_TEXT ); ?></td></tr>
					<?php else : ?>
					<?php foreach ( $rows as $row ) :
						$sms_count = (int) $wpdb->get_var( $wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}stmc_sms_log WHERE appointment_id=%d AND status='sent'",
							$row->id
						) );
					?>
					<tr>
						<td><strong>#<?php echo esc_html( $row->id ); ?></strong></td>
						<td>
							<?php echo esc_html( $row->name ); ?>
							<?php if ( $row->message ) : ?>
								<br><small style="color:#666;"><?php echo esc_html( mb_substr( $row->message, 0, 40 ) ); ?>...</small>
							<?php endif; ?>
						</td>
						<td><a href="tel:<?php echo esc_attr( $row->phone ); ?>"><?php echo esc_html( $row->phone ); ?></a></td>
						<td><?php echo $row->doctor_id ? esc_html( get_the_title( $row->doctor_id ) ) : '—'; ?></td>
						<td>
							<?php if ( $row->appt_date ) : ?>
								<strong><?php echo esc_html( wp_date( 'Y/m/d', strtotime( $row->appt_date ) ) ); ?></strong>
								<?php if ( $row->appt_time ) : ?>
									<br><span style="color:#1a56db;font-weight:700;"><?php echo esc_html( substr( $row->appt_time, 0, 5 ) ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span style="color:#999;"><?php esc_html_e( 'بدون زمان مشخص', STMC_TEXT ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<span class="stmc-appt-status stmc-appt-status--<?php echo esc_attr( $row->status ); ?>">
								<?php echo esc_html( $status_labels[ $row->status ] ?? $row->status ); ?>
							</span>
						</td>
						<td>
							<?php if ( $sms_count > 0 ) : ?>
								<span title="<?php echo esc_attr( $sms_count . ' پیامک ارسال شده' ); ?>">📱 <?php echo esc_html( $sms_count ); ?></span>
							<?php else : ?>
								<span style="color:#ccc;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'stmc_appt_status_' . $row->id ); ?>
								<input type="hidden" name="action" value="stmc_update_appointment">
								<input type="hidden" name="appt_id" value="<?php echo absint( $row->id ); ?>">
								<select name="new_status" style="margin-left:4px;">
									<?php foreach ( $status_labels as $st => $label ) : ?>
										<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $row->status, $st ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-small"><?php esc_html_e( 'ذخیره', STMC_TEXT ); ?></button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<style>
			.stmc-appt-status { padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700; }
			.stmc-appt-status--pending   { background:#fef3c7; color:#92400e; }
			.stmc-appt-status--confirmed { background:#d1fae5; color:#065f46; }
			.stmc-appt-status--done      { background:#e0e7ff; color:#3730a3; }
			.stmc-appt-status--no_show   { background:#fed7aa; color:#9a3412; }
			.stmc-appt-status--cancelled { background:#fee2e2; color:#991b1b; }
			</style>
		</div>
		<?php
	}

	public function handle_status_update(): void {
		$appt_id = absint( $_POST['appt_id'] ?? 0 );
		if ( ! $appt_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_appt_status_' . $appt_id ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		global $wpdb;
		$new_status = sanitize_key( $_POST['new_status'] ?? 'pending' );
		$allowed    = [ 'pending', 'confirmed', 'done', 'cancelled', 'no_show' ];

		if ( in_array( $new_status, $allowed, true ) ) {
			// خواندن ردیف کامل قبل از آپدیت — برای پاس دادن به SMS hook
			$appt_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}stmc_appointments WHERE id=%d",
				$appt_id
			), ARRAY_A );

			$wpdb->update(
				$wpdb->prefix . 'stmc_appointments',
				[ 'status' => $new_status ],
				[ 'id'     => $appt_id ],
				[ '%s' ],
				[ '%d' ]
			);

			if ( $appt_row ) {
				/**
				 * @param int    $appt_id
				 * @param string $new_status
				 * @param array  $appt_row داده کامل نوبت (شامل phone, doctor_id, appt_date, appt_time)
				 */
				do_action( 'stmc_appointment_status_changed', $appt_id, $new_status, $appt_row );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=stmc-appointments&updated=1' ) );
		exit;
	}
}

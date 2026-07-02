<?php
/**
 * SignTeb Medical Core — Admin Reviews
 *
 * مدیریت نظرات بیماران در پیشخوان:
 * - لیست نظرات با فیلتر وضعیت (در انتظار / تأیید شده / رد شده)
 * - تأیید / رد با یک کلیک
 * - فرم ثبت دستی توسط منشی (برای نظراتی که حضوری یا تلفنی گرفته شده)
 *
 * جایگزین کامل CPT قدیمی «testimonial» — هر دو مسیر ورودی
 * (فرم عمومی بیمار + ثبت دستی منشی) از همین جدول stmc_reviews می‌خوانند.
 *
 * @package SignTeb_Medical_Core
 */
declare( strict_types=1 );
namespace STMC\Admin;
use STMC\Loader;
use STMC\Reviews\Repository;
defined( 'ABSPATH' ) || exit;

final class Reviews {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'admin_post_stmc_update_review', $this, 'handle_status_update' );
		$this->loader->add_action( 'admin_post_stmc_add_review_manual', $this, 'handle_manual_add' );
		$this->loader->add_action( 'admin_post_stmc_delete_review', $this, 'handle_delete' );
	}

	public static function render_page(): void {
		$repo = new Repository();

		$status_filter = sanitize_key( $_GET['status'] ?? 'pending' ); // پیش‌فرض: نشان‌دادن نظرات منتظر تأیید
		$page_num      = max( 1, absint( $_GET['paged'] ?? 1 ) );

		$result = $repo->get_for_admin( $status_filter, $page_num, 20 );
		$rows   = $result['rows'];
		$total  = $result['total'];
		$pending_count = $repo->count_pending();

		$status_labels = [
			'pending'  => __( 'در انتظار تأیید', STMC_TEXT ),
			'approved' => __( 'تأیید شده', STMC_TEXT ),
			'rejected' => __( 'رد شده', STMC_TEXT ),
		];

		$doctors = get_posts( [
			'post_type'      => 'doctor',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		?>
		<div class="wrap">
			<h1>
				⭐ <?php esc_html_e( 'نظرات بیماران', STMC_TEXT ); ?>
				<?php if ( $pending_count > 0 ) : ?>
					<span class="stmc-pending-badge"><?php echo esc_html( $pending_count ); ?></span>
				<?php endif; ?>
			</h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '✅ با موفقیت ذخیره شد.', STMC_TEXT ); ?></p></div>
			<?php endif; ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-reviews' ) ); ?>" <?php if ( 'all' === $status_filter ) echo 'class="current"'; ?>><?php esc_html_e( 'همه', STMC_TEXT ); ?></a> |</li>
				<?php foreach ( $status_labels as $st => $label ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-reviews&status=' . $st ) ); ?>" <?php if ( $status_filter === $st ) echo 'class="current"'; ?>>
						<?php echo esc_html( $label ); ?>
					</a> |
				</li>
				<?php endforeach; ?>
			</ul>

			<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;margin-top:12px;">

				<!-- ── لیست نظرات ── -->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:60px;"><?php esc_html_e( 'امتیاز', STMC_TEXT ); ?></th>
							<th><?php esc_html_e( 'بیمار', STMC_TEXT ); ?></th>
							<th><?php esc_html_e( 'پزشک', STMC_TEXT ); ?></th>
							<th><?php esc_html_e( 'متن نظر', STMC_TEXT ); ?></th>
							<th style="width:90px;"><?php esc_html_e( 'منبع', STMC_TEXT ); ?></th>
							<th style="width:110px;"><?php esc_html_e( 'وضعیت', STMC_TEXT ); ?></th>
							<th style="width:160px;"><?php esc_html_e( 'عملیات', STMC_TEXT ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7" style="text-align:center;padding:24px;"><?php esc_html_e( 'نظری یافت نشد.', STMC_TEXT ); ?></td></tr>
						<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<span class="stmc-stars-mini" title="<?php echo esc_attr( $row->rating . '/5' ); ?>">
									<?php echo esc_html( str_repeat( '★', (int) $row->rating ) . str_repeat( '☆', 5 - (int) $row->rating ) ); ?>
								</span>
							</td>
							<td>
								<strong><?php echo esc_html( $row->reviewer_name ); ?></strong>
								<?php if ( $row->reviewer_city ) : ?><br><small style="color:#666;"><?php echo esc_html( $row->reviewer_city ); ?></small><?php endif; ?>
								<?php if ( $row->treatment ) : ?><br><small style="color:#1a56db;"><?php echo esc_html( $row->treatment ); ?></small><?php endif; ?>
							</td>
							<td><?php echo $row->doctor_id ? esc_html( get_the_title( $row->doctor_id ) ) : '—'; ?></td>
							<td><?php echo esc_html( mb_substr( $row->content, 0, 90 ) ) . ( mb_strlen( $row->content ) > 90 ? '...' : '' ); ?></td>
							<td>
								<?php if ( $row->verified ) : ?>
									<span title="<?php esc_attr_e( 'ثبت دستی توسط منشی', STMC_TEXT ); ?>">👤 <?php esc_html_e( 'دستی', STMC_TEXT ); ?></span>
								<?php else : ?>
									<span title="<?php esc_attr_e( 'ثبت شده از فرم عمومی سایت', STMC_TEXT ); ?>">🌐 <?php esc_html_e( 'سایت', STMC_TEXT ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="stmc-review-status stmc-review-status--<?php echo esc_attr( $row->status ); ?>">
									<?php echo esc_html( $status_labels[ $row->status ] ?? $row->status ); ?>
								</span>
							</td>
							<td>
								<div style="display:flex;gap:4px;flex-wrap:wrap;">
									<?php if ( 'approved' !== $row->status ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'stmc_review_status_' . $row->id ); ?>
										<input type="hidden" name="action" value="stmc_update_review">
										<input type="hidden" name="review_id" value="<?php echo absint( $row->id ); ?>">
										<input type="hidden" name="new_status" value="approved">
										<button type="submit" class="button button-small" style="color:#16a34a;">✓ <?php esc_html_e( 'تأیید', STMC_TEXT ); ?></button>
									</form>
									<?php endif; ?>

									<?php if ( 'rejected' !== $row->status ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'stmc_review_status_' . $row->id ); ?>
										<input type="hidden" name="action" value="stmc_update_review">
										<input type="hidden" name="review_id" value="<?php echo absint( $row->id ); ?>">
										<input type="hidden" name="new_status" value="rejected">
										<button type="submit" class="button button-small" style="color:#dc2626;">✕ <?php esc_html_e( 'رد', STMC_TEXT ); ?></button>
									</form>
									<?php endif; ?>

									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'حذف این نظر قطعی است. ادامه می‌دهید؟', STMC_TEXT ) ); ?>');">
										<?php wp_nonce_field( 'stmc_delete_review_' . $row->id ); ?>
										<input type="hidden" name="action" value="stmc_delete_review">
										<input type="hidden" name="review_id" value="<?php echo absint( $row->id ); ?>">
										<button type="submit" class="button button-small">🗑</button>
									</form>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- ── فرم ثبت دستی توسط منشی ── -->
				<div class="postbox" style="padding:20px;">
					<h2 style="margin-top:0;">➕ <?php esc_html_e( 'ثبت نظر دستی', STMC_TEXT ); ?></h2>
					<p class="description"><?php esc_html_e( 'برای نظراتی که حضوری یا تلفنی از بیمار گرفته‌اید.', STMC_TEXT ); ?></p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'stmc_add_review_manual' ); ?>
						<input type="hidden" name="action" value="stmc_add_review_manual">

						<p>
							<label><strong><?php esc_html_e( 'پزشک', STMC_TEXT ); ?> *</strong><br>
							<select name="doctor_id" required style="width:100%;">
								<option value=""><?php esc_html_e( '— انتخاب کنید —', STMC_TEXT ); ?></option>
								<?php foreach ( $doctors as $doc ) : ?>
									<option value="<?php echo esc_attr( $doc->ID ); ?>"><?php echo esc_html( $doc->post_title ); ?></option>
								<?php endforeach; ?>
							</select></label>
						</p>

						<p>
							<label><strong><?php esc_html_e( 'امتیاز', STMC_TEXT ); ?></strong><br>
							<select name="rating" style="width:100%;">
								<option value="5">★★★★★ (5)</option>
								<option value="4">★★★★☆ (4)</option>
								<option value="3">★★★☆☆ (3)</option>
								<option value="2">★★☆☆☆ (2)</option>
								<option value="1">★☆☆☆☆ (1)</option>
							</select></label>
						</p>

						<p><label><strong><?php esc_html_e( 'نام بیمار', STMC_TEXT ); ?> *</strong><br>
							<input type="text" name="reviewer_name" required style="width:100%;"></label></p>

						<p><label><strong><?php esc_html_e( 'شهر', STMC_TEXT ); ?></strong><br>
							<input type="text" name="reviewer_city" style="width:100%;"></label></p>

						<p><label><strong><?php esc_html_e( 'نوع درمان', STMC_TEXT ); ?></strong><br>
							<input type="text" name="treatment" style="width:100%;"></label></p>

						<p><label><strong><?php esc_html_e( 'متن نظر', STMC_TEXT ); ?> *</strong><br>
							<textarea name="content" rows="4" required style="width:100%;"></textarea></label></p>

						<p><label><input type="checkbox" name="auto_approve" value="1" checked> <?php esc_html_e( 'تأیید فوری (نمایش بلافاصله در سایت)', STMC_TEXT ); ?></label></p>

						<button type="submit" class="button button-primary"><?php esc_html_e( 'ثبت نظر', STMC_TEXT ); ?></button>
					</form>
				</div>

			</div>

			<style>
			.stmc-pending-badge { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 6px; border-radius:99px; background:#dc2626; color:#fff; font-size:12px; font-weight:700; margin-right:6px; vertical-align:middle; }
			.stmc-stars-mini { color:#f59e0b; font-size:13px; white-space:nowrap; }
			.stmc-review-status { padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700; white-space:nowrap; }
			.stmc-review-status--pending  { background:#fef3c7; color:#92400e; }
			.stmc-review-status--approved { background:#d1fae5; color:#065f46; }
			.stmc-review-status--rejected { background:#fee2e2; color:#991b1b; }
			</style>
		</div>
		<?php
	}

	// ─── Update status (approve/reject) ───────────────────────────────────────

	public function handle_status_update(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		if ( ! $review_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_review_status_' . $review_id ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		$new_status = sanitize_key( $_POST['new_status'] ?? 'pending' );

		( new Repository() )->update_status( $review_id, $new_status );

		wp_redirect( admin_url( 'admin.php?page=stmc-reviews&saved=1' ) );
		exit;
	}

	// ─── Manual add by staff ───────────────────────────────────────────────────

	public function handle_manual_add(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_add_review_manual' ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		$doctor_id = absint( $_POST['doctor_id'] ?? 0 );
		$name      = sanitize_text_field( wp_unslash( $_POST['reviewer_name'] ?? '' ) );
		$content   = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );

		if ( ! $doctor_id || ! $name || ! $content ) {
			wp_die( esc_html__( 'لطفاً فیلدهای الزامی را تکمیل کنید.', STMC_TEXT ) );
		}

		( new Repository() )->create_manual( [
			'doctor_id'     => $doctor_id,
			'reviewer_name' => $name,
			'reviewer_city' => sanitize_text_field( wp_unslash( $_POST['reviewer_city'] ?? '' ) ) ?: null,
			'rating'        => absint( $_POST['rating'] ?? 5 ),
			'content'       => $content,
			'treatment'     => sanitize_text_field( wp_unslash( $_POST['treatment'] ?? '' ) ) ?: null,
		], ! empty( $_POST['auto_approve'] ) );

		wp_redirect( admin_url( 'admin.php?page=stmc-reviews&saved=1' ) );
		exit;
	}

	// ─── Delete ─────────────────────────────────────────────────────────────────

	public function handle_delete(): void {
		$review_id = absint( $_POST['review_id'] ?? 0 );
		if ( ! $review_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STMC_TEXT ) );
		}
		if ( ! check_admin_referer( 'stmc_delete_review_' . $review_id ) ) {
			wp_die( esc_html__( 'خطای امنیتی', STMC_TEXT ) );
		}

		( new Repository() )->delete( $review_id );

		wp_redirect( admin_url( 'admin.php?page=stmc-reviews&saved=1' ) );
		exit;
	}
}

<?php
/**
 * SignTeb MedCore — Template Tags
 *
 * توابع نمایشی که مستقیماً در template‌ها فراخوانی می‌شوند.
 * هر تابع خروجی HTML امن با esc_ تولید می‌کند.
 *
 * @package SignTeb_MedCore
 */

defined( 'ABSPATH' ) || exit;

class MedCore_Template_Tags {

	public function __construct() {
		// No hooks needed — these are pure output functions called directly
	}

	// ─── Site Header ──────────────────────────────────────────────────────────

	/**
	 * لوگو یا نام سایت
	 */
	public static function site_logo(): void {
		if ( has_custom_logo() ) {
			the_custom_logo();
			return;
		}
		printf(
			'<a href="%s" class="site-name" rel="home">%s</a>',
			esc_url( home_url( '/' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
	}

	// ─── Breadcrumbs ──────────────────────────────────────────────────────────

	/**
	 * نمایش Breadcrumb با Schema markup
	 */
	public static function breadcrumbs(): void {
		$crumbs = stmc_get_breadcrumbs();

		if ( count( $crumbs ) <= 1 ) {
			return; // صفحه اصلی نیاز به breadcrumb ندارد
		}

		echo '<nav class="stmc-breadcrumbs" aria-label="' . esc_attr__( 'مسیر صفحه', 'signteb-medcore' ) . '">';
		echo '<ol class="stmc-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		foreach ( $crumbs as $index => $crumb ) {
			$position = $index + 1;
			$is_last  = ( $index === count( $crumbs ) - 1 );

			echo '<li class="stmc-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( ! $is_last && ! empty( $crumb['url'] ) ) {
				printf(
					'<a href="%s" class="stmc-breadcrumbs__link" itemprop="item"><span itemprop="name">%s</span></a>',
					esc_url( $crumb['url'] ),
					esc_html( $crumb['name'] )
				);
			} else {
				printf(
					'<span class="stmc-breadcrumbs__current" itemprop="name" aria-current="page">%s</span>',
					esc_html( $crumb['name'] )
				);
			}

			printf( '<meta itemprop="position" content="%d">', $position );
			echo '</li>';

			if ( ! $is_last ) {
				echo '<li class="stmc-breadcrumbs__sep" aria-hidden="true">/</li>';
			}
		}

		echo '</ol>';
		echo '</nav>';
	}

	// ─── Doctor Card ──────────────────────────────────────────────────────────

	/**
	 * نمایش کارت پزشک (برای لیست‌ها)
	 *
	 * @param int $post_id شناسه پست پزشک
	 */
	public static function doctor_card( int $post_id = 0 ): void {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$name       = esc_html( get_the_title( $post_id ) );
		$url        = esc_url( get_permalink( $post_id ) );
		$specialty  = esc_html( stmc_doctor_meta( 'specialty', $post_id ) );
		$exp        = absint( stmc_doctor_meta( 'experience_yrs', $post_id ) );
		$patients   = absint( stmc_doctor_meta( 'patients_count', $post_id ) );
		$whatsapp   = esc_url( stmc_doctor_whatsapp_url( $post_id ) );
		$img_url    = esc_url( stmc_get_thumbnail_url( $post_id, 'stmc-doctor-card', 'doctor' ) );

		// Specialty terms
		$terms = get_the_terms( $post_id, 'specialty' );
		$term_html = '';
		if ( $terms && ! is_wp_error( $terms ) ) {
			$term_html = sprintf(
				'<a href="%s" class="stmc-badge stmc-badge-specialty">%s</a>',
				esc_url( get_term_link( $terms[0] ) ),
				esc_html( $terms[0]->name )
			);
		}
		?>
		<article class="stmc-doctor-card" itemscope itemtype="https://schema.org/Physician">
			<a href="<?php echo $url; ?>" class="stmc-doctor-card__image-wrap" tabindex="-1" aria-hidden="true">
				<img
					src="<?php echo $img_url; ?>"
					alt="<?php echo $name; ?>"
					class="stmc-doctor-card__image"
					loading="lazy"
					decoding="async"
					width="400" height="450"
					itemprop="image"
				>
			</a>

			<div class="stmc-doctor-card__body">
				<?php echo $term_html; ?>

				<h3 class="stmc-doctor-card__name" itemprop="name">
					<a href="<?php echo $url; ?>"><?php echo $name; ?></a>
				</h3>

				<?php if ( $specialty ) : ?>
					<p class="stmc-doctor-card__specialty" itemprop="medicalSpecialty">
						<?php echo $specialty; ?>
					</p>
				<?php endif; ?>

				<?php if ( $exp || $patients ) : ?>
					<div class="stmc-doctor-card__stats">
						<?php if ( $exp ) : ?>
							<div class="stmc-doctor-card__stat">
								<span class="stmc-doctor-card__stat-value"><?php echo stmc_num_fa( $exp ); ?>+</span>
								<span class="stmc-doctor-card__stat-label"><?php esc_html_e( 'سال تجربه', 'signteb-medcore' ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( $patients ) : ?>
							<div class="stmc-doctor-card__stat">
								<span class="stmc-doctor-card__stat-value"><?php echo stmc_num_fa( number_format( $patients ) ); ?>+</span>
								<span class="stmc-doctor-card__stat-label"><?php esc_html_e( 'بیمار', 'signteb-medcore' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="stmc-doctor-card__actions">
					<a href="<?php echo $url; ?>" class="stmc-btn stmc-btn-primary stmc-btn-sm">
						<?php esc_html_e( 'مشاهده پروفایل', 'signteb-medcore' ); ?>
					</a>
					<?php if ( $whatsapp ) : ?>
						<a
							href="<?php echo $whatsapp; ?>"
							class="stmc-btn stmc-btn-ghost stmc-btn-sm"
							target="_blank"
							rel="noopener noreferrer"
							aria-label="<?php esc_attr_e( 'تماس از طریق واتساپ', 'signteb-medcore' ); ?>"
						>
							<?php esc_html_e( 'واتساپ', 'signteb-medcore' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<meta itemprop="url" content="<?php echo $url; ?>">
		</article>
		<?php
	}

	// ─── Service Card ─────────────────────────────────────────────────────────

	/**
	 * کارت خدمت پزشکی
	 */
	public static function service_card( int $post_id = 0 ): void {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$title      = esc_html( get_the_title( $post_id ) );
		$url        = esc_url( get_permalink( $post_id ) );
		$excerpt    = wp_trim_words( get_the_excerpt( $post_id ), 20, '...' );
		$duration   = esc_html( get_post_meta( $post_id, 'stmc_service_duration', true ) );
		$price_from = esc_html( get_post_meta( $post_id, 'stmc_service_price_from', true ) );
		$img_url    = esc_url( stmc_get_thumbnail_url( $post_id, 'stmc-service-thumb', 'service' ) );
		?>
		<article class="stmc-card stmc-service-card" itemscope itemtype="https://schema.org/MedicalProcedure">
			<a href="<?php echo $url; ?>" class="stmc-service-card__image-wrap" tabindex="-1">
				<img
					src="<?php echo $img_url; ?>"
					alt="<?php echo $title; ?>"
					class="stmc-service-card__image"
					loading="lazy"
					decoding="async"
					width="400" height="250"
					itemprop="image"
				>
			</a>

			<div class="stmc-card-body">
				<h3 class="stmc-service-card__title" itemprop="name">
					<a href="<?php echo $url; ?>"><?php echo $title; ?></a>
				</h3>

				<?php if ( $excerpt ) : ?>
					<p class="stmc-service-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<div class="stmc-service-card__meta">
					<?php if ( $duration ) : ?>
						<span class="stmc-service-card__meta-item">
							<span aria-hidden="true">⏱</span>
							<?php echo $duration; ?>
						</span>
					<?php endif; ?>
					<?php if ( $price_from ) : ?>
						<span class="stmc-service-card__meta-item stmc-service-card__price">
							<?php esc_html_e( 'از:', 'signteb-medcore' ); ?>
							<?php echo $price_from; ?>
						</span>
					<?php endif; ?>
				</div>

				<a href="<?php echo $url; ?>" class="stmc-btn stmc-btn-outline stmc-btn-sm stmc-btn-full">
					<?php esc_html_e( 'اطلاعات بیشتر', 'signteb-medcore' ); ?>
				</a>
			</div>

			<meta itemprop="url" content="<?php echo $url; ?>">
		</article>
		<?php
	}

	// ─── Pagination ───────────────────────────────────────────────────────────

	/**
	 * Pagination با استایل MedCore
	 */
	public static function pagination(): void {
		$pagination = paginate_links( [
			'type'      => 'array',
			'prev_text' => is_rtl() ? '→' : '←',
			'next_text' => is_rtl() ? '←' : '→',
		] );

		if ( ! $pagination ) {
			return;
		}

		echo '<nav class="stmc-pagination" aria-label="' . esc_attr__( 'صفحه‌بندی', 'signteb-medcore' ) . '">';
		echo '<ul class="stmc-pagination__list">';
		foreach ( $pagination as $page ) {
			echo '<li class="stmc-pagination__item">' . $page . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul>';
		echo '</nav>';
	}

	// ─── WhatsApp Float Button ────────────────────────────────────────────────

	/**
	 * دکمه شناور واتساپ
	 */
	public static function whatsapp_float(): void {
		$number = get_theme_mod( 'stmc_whatsapp', '' );
		if ( ! $number ) {
			return;
		}

		$url = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $number );
		printf(
			'<a href="%s" class="stmc-whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'تماس از طریق واتساپ', 'signteb-medcore' ),
			// WhatsApp SVG icon
			'<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.116 1.535 5.845L.057 23.49l5.797-1.522A11.934 11.934 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.893 0-3.66-.523-5.17-1.428l-.37-.22-3.44.902.917-3.352-.24-.386A9.944 9.944 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>'
		);
	}
}

// ─── Global shorthand functions ────────────────────────────────────────────────
// این توابع wrapper هستند که در template‌ها راحت‌تر استفاده می‌شوند

function stmc_site_logo(): void {
	MedCore_Template_Tags::site_logo();
}

function stmc_breadcrumbs(): void {
	MedCore_Template_Tags::breadcrumbs();
}

function stmc_doctor_card( int $post_id = 0 ): void {
	MedCore_Template_Tags::doctor_card( $post_id );
}

function stmc_service_card( int $post_id = 0 ): void {
	MedCore_Template_Tags::service_card( $post_id );
}

function stmc_pagination(): void {
	MedCore_Template_Tags::pagination();
}

function stmc_whatsapp_float(): void {
	MedCore_Template_Tags::whatsapp_float();
}

// ─── Shortcodes for use inside FSE block templates ─────────────────────────
// نکته مهم: فایل‌های templates/*.html و parts/*.html به‌صورت block markup
// خوانده می‌شوند (file_get_contents) و هرگز از موتور PHP عبور نمی‌کنند، پس
// تگ‌های <?php ?> داخل آن‌ها اجرا نمی‌شوند و به‌صورت متن خام چاپ می‌شوند.
// شورت‌کد تنها راه رسمی WordPress برای اجرای PHP داخل این فایل‌هاست، چون
// get_the_block_template_html() پس از do_blocks() مقدار do_shortcode() را
// روی کل خروجی templateها اجرا می‌کند.

add_action( 'init', function () {

	add_shortcode( 'stmc_breadcrumbs', function (): string {
		ob_start();
		stmc_breadcrumbs();
		return (string) ob_get_clean();
	} );

	add_shortcode( 'stmc_whatsapp_float', function (): string {
		ob_start();
		stmc_whatsapp_float();
		return (string) ob_get_clean();
	} );

	add_shortcode( 'stmc_copyright_year', function (): string {
		return esc_html( current_time( 'Y' ) );
	} );

} );

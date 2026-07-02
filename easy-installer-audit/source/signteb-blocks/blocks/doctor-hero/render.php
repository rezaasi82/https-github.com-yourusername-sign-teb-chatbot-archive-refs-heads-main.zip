<?php
/**
 * SignTeb Blocks — Doctor Hero: Server-Side Render
 *
 * متغیرهای موجود:
 * $attributes  — آرایه attributes از block.json
 * $content     — محتوای InnerBlocks (در این block استفاده نمی‌شود)
 * $block       — WP_Block object
 *
 * @package SignTeb_Blocks
 */

defined( 'ABSPATH' ) || exit;

// ── Doctor ID ─────────────────────────────────────────────────────────────────
$doctor_id = (int) ( $attributes['doctorId'] ?? get_the_ID() );

if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) ) {
	// در Gutenberg Editor: نمایش placeholder
	if ( is_admin() || defined( 'REST_REQUEST' ) ) {
		echo '<div class="stmb-doctor-hero stmb-doctor-hero--placeholder">';
		echo '<p>' . esc_html__( 'یک پزشک از پانل تنظیمات انتخاب کنید.', 'signteb-blocks' ) . '</p>';
		echo '</div>';
	}
	return;
}

// ── Pull Meta ─────────────────────────────────────────────────────────────────
$name       = get_the_title( $doctor_id );
$specialty  = get_post_meta( $doctor_id, 'stmc_doctor_specialty',       true );
$subspc     = get_post_meta( $doctor_id, 'stmc_doctor_subspecialty',    true );
$exp        = (int) get_post_meta( $doctor_id, 'stmc_doctor_experience_yrs', true );
$patients   = (int) get_post_meta( $doctor_id, 'stmc_doctor_patients_count', true );
$license    = get_post_meta( $doctor_id, 'stmc_doctor_license_no',      true );
$whatsapp   = get_post_meta( $doctor_id, 'stmc_doctor_whatsapp',        true );
$booking    = get_post_meta( $doctor_id, 'stmc_doctor_booking_url',     true );
$education  = get_post_meta( $doctor_id, 'stmc_doctor_education',       true );
$url        = get_permalink( $doctor_id );
$img_url    = get_the_post_thumbnail_url( $doctor_id, 'stmc-doctor-hero' ) ?: '';

// Specialty terms
$terms     = get_the_terms( $doctor_id, 'specialty' );
$term_html = '';
if ( $terms && ! is_wp_error( $terms ) ) {
	foreach ( array_slice( $terms, 0, 2 ) as $term ) {
		$term_html .= sprintf(
			'<a href="%s" class="stmb-badge stmb-badge--specialty">%s</a>',
			esc_url( get_term_link( $term ) ),
			esc_html( $term->name )
		);
	}
}

// WhatsApp URL
$wa_url = '';
if ( $whatsapp ) {
	$wa_url = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $whatsapp );
}

// Attrs
$theme    = esc_attr( $attributes['theme']       ?? 'dark' );
$layout   = esc_attr( $attributes['layoutStyle'] ?? 'split' );
$show_stats    = (bool) ( $attributes['showStats']    ?? true );
$show_whatsapp = (bool) ( $attributes['showWhatsapp'] ?? true );
$show_booking  = (bool) ( $attributes['showBooking']  ?? true );
$cta_text      = esc_html( $attributes['ctaText']          ?? __( 'رزرو نوبت', 'signteb-blocks' ) );
$cta_wa_text   = esc_html( $attributes['ctaWhatsappText']  ?? __( 'مشاوره در WhatsApp', 'signteb-blocks' ) );

// Education — first line only for hero
$edu_first = '';
if ( $education ) {
	$lines     = array_filter( explode( "\n", $education ) );
	$edu_first = trim( reset( $lines ) );
}

// Number formatter (FA)
// این فایل توسط WordPress برای هر instance از block با require (نه require_once)
// بارگذاری می‌شود؛ بدون این گارد، وجود دو Doctor Hero در یک صفحه باعث
// Fatal error: Cannot redeclare function می‌شود.
if ( ! function_exists( 'stmb_num' ) ) {
	function stmb_num( int $n ): string {
		$fa = [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ];
		return str_replace( range(0,9), $fa, (string) $n );
	}
}

?>
<section
	class="stmb-doctor-hero stmb-doctor-hero--<?php echo $theme; ?> stmb-doctor-hero--<?php echo $layout; ?>"
	itemscope
	itemtype="https://schema.org/Physician"
	data-wp-interactive="signteb/doctor-hero"
>
	<!-- Ambient background blobs -->
	<div class="stmb-hero-orb stmb-hero-orb--1" aria-hidden="true"></div>
	<div class="stmb-hero-orb stmb-hero-orb--2" aria-hidden="true"></div>

	<div class="stmb-doctor-hero__inner">

		<?php if ( 'split' === $layout || 'minimal' === $layout ) : ?>
		<!-- ── Content Side ──────────────────────────────────── -->
		<div class="stmb-doctor-hero__content">

			<!-- Specialty badges -->
			<?php if ( $term_html ) : ?>
				<div class="stmb-doctor-hero__badges"><?php echo $term_html; ?></div>
			<?php endif; ?>

			<!-- Name -->
			<h1 class="stmb-doctor-hero__name" itemprop="name">
				<?php echo esc_html( $name ); ?>
			</h1>

			<!-- Specialty -->
			<?php if ( $specialty ) : ?>
				<p class="stmb-doctor-hero__specialty" itemprop="medicalSpecialty">
					<?php echo esc_html( $specialty ); ?>
					<?php if ( $subspc ) : ?>
						<span class="stmb-doctor-hero__subspc"> — <?php echo esc_html( $subspc ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<!-- Education -->
			<?php if ( $edu_first ) : ?>
				<p class="stmb-doctor-hero__edu">
					<span aria-hidden="true">🎓</span>
					<?php echo esc_html( $edu_first ); ?>
				</p>
			<?php endif; ?>

			<!-- License -->
			<?php if ( $license ) : ?>
				<p class="stmb-doctor-hero__license">
					<span aria-hidden="true">🪪</span>
					<?php printf( esc_html__( 'نظام پزشکی: %s', 'signteb-blocks' ), esc_html( $license ) ); ?>
				</p>
			<?php endif; ?>

			<!-- Stats -->
			<?php if ( $show_stats && ( $exp || $patients ) ) : ?>
				<div class="stmb-doctor-hero__stats" role="list">

					<?php if ( $exp ) : ?>
						<div class="stmb-stat" role="listitem">
							<span class="stmb-stat__value" data-counter="<?php echo $exp; ?>" aria-label="<?php echo $exp; ?> سال تجربه">
								<?php echo stmb_num( $exp ); ?>+
							</span>
							<span class="stmb-stat__label"><?php esc_html_e( 'سال تجربه', 'signteb-blocks' ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $patients ) : ?>
						<div class="stmb-stat" role="listitem">
							<span class="stmb-stat__value" data-counter="<?php echo $patients; ?>" aria-label="<?php echo number_format($patients); ?> بیمار">
								<?php echo stmb_num( (int) round( $patients / 1000 ) ); ?>K+
							</span>
							<span class="stmb-stat__label"><?php esc_html_e( 'بیمار موفق', 'signteb-blocks' ); ?></span>
						</div>
					<?php endif; ?>

					<div class="stmb-stat" role="listitem">
						<span class="stmb-stat__value">★ ۵</span>
						<span class="stmb-stat__label"><?php esc_html_e( 'رضایت بیماران', 'signteb-blocks' ); ?></span>
					</div>

				</div>
			<?php endif; ?>

			<!-- CTA Buttons -->
			<div class="stmb-doctor-hero__actions">

				<?php if ( $show_booking ) : ?>
					<a
						href="#stmc-appointment-<?php echo $doctor_id; ?>"
						class="stmb-btn stmb-btn--gold stmb-btn--lg"
					>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
						<?php echo $cta_text; ?>
					</a>
				<?php endif; ?>

				<?php if ( $show_whatsapp && $wa_url ) : ?>
					<a
						href="<?php echo esc_url( $wa_url ); ?>"
						class="stmb-btn stmb-btn--whatsapp stmb-btn--lg"
						target="_blank"
						rel="noopener noreferrer"
						itemprop="telephone"
					>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
						<?php echo $cta_wa_text; ?>
					</a>
				<?php endif; ?>

			</div>

			<!-- Trust bar -->
			<div class="stmb-doctor-hero__trust">
				<span class="stmb-trust-item">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
					<?php esc_html_e( 'مشاوره رایگان', 'signteb-blocks' ); ?>
				</span>
				<span class="stmb-trust-item">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
					<?php esc_html_e( 'پاسخگویی ۲۴ ساعته', 'signteb-blocks' ); ?>
				</span>
				<span class="stmb-trust-item">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
					<?php esc_html_e( 'کاملاً محرمانه', 'signteb-blocks' ); ?>
				</span>
			</div>

		</div><!-- /.content -->
		<?php endif; ?>

		<!-- ── Image Side ─────────────────────────────────────── -->
		<?php if ( $img_url ) : ?>
		<div class="stmb-doctor-hero__media">

			<div class="stmb-doctor-hero__img-wrap">
				<img
					src="<?php echo esc_url( $img_url ); ?>"
					alt="<?php echo esc_attr( sprintf( __( 'پروفایل %s', 'signteb-blocks' ), $name ) ); ?>"
					class="stmb-doctor-hero__img"
					loading="eager"
					fetchpriority="high"
					decoding="async"
					width="800" height="900"
					itemprop="image"
				>
				<!-- Glass overlay card on image -->
				<div class="stmb-doctor-hero__img-badge" aria-hidden="true">
					<span class="stmb-doctor-hero__img-badge-icon">✓</span>
					<span><?php esc_html_e( 'پزشک تأیید شده', 'signteb-blocks' ); ?></span>
				</div>
			</div>

		</div>
		<?php endif; ?>

		<?php if ( 'centered' === $layout ) : ?>
		<!-- Centered layout content after image -->
		<div class="stmb-doctor-hero__content stmb-doctor-hero__content--centered">
			<h1 class="stmb-doctor-hero__name" itemprop="name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( $specialty ) : ?>
				<p class="stmb-doctor-hero__specialty" itemprop="medicalSpecialty"><?php echo esc_html( $specialty ); ?></p>
			<?php endif; ?>
			<div class="stmb-doctor-hero__actions">
				<?php if ( $show_booking ) : ?>
					<a href="#stmc-appointment-<?php echo $doctor_id; ?>" class="stmb-btn stmb-btn--gold stmb-btn--lg"><?php echo $cta_text; ?></a>
				<?php endif; ?>
				<?php if ( $show_whatsapp && $wa_url ) : ?>
					<a href="<?php echo esc_url( $wa_url ); ?>" class="stmb-btn stmb-btn--whatsapp stmb-btn--lg" target="_blank" rel="noopener noreferrer"><?php echo $cta_wa_text; ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

	</div><!-- /.inner -->

	<!-- Hidden Schema meta -->
	<meta itemprop="url" content="<?php echo esc_url( $url ); ?>">

</section>
<?php

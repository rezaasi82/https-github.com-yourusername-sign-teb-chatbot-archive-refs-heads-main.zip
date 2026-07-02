<?php
/**
 * SignTeb MedCore — Helper Functions
 *
 * توابع کمکی global که در همه template‌ها و کلاس‌ها قابل استفاده‌اند.
 * همه توابع با پیشوند stmc_ نامگذاری شده‌اند تا از تداخل جلوگیری شود.
 *
 * @package SignTeb_MedCore
 */

defined( 'ABSPATH' ) || exit;

// ─── SVG Helper ───────────────────────────────────────────────────────────────

/**
 * خروجی SVG امن از پوشه assets/images/icons/
 *
 * @param string $name  نام فایل SVG بدون پسوند
 * @param string $group زیرپوشه: 'medical' | 'ui'
 * @param array  $attrs آرایه attribute‌های اضافه مثل class, width
 */
function stmc_svg( string $name, string $group = 'ui', array $attrs = [] ): void {
	$path = MEDCORE_DIR . '/assets/images/icons/' . $group . '/' . $name . '.svg';

	if ( ! file_exists( $path ) ) {
		return;
	}

	$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	// Add attributes to root <svg> tag
	if ( ! empty( $attrs ) ) {
		$attr_str = '';
		foreach ( $attrs as $key => $val ) {
			$attr_str .= ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
		}
		$svg = preg_replace( '/<svg/', '<svg' . $attr_str, $svg, 1 );
	}

	echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG sanitized on upload
}

// ─── Doctor Helpers ───────────────────────────────────────────────────────────

/**
 * دریافت Meta Value پزشک با fallback
 *
 * @param string $key    نام meta key (بدون پیشوند stmc_doctor_)
 * @param int    $doc_id شناسه پست پزشک (پیش‌فرض: پست جاری)
 */
function stmc_doctor_meta( string $key, int $doc_id = 0 ): string {
	if ( ! $doc_id ) {
		$doc_id = get_the_ID();
	}
	return (string) get_post_meta( $doc_id, 'stmc_doctor_' . $key, true );
}

/**
 * لینک WhatsApp پزشک
 */
function stmc_doctor_whatsapp_url( int $doc_id = 0 ): string {
	$number = stmc_doctor_meta( 'whatsapp', $doc_id );
	if ( ! $number ) {
		return '';
	}
	// Remove non-numeric characters and ensure country code
	$number = preg_replace( '/[^0-9]/', '', $number );
	return 'https://wa.me/' . $number;
}

/**
 * تبدیل اعداد انگلیسی به فارسی
 */
function stmc_num_fa( $number ): string {
	if ( ! is_rtl() ) {
		return (string) $number;
	}
	$fa = [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ];
	$en = [ '0','1','2','3','4','5','6','7','8','9' ];
	return str_replace( $en, $fa, (string) $number );
}

// ─── Schema / SEO Helpers ─────────────────────────────────────────────────────

/**
 * خروجی JSON-LD امن در head
 *
 * @param array $schema داده Schema
 */
function stmc_json_ld( array $schema ): void {
	if ( empty( $schema ) ) {
		return;
	}
	echo '<script type="application/ld+json">';
	echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	echo '</script>' . "\n";
}

/**
 * تولید Breadcrumb Array
 */
function stmc_get_breadcrumbs(): array {
	$crumbs = [];

	$crumbs[] = [
		'name' => __( 'خانه', 'signteb-medcore' ),
		'url'  => home_url( '/' ),
	];

	if ( is_singular() ) {
		// Post type archives
		$post_type = get_post_type();
		$pto       = get_post_type_object( $post_type );

		if ( $pto && $pto->has_archive ) {
			$crumbs[] = [
				'name' => $pto->labels->name,
				'url'  => get_post_type_archive_link( $post_type ),
			];
		}

		// Taxonomy terms (first term)
		$tax_map = [
			'doctor'          => 'specialty',
			'medical-service' => 'treatment-type',
		];

		if ( isset( $tax_map[ $post_type ] ) ) {
			$terms = get_the_terms( get_the_ID(), $tax_map[ $post_type ] );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$crumbs[] = [
					'name' => $terms[0]->name,
					'url'  => get_term_link( $terms[0] ),
				];
			}
		}

		// Current post
		$crumbs[] = [
			'name' => get_the_title(),
			'url'  => '',
		];

	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();

		if ( ! ( $term instanceof WP_Term ) ) {
			return $crumbs;
		}

		// Parent taxonomy archive
		if ( $term->taxonomy && 'specialty' === $term->taxonomy ) {
			$crumbs[] = [
				'name' => __( 'پزشکان', 'signteb-medcore' ),
				'url'  => get_post_type_archive_link( 'doctor' ),
			];
		}
		$crumbs[] = [
			'name' => $term->name,
			'url'  => '',
		];
	} elseif ( is_post_type_archive() ) {
		$crumbs[] = [
			'name' => post_type_archive_title( '', false ),
			'url'  => '',
		];
	} elseif ( is_search() ) {
		$crumbs[] = [
			'name' => sprintf( __( 'نتایج جستجو برای: %s', 'signteb-medcore' ), get_search_query() ),
			'url'  => '',
		];
	} elseif ( is_404() ) {
		$crumbs[] = [
			'name' => __( 'صفحه یافت نشد', 'signteb-medcore' ),
			'url'  => '',
		];
	}

	return $crumbs;
}

// ─── Template Helpers ─────────────────────────────────────────────────────────

/**
 * دریافت تصویر با fallback placeholder
 *
 * @param int    $post_id   شناسه پست
 * @param string $size      اندازه تصویر
 * @param string $type      نوع: 'doctor'|'service'|'clinic'
 */
function stmc_get_thumbnail_url( int $post_id, string $size = 'medium', string $type = 'doctor' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		return (string) get_the_post_thumbnail_url( $post_id, $size );
	}

	// Placeholder SVG based on type
	return MEDCORE_URI . '/assets/images/placeholder-' . sanitize_key( $type ) . '.svg';
}

/**
 * نمایش تصویر lazy-loaded با placeholder
 */
function stmc_lazy_image( int $post_id, string $size, string $alt, array $attrs = [] ): void {
	$url = stmc_get_thumbnail_url( $post_id, $size );

	$default_attrs = [
		'loading' => 'lazy',
		'decoding'=> 'async',
		'alt'     => $alt,
	];

	$merged = array_merge( $default_attrs, $attrs );
	$attr_str = '';
	foreach ( $merged as $key => $val ) {
		$attr_str .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $val ) );
	}

	printf( '<img src="%s"%s>', esc_url( $url ), $attr_str ); // phpcs:ignore
}

/**
 * بررسی وجود sidebar محتوا
 */
function stmc_has_sidebar(): bool {
	if ( is_page_template( 'page-no-sidebar.html' ) ) {
		return false;
	}
	if ( is_page_template( 'page-landing.html' ) ) {
		return false;
	}
	if ( is_singular( [ 'doctor', 'medical-service', 'treatment', 'disease', 'clinic' ] ) ) {
		return false; // CPT‌های پزشکی sidebar ندارند
	}
	return is_active_sidebar( 'sidebar-blog' );
}

// ─── Phone / WhatsApp Helpers ─────────────────────────────────────────────────

/**
 * تبدیل شماره ایرانی به فرمت بین‌المللی
 * مثال: 09191182649 → +989191182649
 */
function stmc_normalize_phone( string $phone ): string {
	$phone = preg_replace( '/[^0-9+]/', '', $phone );
	if ( str_starts_with( $phone, '0' ) ) {
		$phone = '+98' . substr( $phone, 1 );
	} elseif ( str_starts_with( $phone, '98' ) ) {
		$phone = '+' . $phone;
	}
	return $phone;
}

/**
 * لینک تماس تلفنی امن
 */
function stmc_phone_link( string $phone ): string {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
}

// ─── Star Rating ──────────────────────────────────────────────────────────────

/**
 * نمایش ستاره‌های امتیاز
 *
 * @param float $rating عدد ۱ تا ۵
 * @param int   $max    حداکثر ستاره
 */
function stmc_stars( float $rating, int $max = 5 ): void {
	echo '<span class="stmc-stars" aria-label="' . esc_attr( sprintf( __( '%1$s از %2$s ستاره', 'signteb-medcore' ), $rating, $max ) ) . '">';
	for ( $i = 1; $i <= $max; $i++ ) {
		if ( $i <= $rating ) {
			echo '<span class="star star--full" aria-hidden="true">★</span>';
		} elseif ( $i - 0.5 <= $rating ) {
			echo '<span class="star star--half" aria-hidden="true">⯨</span>';
		} else {
			echo '<span class="star star--empty" aria-hidden="true">☆</span>';
		}
	}
	echo '</span>';
}

// ─── Debugging (dev only) ─────────────────────────────────────────────────────

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	/**
	 * dump متغیر با var_export
	 */
	function stmc_dump( $data, bool $die = false ): void { // phpcs:ignore
		echo '<pre style="background:#0f172a;color:#e2e8f0;padding:16px;border-radius:8px;font-size:12px;overflow:auto;direction:ltr;">';
		var_export( $data ); // phpcs:ignore
		echo '</pre>';
		if ( $die ) {
			die();
		}
	}
}

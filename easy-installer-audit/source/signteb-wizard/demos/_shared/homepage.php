<?php
/**
 * سازنده‌ی مشترک صفحه‌ی اصلی دموها.
 *
 * markup استاندارد صفحه‌ی اصلی (هیرو + آمار + گرید خدمات + گرید پزشکان + بخش
 * اضافی اختیاری + FAQ + نظرات + CTA نوبت + CTA تماس) را برمی‌گرداند تا هر دمو
 * فقط داده‌ی خودش را بدهد و markup تکراری/خطاپذیر نشود.
 *
 * پوشه‌ی _shared فایل demo.php ندارد، پس DemoRegistry آن را دمو تلقی نمی‌کند.
 *
 * @package SignTeb_Wizard
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'stwiz_demo_homepage' ) ) {
	/**
	 * @param array $a headline, sub, cta_label, cta_href, stats(array),
	 *                 services_title, doctors_title, faq_title, appt_title,
	 *                 contact_title, testimonials_title, extra(raw block markup)
	 */
	function stwiz_demo_homepage( array $a ): string {
		// ── سکشن‌های قابل‌استفاده‌ی مجدد (هر کدام یک بلوک/ناحیه) ─────────────────
		// JSON_UNESCAPED_UNICODE حیاتی است: در غیر این صورت متن فارسیِ آمار به
		// \u06XX تبدیل و بعد از حذف بک‌اسلش‌ها به‌صورت خام چاپ می‌شود.
		$stats_json = wp_json_encode( $a['stats'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		$heading = static fn( string $t ): string =>
			'<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . $t . '</h2><!-- /wp:heading -->';

		$hero = '
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}}},"backgroundColor":"trust-blue-light","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-trust-blue-light-background-color has-background" style="padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">' . ( $a['headline'] ?? '' ) . '</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">' . ( $a['sub'] ?? '' ) . '</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . ( $a['cta_href'] ?? '/appointment' ) . '">' . ( $a['cta_label'] ?? 'رزرو نوبت' ) . '</a></div><!-- /wp:button --></div>
<!-- /wp:buttons -->
</section>
<!-- /wp:group -->';

		// آمار: theme=dark پیش‌فرض؛ columns بسته به دمو.
		$stats = static fn( int $cols = 4 ): string =>
			'<!-- wp:signteb/stats-counter {"columns":' . $cols . ',"stats":' . $stats_json . '} /-->';

		$services = static function ( string $title, int $cols = 3, string $style = 'glass' ) use ( $heading ): string {
			return $heading( $title ) .
				'<!-- wp:signteb/service-grid {"columns":' . $cols . ',"showPrice":true,"cardStyle":"' . $style . '"} /-->';
		};

		$doctors = static function ( string $title, int $cols = 3, bool $filter = false ) use ( $heading ): string {
			$f = $filter ? ',"showFilter":true' : '';
			return $heading( $title ) .
				'<!-- wp:signteb/doctor-card-grid {"columns":' . $cols . $f . '} /-->';
		};

		$faq     = static fn( string $t ): string => '<!-- wp:signteb/faq-accordion {"title":"' . $t . '"} /-->';
		$testi   = static fn( string $t ): string => '<!-- wp:signteb/testimonials-slider {"title":"' . $t . '"} /-->';
		$appt    = static fn( string $t ): string => '<!-- wp:signteb/appointment-cta {"title":"' . $t . '"} /-->';
		$contact = static fn( string $t ): string => '<!-- wp:signteb/contact-cta {"title":"' . $t . '"} /-->';

		// قبل/بعد — توکن‌هایش هنگام ایمپورت با URL تصویرِ واقعی جایگزین می‌شوند.
		$beforeafter = static fn( string $caption = 'نتایج واقعی درمان' ): string =>
			'<!-- wp:signteb/before-after-slider {"beforeImageUrl":"%%IMG_BEFORE%%","afterImageUrl":"%%IMG_AFTER%%","beforeLabel":"قبل","afterLabel":"بعد","caption":"' . $caption . '"} /-->';

		// عنوان‌های قابل‌override از تعریف دمو.
		$t_services = $a['services_title']     ?? 'خدمات ما';
		$t_doctors  = $a['doctors_title']      ?? 'پزشکان ما';
		$t_faq      = $a['faq_title']          ?? 'سؤالات متداول';
		$t_testi    = $a['testimonials_title'] ?? 'نظر بیماران ما';
		$t_appt     = $a['appt_title']         ?? 'همین امروز نوبت بگیرید';
		$t_contact  = $a['contact_title']      ?? 'تماس با ما';
		$extra      = $a['extra']              ?? '';

		// ── چیدمان‌های مستقل — هر آرکی‌تایپ ترتیب/بلوک/تیتر متفاوتی دارد ──────────
		// هدفِ صریح (معیار پذیرش کاربر): هیچ دو دمویی ساختار یکسان نداشته باشند.
		$layout = $a['layout'] ?? 'clinic';

		switch ( $layout ) {

			// بیمارستان چندتخصصی: بخش‌ها → آمار → پزشکان → اورژانس → نظرات
			case 'enterprise':
				$sections = [
					$hero,
					$services( $t_services ?: 'بخش‌های تخصصی بیمارستان', 4, 'solid' ),
					$stats( 4 ),
					$doctors( $t_doctors ?: 'کادر درمان', 3, true ),
					$contact( $a['contact_title'] ?? 'اورژانس ۲۴ ساعته — همیشه در دسترس' ),
					$testi( $t_testi ),
					$extra,
				];
				break;

			// جراحی زیبایی: قبل/بعد در مرکز توجه → خدمات → نظرات → آمار → رزرو
			case 'showcase':
				$sections = [
					$hero,
					$beforeafter( 'نتایج واقعی جراحی زیبایی' ),
					$services( $t_services ?: 'خدمات زیبایی', 3, 'glass' ),
					$testi( $t_testi ?: 'رضایت مراجعان' ),
					$stats( 4 ),
					$appt( $t_appt ),
					$extra,
				];
				break;

			// آژانس برندینگ: توانمندی‌ها → دستاوردها → فرآیند → مشتریان → تماس
			case 'agency':
				$sections = [
					$hero,
					$services( $t_services ?: 'توانمندی‌های ما', 3, 'solid' ),
					$stats( 4 ),
					$faq( $t_faq ?: 'فرآیند همکاری' ),
					$testi( $t_testi ?: 'مشتریان ما' ),
					$contact( $t_contact ?: 'بریفت را برای ما بفرست' ),
					$extra,
				];
				break;

			// تله‌مدیسین/تک: امکانات پلتفرم → آمار → چطور کار می‌کند → رزرو آنلاین
			case 'tech':
				$sections = [
					$hero,
					$services( $t_services ?: 'امکانات پلتفرم', 3, 'glass' ),
					$stats( 4 ),
					$faq( $t_faq ?: 'چطور کار می‌کند؟' ),
					$appt( $a['appt_title'] ?? 'رزرو ویزیت آنلاین' ),
					$contact( $t_contact ),
					$extra,
				];
				break;

			// ناباروری: آمار → مسیر درمان (steps) → پزشکان → نظرات → رزرو
			case 'journey':
				$sections = [
					$hero,
					$stats( 4 ),
					$faq( $a['faq_title'] ?? 'مسیر درمان، گام‌به‌گام' ),
					$doctors( $t_doctors, 3 ),
					$testi( $t_testi ?: 'داستان موفقیت مراجعان' ),
					$appt( $t_appt ),
					$extra,
				];
				break;

			// گوارش: آموزش‌محور — خدمات → سؤالات شایع → پزشکان → آمار → رزرو
			case 'educational':
				$sections = [
					$hero,
					$services( $t_services, 3, 'glass' ),
					$faq( $a['faq_title'] ?? 'سؤالات شایع بیماران گوارش' ),
					$doctors( $t_doctors, 3 ),
					$stats( 4 ),
					$appt( $t_appt ),
					$extra,
				];
				break;

			// ارتوپدی: نتیجه‌محور — خدمات → پزشکان → نتایج درمان → آمار → رزرو
			case 'results':
				$sections = [
					$hero,
					$services( $t_services ?: 'خدمات ارتوپدی', 3, 'solid' ),
					$doctors( $t_doctors, 3 ),
					$testi( $a['testimonials_title'] ?? 'نتایج درمان بیماران' ),
					$stats( 4 ),
					$appt( $t_appt ),
					$extra,
				];
				break;

			// قلب: اعتماد/تخصص‌محور — تیم فوق‌تخصص جلوتر از خدمات
			case 'expertise':
				$sections = [
					$hero,
					$doctors( $a['doctors_title'] ?? 'تیم فوق‌تخصص قلب', 3, true ),
					$stats( 4 ),
					$services( $t_services, 3, 'glass' ),
					$testi( $t_testi ),
					$appt( $t_appt ),
					$extra,
				];
				break;

			// پیش‌فرض (کلینیک استاندارد): چیدمان کاملِ متعادل
			case 'clinic':
			default:
				$sections = [
					$hero,
					$stats( 4 ),
					$services( $t_services, 3, 'glass' ),
					$doctors( $t_doctors, 3 ),
					$extra,
					$faq( $t_faq ),
					$testi( $t_testi ),
					$appt( $t_appt ),
					$contact( $t_contact ),
				];
				break;
		}

		return implode( "\n\n", array_filter( $sections ) );
	}
}

if ( ! function_exists( 'stwiz_demo_pages' ) ) {
	/**
	 * ساخت آرایه‌ی استاندارد ۷ صفحه‌ی دمو (خانه + درباره + خدمات + پزشکان + FAQ +
	 * تماس + رزرو). هر دمو فقط home markup و متن about را می‌دهد.
	 */
	function stwiz_demo_pages( string $home, string $about, string $doctors_title = 'پزشکان' ): array {
		return [
			[ 'title' => 'خانه', 'slug' => 'home', 'front' => true, 'content' => $home ],
			[ 'title' => 'درباره ما', 'slug' => 'about', 'content' => $about ],
			[ 'title' => 'خدمات', 'slug' => 'services', 'content' => '<!-- wp:signteb/service-grid {"columns":3,"showPrice":true} /-->' ],
			[ 'title' => $doctors_title, 'slug' => 'doctors', 'content' => '<!-- wp:signteb/doctor-card-grid {"columns":3,"showFilter":true} /-->' ],
			[ 'title' => 'سؤالات متداول', 'slug' => 'faq', 'content' => '<!-- wp:signteb/faq-accordion {"title":"سؤالات متداول","allowMultiple":true} /-->' ],
			[ 'title' => 'تماس با ما', 'slug' => 'contact', 'content' => '<!-- wp:signteb/contact-cta {"title":"با ما در تماس باشید"} /-->' ],
			[ 'title' => 'رزرو نوبت', 'slug' => 'appointment', 'template' => 'page-landing', 'content' => '<!-- wp:signteb/appointment-cta {"title":"رزرو نوبت آنلاین"} /-->' ],
		];
	}
}

if ( ! function_exists( 'stwiz_demo_menu' ) ) {
	function stwiz_demo_menu( string $doctors_label = 'پزشکان' ): array {
		return [
			'home'        => 'خانه',
			'services'    => 'خدمات',
			'doctors'     => $doctors_label,
			'about'       => 'درباره ما',
			'faq'         => 'سؤالات متداول',
			'contact'     => 'تماس با ما',
			'appointment' => 'رزرو نوبت',
		];
	}
}

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
		$stats_json = wp_json_encode( $a['stats'] ?? [] );

		$tpl = '
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}}},"backgroundColor":"trust-blue-light","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-trust-blue-light-background-color has-background" style="padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">{{HEADLINE}}</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">{{SUB}}</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{CTA_HREF}}">{{CTA_LABEL}}</a></div><!-- /wp:button --></div>
<!-- /wp:buttons -->
</section>
<!-- /wp:group -->

<!-- wp:signteb/stats-counter {"columns":4,"stats":{{STATS}}} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">{{SERVICES_TITLE}}</h2><!-- /wp:heading -->
<!-- wp:signteb/service-grid {"columns":3,"showPrice":true} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">{{DOCTORS_TITLE}}</h2><!-- /wp:heading -->
<!-- wp:signteb/doctor-card-grid {"columns":3} /-->
{{EXTRA}}
<!-- wp:signteb/faq-accordion {"title":"{{FAQ_TITLE}}"} /-->

<!-- wp:signteb/testimonials-slider {"title":"{{TESTIMONIALS_TITLE}}"} /-->

<!-- wp:signteb/appointment-cta {"title":"{{APPT_TITLE}}"} /-->

<!-- wp:signteb/contact-cta {"title":"{{CONTACT_TITLE}}"} /-->';

		$replace = [
			'{{HEADLINE}}'          => $a['headline'] ?? '',
			'{{SUB}}'               => $a['sub'] ?? '',
			'{{CTA_HREF}}'          => $a['cta_href'] ?? '/appointment',
			'{{CTA_LABEL}}'         => $a['cta_label'] ?? 'رزرو نوبت',
			'{{STATS}}'             => $stats_json,
			'{{SERVICES_TITLE}}'    => $a['services_title'] ?? 'خدمات ما',
			'{{DOCTORS_TITLE}}'     => $a['doctors_title'] ?? 'پزشکان ما',
			'{{EXTRA}}'             => $a['extra'] ?? '',
			'{{FAQ_TITLE}}'         => $a['faq_title'] ?? 'سؤالات متداول',
			'{{TESTIMONIALS_TITLE}}'=> $a['testimonials_title'] ?? 'نظر بیماران ما',
			'{{APPT_TITLE}}'        => $a['appt_title'] ?? 'همین امروز نوبت بگیرید',
			'{{CONTACT_TITLE}}'     => $a['contact_title'] ?? 'تماس با ما',
		];

		return strtr( $tpl, $replace );
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

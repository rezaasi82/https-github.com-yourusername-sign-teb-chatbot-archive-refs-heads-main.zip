<?php
/**
 * SignTeb MedCore — Block Patterns
 * الگوهای آماده Gutenberg برای صفحات پزشکی
 */
defined( 'ABSPATH' ) || exit;

class MedCore_Block_Patterns {

	public function __construct() {
		add_action( 'init', [ $this, 'register_category' ] );
		add_action( 'init', [ $this, 'register_patterns' ] );
	}

	public function register_category(): void {
		register_block_pattern_category( 'signteb-medical', [
			'label'       => __( '⚕️ SignTeb Medical', 'signteb-medcore' ),
			'description' => __( 'الگوهای آماده برای وب‌سایت‌های پزشکی', 'signteb-medcore' ),
		] );
	}

	public function register_patterns(): void {
		$patterns = [
			'doctor-profile-hero'    => $this->pattern_doctor_hero(),
			'appointment-section'    => $this->pattern_appointment(),
			'services-3col'          => $this->pattern_services_grid(),
			'trust-stats-bar'        => $this->pattern_stats_bar(),
			'faq-with-header'        => $this->pattern_faq_section(),
			'contact-with-map'       => $this->pattern_contact_section(),
		];

		foreach ( $patterns as $slug => $data ) {
			register_block_pattern( 'signteb/' . $slug, $data );
		}
	}

	// ── Pattern: Doctor Hero ──────────────────────────────────────────────────

	private function pattern_doctor_hero(): array {
		return [
			'title'         => __( 'پروفایل پزشک Hero', 'signteb-medcore' ),
			'description'   => __( 'بخش Hero کامل برای صفحه پروفایل پزشک', 'signteb-medcore' ),
			'categories'    => [ 'signteb-medical' ],
			'keywords'      => [ 'doctor', 'hero', 'profile', 'پزشک' ],
			'viewportWidth' => 1280,
			'content'       => '<!-- wp:signteb/doctor-hero {"theme":"dark","showStats":true,"showWhatsapp":true,"layoutStyle":"split"} /-->',
		];
	}

	// ── Pattern: Appointment Section ──────────────────────────────────────────

	private function pattern_appointment(): array {
		return [
			'title'       => __( 'بخش رزرو نوبت', 'signteb-medcore' ),
			'description' => __( 'فرم رزرو نوبت با سرتیتر', 'signteb-medcore' ),
			'categories'  => [ 'signteb-medical' ],
			'content'     => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}},"backgroundColor":"deep-navy","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-deep-navy-background-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
<!-- wp:signteb/appointment-cta {"title":"رزرو نوبت آنلاین","subtitle":"در اسرع وقت با شما تماس می‌گیریم","theme":"dark"} /-->
</div>
<!-- /wp:group -->',
		];
	}

	// ── Pattern: Services Grid ────────────────────────────────────────────────

	private function pattern_services_grid(): array {
		return [
			'title'       => __( 'گرید خدمات ۳ ستونه', 'signteb-medcore' ),
			'description' => __( 'نمایش خدمات پزشکی در ۳ ستون', 'signteb-medcore' ),
			'categories'  => [ 'signteb-medical' ],
			'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">خدمات پزشکی ما</h2><!-- /wp:heading -->
<!-- wp:signteb/service-grid {"columns":3,"postsPerPage":6,"cardStyle":"glass"} /-->
</div>
<!-- /wp:group -->',
		];
	}

	// ── Pattern: Stats Bar ────────────────────────────────────────────────────

	private function pattern_stats_bar(): array {
		return [
			'title'       => __( 'نوار آمار و دستاوردها', 'signteb-medcore' ),
			'description' => __( 'شمارنده‌های آماری با انیمیشن', 'signteb-medcore' ),
			'categories'  => [ 'signteb-medical' ],
			'content'     => '<!-- wp:signteb/stats-counter {"theme":"dark","columns":4,"stats":[{"value":15,"suffix":"+","label":"سال تجربه"},{"value":5000,"suffix":"+","label":"بیمار درمان شده"},{"value":98,"suffix":"%","label":"رضایت بیماران"},{"value":12,"suffix":"+","label":"تخصص پزشکی"}]} /-->',
		];
	}

	// ── Pattern: FAQ Section ──────────────────────────────────────────────────

	private function pattern_faq_section(): array {
		return [
			'title'       => __( 'بخش سؤالات متداول', 'signteb-medcore' ),
			'description' => __( 'آکاردیون FAQ با Schema markup', 'signteb-medcore' ),
			'categories'  => [ 'signteb-medical' ],
			'content'     => '<!-- wp:signteb/faq-accordion {"title":"سؤالات متداول بیماران","theme":"light","generateSchema":true} /-->',
		];
	}

	// ── Pattern: Contact Section ──────────────────────────────────────────────

	private function pattern_contact_section(): array {
		return [
			'title'       => __( 'بخش تماس', 'signteb-medcore' ),
			'description' => __( 'CTA تماس با تلفن و WhatsApp', 'signteb-medcore' ),
			'categories'  => [ 'signteb-medical' ],
			'content'     => '<!-- wp:signteb/contact-cta {"title":"آماده پاسخگویی به سؤالات شما هستیم","subtitle":"تیم پزشکی ما ۲۴ ساعته در دسترس است","theme":"dark"} /-->',
		];
	}
}

new MedCore_Block_Patterns();

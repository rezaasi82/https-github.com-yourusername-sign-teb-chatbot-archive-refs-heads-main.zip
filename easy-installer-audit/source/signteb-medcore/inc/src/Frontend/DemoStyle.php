<?php
/**
 * SignTeb MedCore — کلاس body مختصِ دموی فعال
 *
 * قالب رنگ‌ها را از Design Tokens (theme_mods) می‌گیرد، اما برای تنوعِ ساختاری
 * (سبک هیرو، لهجه‌های بصری مختص هر دمو) به یک قلاب CSS نیاز داریم. این کلاس
 * دو کلاس به <body> اضافه می‌کند:
 *   - stwiz-demo--{slug}   (مثلاً stwiz-demo--cardiology)
 *   - stwiz-hero--{style}  (aurora | spotlight | diagonal | minimal)
 * که vip.css با آن‌ها به هر دمو هویتِ بصریِ مستقل می‌دهد.
 *
 * مقادیر از آپشن‌هایی می‌آیند که ویزارد هنگام ایمپورت دمو ذخیره می‌کند.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Frontend;

defined( 'ABSPATH' ) || exit;

final class DemoStyle {

	public function register(): void {
		add_filter( 'body_class', [ $this, 'add_classes' ] );
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public function add_classes( array $classes ): array {
		$demo = sanitize_html_class( (string) get_option( 'stwiz_active_demo', '' ) );
		$hero = sanitize_html_class( (string) get_option( 'stwiz_active_hero', 'aurora' ) );

		if ( '' !== $demo ) {
			$classes[] = 'stwiz-demo--' . $demo;
		}
		$classes[] = 'stwiz-hero--' . ( '' !== $hero ? $hero : 'aurora' );

		return $classes;
	}
}

<?php
/**
 * SignTeb MedCore — Performance Optimizer (فاز ۷)
 *
 * بهینه‌سازی‌های سمت‌کدِ قابل‌اندازه‌گیری و امن که مکملِ کارهای موجودِ enqueue
 * (defer، critical CSS، preload فونت، حذف jQuery) هستند:
 *  - حذف اسکریپت/استایل ایموجی وردپرس (payload اضافی در هر صفحه)
 *  - پاکسازی <head> از تگ‌های بی‌مصرف (generator, RSD, wlwmanifest, shortlink…)
 *  - حذف jQuery Migrate از فرانت
 *  - حذف اسکریپت oEmbed
 *  - استفاده از نسخه‌ی مینیفای‌شده‌ی CSS در صورت وجود (و خارج از حالت دیباگ)
 *
 * همه‌ی موارد فقط در فرانت اعمال می‌شوند و پیشخوان را دست‌نخورده می‌گذارند.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Performance;

defined( 'ABSPATH' ) || exit;

final class Optimizer {

	/** handleهای CSSِ قالب که نسخه‌ی .min.css دارند. */
	private const MINIFIABLE = [ 'stmc-main', 'stmc-components', 'stmc-rtl', 'stmc-editor' ];

	public function register(): void {
		add_action( 'init',              [ $this, 'disable_emoji' ] );
		add_action( 'after_setup_theme', [ $this, 'clean_head' ] );
		add_action( 'wp_default_scripts',[ $this, 'remove_jquery_migrate' ] );
		add_action( 'wp_footer',         [ $this, 'remove_oembed_script' ] );
		add_filter( 'style_loader_src',  [ $this, 'use_min_css' ], 10, 2 );
	}

	// ─── حذف ایموجی ─────────────────────────────────────────────────────────────

	public function disable_emoji(): void {
		remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles',     'print_emoji_styles' );
		remove_action( 'admin_print_styles',  'print_emoji_styles' );
		remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
		remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', static function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
		} );
		// حذف dns-prefetch به s.w.org برای ایموجی.
		add_filter( 'wp_resource_hints', static function ( $hints, $relation ) {
			if ( 'dns-prefetch' === $relation ) {
				$emoji = 'https://s.w.org/images/core/emoji/';
				foreach ( $hints as $k => $h ) {
					if ( is_string( $h ) && str_contains( $h, $emoji ) ) {
						unset( $hints[ $k ] );
					}
				}
			}
			return $hints;
		}, 10, 2 );
	}

	// ─── پاکسازی <head> ─────────────────────────────────────────────────────────

	public function clean_head(): void {
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
		remove_action( 'wp_head', 'feed_links_extra', 3 ); // فقط فیدهای اضافی؛ فید اصلی حفظ می‌شود
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		// حذف نسخه از تگ generator (اطلاعات نسخه‌ی وردپرس).
		add_filter( 'the_generator', '__return_empty_string' );
	}

	// ─── حذف jQuery Migrate از فرانت ────────────────────────────────────────────

	public function remove_jquery_migrate( \WP_Scripts $scripts ): void {
		if ( is_admin() ) {
			return;
		}
		if ( isset( $scripts->registered['jquery'] ) ) {
			$deps = $scripts->registered['jquery']->deps;
			$scripts->registered['jquery']->deps = array_diff( $deps, [ 'jquery-migrate' ] );
		}
	}

	// ─── حذف اسکریپت oEmbed ─────────────────────────────────────────────────────

	public function remove_oembed_script(): void {
		wp_dequeue_script( 'wp-embed' );
	}

	// ─── استفاده از CSS مینیفای‌شده ─────────────────────────────────────────────

	/**
	 * اگر نسخه‌ی .min.css موجود باشد (و حالت دیباگ فعال نباشد)، همان لود می‌شود.
	 *
	 * @param string $src    آدرس فایل استایل.
	 * @param string $handle هندلِ استایل.
	 * @return string
	 */
	public function use_min_css( string $src, string $handle ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return $src;
		}
		if ( ! in_array( $handle, self::MINIFIABLE, true ) ) {
			return $src;
		}
		if ( str_contains( $src, '.min.css' ) ) {
			return $src;
		}

		$min_src = (string) preg_replace( '/\.css(\?|$)/', '.min.css$1', $src );

		// بررسی وجود فایل مینیفای‌شده روی دیسک قبل از سوییچ (جلوگیری از ۴۰۴).
		$path = strtok( str_replace( MEDCORE_URI, '', $min_src ), '?' );
		if ( $path && is_file( MEDCORE_DIR . $path ) ) {
			return $min_src;
		}
		return $src;
	}
}

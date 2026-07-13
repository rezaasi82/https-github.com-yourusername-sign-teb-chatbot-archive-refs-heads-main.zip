<?php
/**
 * SignTeb MedCore — Elementor Compatibility Layer
 *
 * سازگاری کامل قالب با المنتور (رایگان و Pro). این ماژول اولین سرویسی است که
 * با معماری هدف (namespace + DI) ساخته شده و از طریق Container بوت می‌شود.
 *
 * مسئولیت‌ها:
 *  1. رفع تداخل حیاتی: قالب روی فرانت jQuery را dequeue می‌کند؛ المنتور به
 *     jQuery وابسته است، پس روی صفحاتی که المنتور فعال است این حذف لغو می‌شود.
 *  2. ثبت locationهای Theme Builder المنتور Pro (header/footer/single/archive).
 *  3. سازگاری عرض محتوا (full-width) و body class برای صفحات ساخته‌شده با المنتور.
 *  4. اعلام سازگاری نسخه‌ی المنتور و رفتار امن وقتی المنتور نصب نیست.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Integration;

defined( 'ABSPATH' ) || exit;

final class Elementor {

	/**
	 * ثبت همه‌ی هوک‌های سازگاری. اگر المنتور نصب نباشد، فقط فیلترهای بی‌خطر
	 * ثبت می‌شوند و هیچ رفتاری تغییر نمی‌کند (بدون هزینه‌ی اجرایی).
	 */
	public function register(): void {
		// (۱) حیاتی: جلوگیری از حذف jQuery وقتی المنتور فعال است.
		// enqueue قالب از فیلتر `stmc_remove_jquery` استفاده می‌کند؛ اینجا به‌صورت
		// تنبل (در زمان اجرای فیلتر) بررسی می‌کنیم که المنتور فعال است یا نه.
		add_filter( 'stmc_remove_jquery', [ $this, 'keep_jquery_for_elementor' ] );

		// (۲) Theme Builder المنتور Pro — ثبت locationهای اصلی قالب.
		add_action( 'elementor/theme/register_locations', [ $this, 'register_locations' ] );

		// (۳) کلاس body برای صفحات ساخته‌شده با المنتور (برای استایل‌دهی سازگار).
		add_filter( 'body_class', [ $this, 'body_class' ] );

		// (۴) عرض محتوا برای full-width المنتور — روی صفحات المنتوری محدودیت 760px قالب برداشته می‌شود.
		add_action( 'template_redirect', [ $this, 'maybe_relax_content_width' ] );
	}

	// ─── تشخیص ─────────────────────────────────────────────────────────────────

	/**
	 * آیا افزونه‌ی المنتور نصب و بارگذاری شده است؟
	 */
	public function is_active(): bool {
		return did_action( 'elementor/loaded' ) > 0 || class_exists( '\\Elementor\\Plugin' );
	}

	/**
	 * آیا المنتور Pro فعال است؟
	 */
	public function is_pro_active(): bool {
		return did_action( 'elementor_pro/init' ) > 0 || class_exists( '\\ElementorPro\\Plugin' );
	}

	/**
	 * آیا این پست/صفحه با المنتور ساخته شده است؟
	 */
	public function is_built_with_elementor( ?int $post_id = null ): bool {
		$post_id = $post_id ?: get_the_ID();
		if ( ! $post_id ) {
			return false;
		}
		// روش رسمی المنتور در صورت در دسترس بودن.
		if ( $this->is_active() && class_exists( '\\Elementor\\Plugin' ) ) {
			$plugin = \Elementor\Plugin::instance();
			if ( isset( $plugin->documents ) ) {
				$doc = $plugin->documents->get( $post_id );
				if ( $doc && method_exists( $doc, 'is_built_with_elementor' ) ) {
					return (bool) $doc->is_built_with_elementor();
				}
			}
		}
		// fallback بر اساس متای المنتور.
		return 'builtWith' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	// ─── هوک‌ها ─────────────────────────────────────────────────────────────────

	/**
	 * وقتی المنتور فعال است، مقدار فیلتر حذف jQuery را به false تغییر می‌دهد.
	 *
	 * @param bool $remove مقدار فعلی فیلتر (پیش‌فرض قالب: true).
	 * @return bool false اگر المنتور فعال باشد، وگرنه مقدار اصلی.
	 */
	public function keep_jquery_for_elementor( $remove ): bool {
		if ( $this->is_active() ) {
			return false;
		}
		return (bool) $remove;
	}

	/**
	 * ثبت locationهای اصلی قالب برای Theme Builder المنتور Pro.
	 *
	 * @param mixed $manager نمونه‌ی Locations_Manager المنتور.
	 */
	public function register_locations( $manager ): void {
		if ( is_object( $manager ) && method_exists( $manager, 'register_all_core_locations' ) ) {
			$manager->register_all_core_locations();
		}
	}

	/**
	 * افزودن کلاس‌های body برای سازگاری استایل با صفحات المنتوری.
	 *
	 * @param array $classes کلاس‌های فعلی.
	 * @return array
	 */
	public function body_class( array $classes ): array {
		if ( $this->is_active() ) {
			$classes[] = 'medcore-elementor-ready';
			if ( is_singular() && $this->is_built_with_elementor() ) {
				$classes[] = 'medcore-elementor-page';
			}
		}
		return $classes;
	}

	/**
	 * روی صفحاتی که با المنتور ساخته شده‌اند، محدودیت عرض محتوای قالب (۷۶۰px)
	 * را برمی‌دارد تا چیدمان full-width المنتور درست کار کند.
	 */
	public function maybe_relax_content_width(): void {
		if ( is_singular() && $this->is_built_with_elementor() ) {
			$GLOBALS['content_width'] = 1600;
		}
	}
}

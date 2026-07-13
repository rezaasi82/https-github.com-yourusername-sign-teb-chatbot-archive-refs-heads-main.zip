<?php
/**
 * SignTeb MedCore — Elementor Pro Theme Builder Bridge (FSE)
 *
 * پلی که هدر/فوتر ساخته‌شده در Theme Builder المنتور Pro را روی این قالبِ FSE
 * جایگزین هدر/فوترِ بلوکی (template-part) می‌کند.
 *
 * چرا این‌طور: در قالب کلاسیک، هدر با `elementor_theme_do_location('header')`
 * داخل header.php تزریق می‌شود. قالب بلوکی header.php ندارد و هدر را از طریق
 * بلوک `core/template-part` رندر می‌کند. این ماژول در لحظه‌ی رندرِ همان بلوک،
 * بررسی می‌کند که آیا المنتور Pro برای این location قالب فعالی دارد؛ اگر بله،
 * خروجی المنتور جایگزین template-partِ قالب می‌شود (بدون تکرار)، وگرنه هدر/فوترِ
 * خودِ قالب دست‌نخورده باقی می‌ماند (fallback امن).
 *
 * از تابع عمومی و پایدار `elementor_theme_do_location()` استفاده می‌شود، نه APIهای
 * داخلی و شکننده‌ی المنتور.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Integration;

defined( 'ABSPATH' ) || exit;

final class ElementorLocations {

	/**
	 * ثبت هوک. فیلتر همیشه اضافه می‌شود اما خودش را در نبود المنتور Pro سریع
	 * کنار می‌کشد؛ پس هزینه‌ی اجرایی وقتی المنتور Pro نیست، عملاً صفر است.
	 */
	public function register(): void {
		add_filter( 'render_block', [ $this, 'maybe_swap_template_part' ], 10, 2 );
	}

	/**
	 * در رندر بلوک template-part، هدر/فوتر FSE را با نسخه‌ی المنتور جایگزین کن
	 * اگر Theme Builder برای آن location قالب فعالی داشته باشد.
	 *
	 * @param string $block_content HTML رندرشده‌ی بلوک.
	 * @param array  $block         داده‌ی بلوک (blockName، attrs، …).
	 * @return string
	 */
	public function maybe_swap_template_part( string $block_content, array $block ): string {
		// فقط بلوک‌های template-part، و فقط در فرانت.
		if ( is_admin() || ( $block['blockName'] ?? '' ) !== 'core/template-part' ) {
			return $block_content;
		}

		// تابع المنتور Pro باید موجود باشد.
		if ( ! function_exists( 'elementor_theme_do_location' ) ) {
			return $block_content;
		}

		$slug     = (string) ( $block['attrs']['slug'] ?? '' );
		$location = $this->slug_to_location( $slug );
		if ( null === $location ) {
			return $block_content;
		}

		// خروجی location المنتور را بافر می‌کنیم تا اگر واقعاً چیزی چاپ کرد،
		// جایگزین template-part شود؛ در غیر این صورت هدر/فوتر قالب حفظ می‌شود.
		ob_start();
		$did_location = elementor_theme_do_location( $location );
		$output       = (string) ob_get_clean();

		if ( $did_location && '' !== trim( $output ) ) {
			return $output;
		}

		return $block_content;
	}

	/**
	 * نگاشت slug تمپلیت‌پارت قالب به location المنتور.
	 *
	 * قالب دارای پارت‌های header / header-transparent / footer است؛ همه‌ی
	 * انواع هدر به location «header» و فوتر به «footer» نگاشت می‌شوند.
	 */
	private function slug_to_location( string $slug ): ?string {
		$slug = strtolower( $slug );
		if ( str_contains( $slug, 'header' ) ) {
			return 'header';
		}
		if ( str_contains( $slug, 'footer' ) ) {
			return 'footer';
		}
		return null;
	}
}

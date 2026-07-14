<?php
/**
 * SignTeb MedCore — Elementor Pro Theme Builder Bridge (FSE)
 *
 * پلی که قالب‌های Theme Builder المنتور Pro را روی این قالبِ FSE اعمال می‌کند:
 *   - Header / Footer  → جایگزینِ بلوکِ core/template-part
 *   - Single (پروفایل پزشک/خدمت/…) → جایگزینِ بلوکِ core/post-content
 *   - Archive (آرشیو پزشکان/تاکسونومی) → جایگزینِ حلقه‌ی اصلیِ core/query
 *
 * چرا این‌طور: قالب بلوکی header.php/single.php کلاسیک ندارد و محتوا را از طریق
 * بلوک‌ها رندر می‌کند. این پل در لحظه‌ی رندرِ همان بلوک بررسی می‌کند که آیا
 * المنتور Pro برای آن location قالب فعالی دارد؛ اگر بله، خروجی المنتور جایگزین
 * می‌شود، وگرنه رفتار پیش‌فرضِ قالب دست‌نخورده می‌ماند (fallback امن). از تابع
 * عمومی و پایدار elementor_theme_do_location() استفاده می‌شود.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Integration;

defined( 'ABSPATH' ) || exit;

final class ElementorLocations {

	public function register(): void {
		add_filter( 'render_block', [ $this, 'maybe_swap' ], 10, 2 );
	}

	/**
	 * جایگزینی بلوک با خروجی Theme Builder المنتور در صورت وجود قالب فعال.
	 *
	 * @param string $block_content HTML رندرشده‌ی بلوک.
	 * @param array  $block         داده‌ی بلوک.
	 * @return string
	 */
	public function maybe_swap( string $block_content, array $block ): string {
		if ( is_admin() || ! function_exists( 'elementor_theme_do_location' ) ) {
			return $block_content;
		}

		$name = $block['blockName'] ?? '';

		// ── Header / Footer ──────────────────────────────────────────────────
		if ( 'core/template-part' === $name ) {
			$location = $this->slug_to_location( (string) ( $block['attrs']['slug'] ?? '' ) );
			return $location ? $this->location_or( $location, $block_content ) : $block_content;
		}

		// ── Single (محتوای تک‌نوشته) ─────────────────────────────────────────
		if ( 'core/post-content' === $name && is_singular() ) {
			return $this->location_or( 'single', $block_content );
		}

		// ── Archive (فقط حلقه‌ی اصلی: query با inherit=true) ──────────────────
		if ( 'core/query' === $name
			&& ! empty( $block['attrs']['query']['inherit'] )
			&& $this->is_archive_context() ) {
			return $this->location_or( 'archive', $block_content );
		}

		return $block_content;
	}

	/**
	 * اگر المنتور برای این location قالبی فعال داشته باشد، خروجی آن را برمی‌گرداند؛
	 * وگرنه محتوای پیش‌فرضِ قالب.
	 */
	private function location_or( string $location, string $fallback ): string {
		ob_start();
		$did = elementor_theme_do_location( $location );
		$out = (string) ob_get_clean();

		return ( $did && '' !== trim( $out ) ) ? $out : $fallback;
	}

	/** نگاشت slug تمپلیت‌پارت به location هدر/فوتر. */
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

	/** آیا صفحه‌ی جاری یک آرشیو/فهرست است؟ */
	private function is_archive_context(): bool {
		return is_archive() || is_home() || is_search() || is_post_type_archive() || is_tax();
	}
}

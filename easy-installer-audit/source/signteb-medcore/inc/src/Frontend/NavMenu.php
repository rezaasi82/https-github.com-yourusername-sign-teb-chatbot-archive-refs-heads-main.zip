<?php
/**
 * SignTeb MedCore — رندر پایدار منوی اصلی هدر
 *
 * چرا این کلاس وجود دارد: پارت هدر از بلوک core/navigation با "ref":0 استفاده
 * می‌کرد. ref نامعتبر یعنی بلوک به مسیر fallback هسته می‌رود — مسیری شکننده که
 * در عمل دو خرابی واقعی تولید کرد: منوی بدون استایل/پراکنده، و در بدترین حالت
 * بلعیده‌شدن کل محتوای صفحه داخل <nav> (همپوشانی فاجعه‌بار سکشن‌ها روی دموها).
 *
 * راه‌حل: خروجی همان بلوک navigation هدر در لحظه‌ی رندر با یک منوی کلاسیک
 * PHP-رندر (لوکیشن primary — همان که ویزارد می‌سازد و ست می‌کند) جایگزین
 * می‌شود. مارک‌آپ کاملاً تحت کنترل قالب است، روی هر نسخه‌ی وردپرس یکسان کار
 * می‌کند و استایل VIP مستقیماً روی آن اعمال می‌شود. اگر منویی ست نشده باشد،
 * فهرست صفحات به‌عنوان fallback امن رندر می‌شود.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Frontend;

defined( 'ABSPATH' ) || exit;

final class NavMenu {

	public function register(): void {
		add_filter( 'render_block', [ $this, 'maybe_swap' ], 9, 2 );
	}

	/**
	 * جایگزینی بلوک ناوبریِ هدر (className: site-header__nav) با منوی primary.
	 *
	 * @param string $block_content HTML رندرشده‌ی بلوک.
	 * @param array  $block         داده‌ی بلوک.
	 */
	public function maybe_swap( string $block_content, array $block ): string {
		if ( is_admin() ) {
			return $block_content;
		}

		if ( 'core/navigation' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		$class = (string) ( $block['attrs']['className'] ?? '' );
		if ( ! str_contains( $class, 'site-header__nav' ) ) {
			return $block_content; // فقط منوی هدر — ناوبری‌های دیگر دست‌نخورده
		}

		$menu = wp_nav_menu( [
			'theme_location' => 'primary',
			'container'      => 'nav',
			'container_class'=> 'site-header__nav stmc-nav',
			'menu_class'     => 'stmc-nav__list',
			'depth'          => 2,
			'fallback_cb'    => false,
			'echo'           => false,
		] );

		if ( is_string( $menu ) && '' !== trim( $menu ) ) {
			return $menu;
		}

		// fallback امن: فهرست صفحات (وقتی هنوز منویی ساخته/ست نشده است).
		$pages = wp_list_pages( [
			'title_li' => '',
			'echo'     => false,
			'depth'    => 1,
			'number'   => 7,
		] );

		return $pages
			? '<nav class="site-header__nav stmc-nav" aria-label="' . esc_attr__( 'منوی اصلی', 'signteb-medcore' ) . '"><ul class="stmc-nav__list">' . $pages . '</ul></nav>'
			: '';
	}
}

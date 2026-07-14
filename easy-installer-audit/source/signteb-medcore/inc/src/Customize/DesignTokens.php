<?php
/**
 * SignTeb MedCore — Design Tokens (فاز ۵)
 *
 * منبعِ واحدِ حقیقتِ سفارشی‌سازی. توکن‌ها (رنگ، تایپوگرافی، دکمه، چیدمان) را
 * تعریف می‌کند، مقادیر کاربر را از theme_mods می‌خواند، و آن‌ها را به‌صورت
 * CSS Variables زنده در <head> خروجی می‌دهد تا تغییرات واقعاً روی سایت اعمال
 * شوند (نقطه‌ضعف قبلی: رنگ‌ها ذخیره می‌شدند ولی هیچ‌جا اعمال نمی‌شدند).
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Customize;

defined( 'ABSPATH' ) || exit;

final class DesignTokens {

	/**
	 * تعریف توکن‌های قابل‌کنترل. منبع واحدِ Customizer و خروجی CSS.
	 *
	 * هر توکن: id (کلید theme_mod)، label، type، default، section، و نگاشت
	 * به CSS variable(ها). type: color | select | range.
	 *
	 * @return array<string,array>
	 */
	public function tokens(): array {
		return [
			// ── رنگ‌ها ──────────────────────────────────────────────────────────
			'stmc_color_primary' => [
				'label' => __( 'رنگ اصلی برند', 'signteb-medcore' ),
				'type' => 'color', 'default' => '#1a56db', 'section' => 'stmc_sec_colors',
				'css' => '--stmc-blue', 'shades' => true, // مشتقات dark/light تولید می‌شود
			],
			'stmc_color_accent' => [
				'label' => __( 'رنگ تأکید (طلایی/VIP)', 'signteb-medcore' ),
				'type' => 'color', 'default' => '#C9A84C', 'section' => 'stmc_sec_colors',
				'css' => '--stmc-gold',
			],
			'stmc_color_dark' => [
				'label' => __( 'رنگ تیره (سرمه‌ای)', 'signteb-medcore' ),
				'type' => 'color', 'default' => '#0f172a', 'section' => 'stmc_sec_colors',
				'css' => '--stmc-navy',
			],
			// ── تایپوگرافی ──────────────────────────────────────────────────────
			'stmc_font_family' => [
				'label' => __( 'فونت اصلی', 'signteb-medcore' ),
				'type' => 'select', 'default' => 'vazir', 'section' => 'stmc_sec_typography',
				'css' => '--stmc-font-fa',
				'choices' => [
					'vazir'  => 'Vazirmatn (پیش‌فرض)',
					'tahoma' => 'Tahoma',
					'system' => 'System / سیستمی',
				],
				'map' => [
					'vazir'  => "'Vazirmatn', 'Tahoma', sans-serif",
					'tahoma' => "'Tahoma', sans-serif",
					'system' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, sans-serif",
				],
			],
			// ── دکمه‌ها ─────────────────────────────────────────────────────────
			'stmc_button_radius' => [
				'label' => __( 'گردی گوشه‌ی دکمه‌ها (px)', 'signteb-medcore' ),
				'type' => 'range', 'default' => 10, 'section' => 'stmc_sec_buttons',
				'css' => '--stmc-radius-md', 'unit' => 'px', 'min' => 0, 'max' => 40,
			],
			// ── چیدمان ──────────────────────────────────────────────────────────
			'stmc_container_width' => [
				'label' => __( 'عرض کانتینر سایت (px)', 'signteb-medcore' ),
				'type' => 'range', 'default' => 1240, 'section' => 'stmc_sec_layout',
				'css' => '--stmc-wide-width', 'unit' => 'px', 'min' => 960, 'max' => 1600,
			],
			'stmc_header_height' => [
				'label' => __( 'ارتفاع هدر (px)', 'signteb-medcore' ),
				'type' => 'range', 'default' => 72, 'section' => 'stmc_sec_layout',
				'css' => '--stmc-header-height', 'unit' => 'px', 'min' => 56, 'max' => 120,
			],
		];
	}

	/** سکشن‌های Customizer (برچسب + اولویت). */
	public function sections(): array {
		return [
			'stmc_sec_colors'     => [ 'title' => __( 'رنگ‌ها', 'signteb-medcore' ), 'priority' => 10 ],
			'stmc_sec_typography' => [ 'title' => __( 'تایپوگرافی', 'signteb-medcore' ), 'priority' => 20 ],
			'stmc_sec_buttons'    => [ 'title' => __( 'دکمه‌ها', 'signteb-medcore' ), 'priority' => 30 ],
			'stmc_sec_layout'     => [ 'title' => __( 'چیدمان', 'signteb-medcore' ), 'priority' => 40 ],
		];
	}

	/**
	 * ساخت نگاشتِ CSS variable → مقدار، از روی theme_mods کاربر.
	 *
	 * @return array<string,string>
	 */
	public function css_variables(): array {
		$vars = [];
		foreach ( $this->tokens() as $id => $t ) {
			$value = get_theme_mod( $id, $t['default'] );

			switch ( $t['type'] ) {
				case 'color':
					$hex = $this->valid_hex( (string) $value, (string) $t['default'] );
					$vars[ $t['css'] ] = $hex;
					if ( ! empty( $t['shades'] ) ) {
						$vars['--stmc-blue-dark']  = $this->shade( $hex, -0.28 );
						$vars['--stmc-blue-light'] = $this->shade( $hex, 0.82 );
					}
					break;

				case 'select':
					$vars[ $t['css'] ] = $t['map'][ $value ] ?? $t['map'][ $t['default'] ];
					break;

				case 'range':
					$num = (int) $value;
					$num = max( (int) $t['min'], min( (int) $t['max'], $num ) );
					$vars[ $t['css'] ] = $num . ( $t['unit'] ?? '' );
					break;
			}
		}
		return $vars;
	}

	/**
	 * خروجی بلوک <style> شاملِ override متغیرها در <head>.
	 * روی اولویت بالای wp_head اجرا می‌شود تا بعد از استایل اصلی قالب بیاید و
	 * برنده شود.
	 */
	public function output_css(): void {
		$vars = $this->css_variables();
		if ( empty( $vars ) ) {
			return;
		}
		$css = ':root{';
		foreach ( $vars as $name => $val ) {
			$css .= $name . ':' . $val . ';';
		}
		$css .= '}';

		printf(
			'<style id="stmc-design-tokens">%s</style>' . "\n",
			$css // متغیرها از توکن‌های امن (hex/عدد/رشته‌ی از پیش تعریف‌شده) ساخته می‌شوند
		);
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function valid_hex( string $hex, string $fallback ): string {
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex ) ? $hex : $fallback;
	}

	/**
	 * تیره/روشن کردن یک رنگ hex.
	 *
	 * @param string $hex     رنگ مبدأ (#rrggbb یا #rgb).
	 * @param float  $percent بین -1 (کاملاً تیره) تا 1 (کاملاً روشن).
	 */
	public function shade( string $hex, float $percent ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$mix = static function ( int $c ) use ( $percent ): int {
			$target = $percent < 0 ? 0 : 255;
			$p = abs( $percent );
			return (int) round( $c + ( $target - $c ) * $p );
		};

		return sprintf( '#%02x%02x%02x', $mix( $r ), $mix( $g ), $mix( $b ) );
	}
}

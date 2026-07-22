<?php
/**
 * SignTeb MedCore — هاب ارتباطی شناور (VIP)
 *
 * دکمه‌های شناور تماس/واتس‌اپ/رزرو نوبت در گوشه‌ی صفحه — مسیر بدون اصطکاک به
 * اقدام (اصل Conversion در طراحی VIP پزشکی). شماره‌ها از اطلاعاتی که کاربر در
 * ویزارد راه‌اندازی وارد کرده (option: stwiz_step_contact) خوانده می‌شوند؛ اگر
 * چیزی ثبت نشده باشد فقط دکمه‌ی رزرو نوبت نمایش داده می‌شود.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Frontend;

defined( 'ABSPATH' ) || exit;

final class FloatingHub {

	public function register(): void {
		add_action( 'wp_footer', [ $this, 'render' ], 20 );
	}

	public function render(): void {
		if ( is_admin() ) {
			return;
		}

		$contact  = (array) get_option( 'stwiz_step_contact', [] );
		$phone    = trim( (string) ( $contact['phone'] ?? '' ) );
		$whatsapp = preg_replace( '/[^0-9+]/', '', (string) ( $contact['whatsapp'] ?? '' ) ) ?? '';

		/** آدرس صفحه‌ی رزرو نوبت — با فیلتر قابل تغییر است. */
		$booking_url = apply_filters( 'stmc_booking_url', home_url( '/appointment/' ) );

		?>
		<div class="stmc-hub" aria-label="<?php esc_attr_e( 'راه‌های ارتباط سریع', 'signteb-medcore' ); ?>">
			<?php if ( $whatsapp ) : ?>
			<a class="stmc-hub__btn stmc-hub__btn--wa" target="_blank" rel="noopener"
				href="<?php echo esc_url( 'https://wa.me/' . ltrim( $whatsapp, '+' ) ); ?>"
				aria-label="<?php esc_attr_e( 'گفتگو در واتس‌اپ', 'signteb-medcore' ); ?>">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.5 0 1.47 1.07 2.9 1.22 3.1.15.2 2.1 3.2 5.1 4.49.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.57-.09 1.76-.72 2.01-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35zM12.05 21.8h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.85 9.85 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88a9.83 9.83 0 0 1 6.99 2.9 9.82 9.82 0 0 1 2.9 7 9.9 9.9 0 0 1-9.9 9.87zm8.42-18.3A11.8 11.8 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.14 1.59 5.94L.07 24l6.3-1.65a11.87 11.87 0 0 0 5.68 1.44h.01c6.55 0 11.89-5.34 11.89-11.9 0-3.18-1.24-6.16-3.48-8.4z"/></svg>
			</a>
			<?php endif; ?>

			<?php if ( $phone ) : ?>
			<a class="stmc-hub__btn stmc-hub__btn--tel"
				href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"
				aria-label="<?php esc_attr_e( 'تماس تلفنی', 'signteb-medcore' ); ?>">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
			</a>
			<?php endif; ?>

			<a class="stmc-hub__btn stmc-hub__btn--book" href="<?php echo esc_url( $booking_url ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
				<span><?php esc_html_e( 'رزرو نوبت', 'signteb-medcore' ); ?></span>
			</a>
		</div>
		<?php
	}
}

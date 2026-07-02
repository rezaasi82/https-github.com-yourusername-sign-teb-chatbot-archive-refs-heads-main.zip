<?php
/**
 * SignTeb Medical Core — Appointment Form
 *
 * Shortcode: [stmc_appointment doctor_id="123"]
 * Block: signteb/appointment-cta
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Appointment;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Form {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register_shortcode' );
	}

	public function register_shortcode(): void {
		add_shortcode( 'stmc_appointment', [ $this, 'render_shortcode' ] );
	}

	public function render_shortcode( array $atts ): string {
		$atts = shortcode_atts( [
			'doctor_id' => get_the_ID(),
			'title'     => __( 'رزرو نوبت', STMC_TEXT ),
			'theme'     => 'glass', // glass | light | dark
		], $atts, 'stmc_appointment' );

		$doctor_id   = absint( $atts['doctor_id'] );
		$doctor_name = $doctor_id ? get_the_title( $doctor_id ) : '';
		$nonce       = wp_create_nonce( 'stmc_appointment_nonce' );

		ob_start();
		?>
		<div class="stmc-appointment-form stmc-appointment-form--<?php echo esc_attr( $atts['theme'] ); ?>" id="stmc-appointment-<?php echo esc_attr( $doctor_id ); ?>">

			<h3 class="stmc-appointment-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>

			<?php if ( $doctor_name ) : ?>
				<p class="stmc-appointment-form__doctor">
					<?php printf( esc_html__( 'نوبت‌دهی نزد: %s', STMC_TEXT ), '<strong>' . esc_html( $doctor_name ) . '</strong>' ); ?>
				</p>
			<?php endif; ?>

			<div class="stmc-appointment-form__messages" aria-live="polite" hidden></div>

			<?php if ( $doctor_id ) : ?>
			<div class="stmc-appointment-form__notice">
				<?php esc_html_e( '💡 برای انتخاب تاریخ و ساعت دقیق نوبت، از بلوک «Appointment CTA» در صفحه‌سازِ Gutenberg استفاده کنید که تقویم زمان‌های خالی پزشک را نمایش می‌دهد.', STMC_TEXT ); ?>
			</div>
			<?php endif; ?>

			<div class="stmc-appointment-form__fields">
				<input type="hidden" name="stmc_appointment_nonce" value="<?php echo esc_attr( $nonce ); ?>">
				<input type="hidden" name="stmc_doctor_id" value="<?php echo esc_attr( $doctor_id ); ?>">
				<input type="hidden" name="action" value="stmc_submit_appointment">

				<div class="stmc-form-row">
					<div class="stmc-form-group">
						<label for="stmc-appt-name-<?php echo esc_attr( $doctor_id ); ?>"><?php esc_html_e( 'نام و نام خانوادگی *', STMC_TEXT ); ?></label>
						<input
							type="text"
							id="stmc-appt-name-<?php echo esc_attr( $doctor_id ); ?>"
							name="stmc_name"
							placeholder="<?php esc_attr_e( 'نام کامل خود را وارد کنید', STMC_TEXT ); ?>"
							required
							autocomplete="name"
						>
					</div>

					<div class="stmc-form-group">
						<label for="stmc-appt-phone-<?php echo esc_attr( $doctor_id ); ?>"><?php esc_html_e( 'شماره تماس *', STMC_TEXT ); ?></label>
						<input
							type="tel"
							id="stmc-appt-phone-<?php echo esc_attr( $doctor_id ); ?>"
							name="stmc_phone"
							placeholder="<?php esc_attr_e( '09xxxxxxxxx', STMC_TEXT ); ?>"
							required
							autocomplete="tel"
							pattern="[0-9+\-\s]{10,15}"
						>
					</div>
				</div>

				<div class="stmc-form-row">
					<div class="stmc-form-group">
						<label for="stmc-appt-specialty-<?php echo esc_attr( $doctor_id ); ?>"><?php esc_html_e( 'تخصص مورد نظر', STMC_TEXT ); ?></label>
						<select id="stmc-appt-specialty-<?php echo esc_attr( $doctor_id ); ?>" name="stmc_specialty">
							<option value=""><?php esc_html_e( 'انتخاب کنید...', STMC_TEXT ); ?></option>
							<?php
							$terms = get_terms( [ 'taxonomy' => 'specialty', 'hide_empty' => false ] );
							if ( $terms && ! is_wp_error( $terms ) ) {
								foreach ( $terms as $term ) {
									printf( '<option value="%s">%s</option>', esc_attr( $term->name ), esc_html( $term->name ) );
								}
							}
							?>
						</select>
					</div>

					<div class="stmc-form-group">
						<label for="stmc-appt-email-<?php echo esc_attr( $doctor_id ); ?>"><?php esc_html_e( 'ایمیل (اختیاری)', STMC_TEXT ); ?></label>
						<input
							type="email"
							id="stmc-appt-email-<?php echo esc_attr( $doctor_id ); ?>"
							name="stmc_email"
							placeholder="<?php esc_attr_e( 'example@email.com', STMC_TEXT ); ?>"
							autocomplete="email"
						>
					</div>
				</div>

				<div class="stmc-form-group">
					<label for="stmc-appt-message-<?php echo esc_attr( $doctor_id ); ?>"><?php esc_html_e( 'شرح مختصر بیماری', STMC_TEXT ); ?></label>
					<textarea
						id="stmc-appt-message-<?php echo esc_attr( $doctor_id ); ?>"
						name="stmc_message"
						rows="3"
						placeholder="<?php esc_attr_e( 'مشکل یا سؤال خود را بنویسید...', STMC_TEXT ); ?>"
					></textarea>
				</div>

				<div class="stmc-form-footer">
					<button
						type="button"
						class="stmc-btn stmc-btn-gold stmc-btn-lg stmc-btn-full stmc-appt-submit"
						data-form="stmc-appointment-<?php echo esc_attr( $doctor_id ); ?>"
					>
						<span class="stmc-btn-text"><?php esc_html_e( '📅 ثبت درخواست نوبت', STMC_TEXT ); ?></span>
						<span class="stmc-btn-loading" hidden><?php esc_html_e( 'در حال ارسال...', STMC_TEXT ); ?></span>
					</button>

					<p class="stmc-form-privacy">
						<?php esc_html_e( '🔒 اطلاعات شما کاملاً محرمانه است', STMC_TEXT ); ?>
					</p>
				</div>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}

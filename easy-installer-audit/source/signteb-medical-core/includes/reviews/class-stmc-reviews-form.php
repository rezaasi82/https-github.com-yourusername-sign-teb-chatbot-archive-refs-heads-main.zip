<?php
/**
 * SignTeb Medical Core — Review Form (Public)
 *
 * Shortcode: [stmc_review_form doctor_id="123"]
 *
 * فرم ثبت نظر عمومی برای بیماران — مستقیماً از سمت سایت،
 * بدون نیاز به ورود به پیشخوان. نتیجه با وضعیت pending ذخیره
 * می‌شود و تا تأیید منشی/پزشک عمومی نمایش داده نمی‌شود.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Reviews;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Form {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register_shortcode' );
	}

	public function register_shortcode(): void {
		add_shortcode( 'stmc_review_form', [ $this, 'render_shortcode' ] );
	}

	public function render_shortcode( array $atts ): string {
		$atts = shortcode_atts( [
			'doctor_id' => get_the_ID(),
			'title'     => __( 'تجربه خود را با ما به اشتراک بگذارید', STMC_TEXT ),
		], $atts, 'stmc_review_form' );

		$doctor_id   = absint( $atts['doctor_id'] );
		$doctor_name = $doctor_id ? get_the_title( $doctor_id ) : '';
		$nonce       = wp_create_nonce( 'stmc_review_nonce' );
		$uid         = 'stmc-review-' . $doctor_id;

		ob_start();
		?>
		<div class="stmc-review-form" id="<?php echo esc_attr( $uid ); ?>" data-block="stmc-review-form">

			<h3 class="stmc-review-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>

			<?php if ( $doctor_name ) : ?>
				<p class="stmc-review-form__doctor">
					<?php printf( esc_html__( 'نظر شما درباره: %s', STMC_TEXT ), '<strong>' . esc_html( $doctor_name ) . '</strong>' ); ?>
				</p>
			<?php endif; ?>

			<div class="stmc-review-form__success" hidden role="status" aria-live="polite">
				<div class="stmc-review-form__success-icon">✅</div>
				<p><?php esc_html_e( 'با تشکر! نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.', STMC_TEXT ); ?></p>
			</div>

			<div class="stmc-review-form__error" hidden role="alert" aria-live="assertive"></div>

			<div class="stmc-review-form__fields">
				<input type="hidden" name="stmc_review_nonce" value="<?php echo esc_attr( $nonce ); ?>">
				<input type="hidden" name="stmc_doctor_id" value="<?php echo esc_attr( $doctor_id ); ?>">
				<input type="hidden" name="action" value="stmc_submit_review">
				<!-- Honeypot ضد اسپم — کاربر واقعی این فیلد را نمی‌بیند و خالی می‌ماند -->
				<input type="text" name="stmc_website" class="stmc-review-form__honeypot" tabindex="-1" autocomplete="off">

				<div class="stmc-form-group">
					<label for="<?php echo esc_attr( $uid ); ?>-rating"><?php esc_html_e( 'امتیاز شما', STMC_TEXT ); ?> *</label>
					<div class="stmc-rating-input" data-name="stmc_rating">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<button type="button" class="stmc-rating-star" data-value="<?php echo $i; ?>" aria-label="<?php echo esc_attr( sprintf( __( '%d ستاره', STMC_TEXT ), $i ) ); ?>">★</button>
						<?php endfor; ?>
						<input type="hidden" name="stmc_rating" value="5">
					</div>
				</div>

				<div class="stmc-form-row">
					<div class="stmc-form-group">
						<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'نام شما', STMC_TEXT ); ?> *</label>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="stmc_reviewer_name" placeholder="<?php esc_attr_e( 'مثال: م. احمدی', STMC_TEXT ); ?>" required>
					</div>
					<div class="stmc-form-group">
						<label for="<?php echo esc_attr( $uid ); ?>-city"><?php esc_html_e( 'شهر (اختیاری)', STMC_TEXT ); ?></label>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-city" name="stmc_reviewer_city" placeholder="<?php esc_attr_e( 'مثال: تهران', STMC_TEXT ); ?>">
					</div>
				</div>

				<div class="stmc-form-group">
					<label for="<?php echo esc_attr( $uid ); ?>-treatment"><?php esc_html_e( 'نوع درمان (اختیاری)', STMC_TEXT ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-treatment" name="stmc_treatment" placeholder="<?php esc_attr_e( 'مثال: جراحی زانو', STMC_TEXT ); ?>">
				</div>

				<div class="stmc-form-group">
					<label for="<?php echo esc_attr( $uid ); ?>-content"><?php esc_html_e( 'متن نظر', STMC_TEXT ); ?> *</label>
					<textarea id="<?php echo esc_attr( $uid ); ?>-content" name="stmc_content" rows="4" placeholder="<?php esc_attr_e( 'تجربه خود را بنویسید...', STMC_TEXT ); ?>" required></textarea>
				</div>

				<button type="button" class="stmc-btn stmc-btn-primary stmc-review-submit" data-form="<?php echo esc_attr( $uid ); ?>">
					<span class="stmc-btn-text"><?php esc_html_e( 'ثبت نظر', STMC_TEXT ); ?></span>
					<span class="stmc-btn-loading" hidden><?php esc_html_e( 'در حال ارسال...', STMC_TEXT ); ?></span>
				</button>

				<p class="stmc-form-privacy">
					🔒 <?php esc_html_e( 'نظر شما پس از بررسی منتشر می‌شود.', STMC_TEXT ); ?>
				</p>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}

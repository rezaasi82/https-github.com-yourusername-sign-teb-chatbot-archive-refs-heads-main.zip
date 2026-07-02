<?php
/**
 * SignTeb Blocks — Appointment CTA: Server-Side Render
 */

defined( 'ABSPATH' ) || exit;

$doctor_id   = (int) ( $attributes['doctorId'] ?? 0 );
$title       = esc_html( $attributes['title']   ?? __( 'رزرو نوبت آنلاین', 'signteb-blocks' ) );
$subtitle    = esc_html( $attributes['subtitle'] ?? '' );
$theme       = esc_attr( $attributes['theme']   ?? 'dark' );
$success_msg = esc_html( $attributes['successMsg'] ?? '' );
$show_phone  = (bool) ( $attributes['showPhone'] ?? true );
$nonce       = wp_create_nonce( 'stmc_appointment_nonce' );

$doctor_name = $doctor_id ? get_the_title( $doctor_id ) : '';
$doctor_phone = $doctor_id ? get_post_meta( $doctor_id, 'stmc_doctor_phone', true ) : '';
$doctor_wa    = $doctor_id ? get_post_meta( $doctor_id, 'stmc_doctor_whatsapp', true ) : '';

// Specialty terms for dropdown
$specialties = get_terms( [ 'taxonomy' => 'specialty', 'hide_empty' => false ] );

$uid = 'stmb-appt-' . wp_unique_id();
?>
<section
  class="stmb-appointment-section stmb-appointment-section--<?php echo $theme; ?>"
  id="<?php echo esc_attr( $uid ); ?>"
  data-block="signteb/appointment-cta"
  aria-label="<?php echo $title; ?>"
>
  <!-- Decorative gradient bar -->
  <div class="stmb-appt-gradient" aria-hidden="true"></div>

  <div class="stmb-appt-inner">

    <!-- Header -->
    <div class="stmb-appt-header">
      <div class="stmb-appt-icon" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div>
        <h2 class="stmb-appt-title"><?php echo $title; ?></h2>
        <?php if ( $subtitle ) : ?>
          <p class="stmb-appt-subtitle"><?php echo $subtitle; ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form card -->
    <div class="stmb-appt-card" role="form" aria-label="<?php echo $title; ?>">

      <!-- Success message (hidden by default) -->
      <div class="stmb-appt-success" hidden role="status" aria-live="polite">
        <div class="stmb-appt-success__icon">✅</div>
        <p class="stmb-appt-success__msg"><?php echo $success_msg ?: esc_html__( 'نوبت شما ثبت شد. به زودی با شما تماس می‌گیریم.', 'signteb-blocks' ); ?></p>
      </div>

      <!-- Error message -->
      <div class="stmb-appt-error" hidden role="alert" aria-live="assertive"></div>

      <?php if ( $doctor_id ) : ?>
      <!-- ── Step 1: انتخاب تاریخ و ساعت (تقویم واقعی پزشک) ── -->
      <div class="stmb-appt-datetime-step" id="<?php echo esc_attr( $uid ); ?>-datetime">
        <div class="stmb-appt-step-label">
          <span class="stmb-step-num">۱</span>
          <?php esc_html_e( 'انتخاب تاریخ و ساعت', 'signteb-blocks' ); ?>
        </div>

        <div class="stmb-calendar" data-doctor="<?php echo esc_attr( $doctor_id ); ?>">
          <div class="stmb-calendar__header">
            <button type="button" class="stmb-cal-nav" data-dir="-1" aria-label="<?php esc_attr_e( 'ماه قبل', 'signteb-blocks' ); ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span class="stmb-cal-month-label"></span>
            <button type="button" class="stmb-cal-nav" data-dir="1" aria-label="<?php esc_attr_e( 'ماه بعد', 'signteb-blocks' ); ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
          </div>
          <div class="stmb-calendar__weekdays">
            <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
          </div>
          <div class="stmb-calendar__days" aria-live="polite"></div>
          <div class="stmb-calendar__loading" hidden><?php esc_html_e( 'در حال بارگذاری...', 'signteb-blocks' ); ?></div>
        </div>

        <div class="stmb-slots-wrap" hidden>
          <div class="stmb-slots-label"><?php esc_html_e( 'ساعات خالی:', 'signteb-blocks' ); ?> <strong class="stmb-selected-date-label"></strong></div>
          <div class="stmb-slots-grid"></div>
          <p class="stmb-no-slots" hidden><?php esc_html_e( 'برای این روز ظرفیت خالی وجود ندارد. روز دیگری را انتخاب کنید.', 'signteb-blocks' ); ?></p>
        </div>

        <input type="hidden" name="stmc_appt_date" class="stmb-appt-date-input">
        <input type="hidden" name="stmc_appt_time" class="stmb-appt-time-input">

        <div class="stmb-selected-summary" hidden>
          ✅ <?php esc_html_e( 'زمان انتخابی:', 'signteb-blocks' ); ?> <strong class="stmb-summary-text"></strong>
          <button type="button" class="stmb-change-time"><?php esc_html_e( 'تغییر', 'signteb-blocks' ); ?></button>
        </div>
      </div>

      <div class="stmb-appt-step-label stmb-appt-step-2" id="<?php echo esc_attr( $uid ); ?>-step2-label" hidden>
        <span class="stmb-step-num">۲</span>
        <?php esc_html_e( 'اطلاعات شما', 'signteb-blocks' ); ?>
      </div>
      <?php endif; ?>

      <!-- Form fields -->
      <div class="stmb-appt-form-wrap" <?php echo $doctor_id ? 'hidden' : ''; ?>>
        <input type="hidden" name="action"                  value="stmc_submit_appointment">
        <input type="hidden" name="stmc_appointment_nonce"  value="<?php echo esc_attr( $nonce ); ?>">
        <input type="hidden" name="stmc_doctor_id"          value="<?php echo esc_attr( $doctor_id ); ?>">

        <div class="stmb-appt-row">
          <!-- Name -->
          <div class="stmb-appt-field">
            <label class="stmb-appt-label" for="<?php echo esc_attr( $uid ); ?>-name">
              <?php esc_html_e( 'نام و نام خانوادگی', 'signteb-blocks' ); ?>
              <span class="stmb-appt-required" aria-hidden="true">*</span>
            </label>
            <input
              type="text"
              id="<?php echo esc_attr( $uid ); ?>-name"
              name="stmc_name"
              class="stmb-appt-input"
              placeholder="<?php esc_attr_e( 'نام کامل خود را وارد کنید', 'signteb-blocks' ); ?>"
              required
              autocomplete="name"
            >
          </div>

          <!-- Phone -->
          <div class="stmb-appt-field">
            <label class="stmb-appt-label" for="<?php echo esc_attr( $uid ); ?>-phone">
              <?php esc_html_e( 'شماره تماس', 'signteb-blocks' ); ?>
              <span class="stmb-appt-required" aria-hidden="true">*</span>
            </label>
            <input
              type="tel"
              id="<?php echo esc_attr( $uid ); ?>-phone"
              name="stmc_phone"
              class="stmb-appt-input"
              placeholder="۰۹۱xxxxxxxxx"
              required
              autocomplete="tel"
              pattern="[0-9+\-\s]{10,14}"
            >
          </div>
        </div>

        <div class="stmb-appt-row">
          <!-- Specialty -->
          <div class="stmb-appt-field">
            <label class="stmb-appt-label" for="<?php echo esc_attr( $uid ); ?>-specialty">
              <?php esc_html_e( 'تخصص مورد نظر', 'signteb-blocks' ); ?>
            </label>
            <select id="<?php echo esc_attr( $uid ); ?>-specialty" name="stmc_specialty" class="stmb-appt-input stmb-appt-select">
              <option value=""><?php esc_html_e( '— انتخاب کنید —', 'signteb-blocks' ); ?></option>
              <?php if ( ! is_wp_error( $specialties ) ) : ?>
                <?php foreach ( $specialties as $term ) : ?>
                  <option value="<?php echo esc_attr( $term->name ); ?>"><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <!-- Email -->
          <div class="stmb-appt-field">
            <label class="stmb-appt-label" for="<?php echo esc_attr( $uid ); ?>-email">
              <?php esc_html_e( 'ایمیل (اختیاری)', 'signteb-blocks' ); ?>
            </label>
            <input
              type="email"
              id="<?php echo esc_attr( $uid ); ?>-email"
              name="stmc_email"
              class="stmb-appt-input"
              placeholder="example@email.com"
              autocomplete="email"
            >
          </div>
        </div>

        <!-- Message -->
        <div class="stmb-appt-field stmb-appt-field--full">
          <label class="stmb-appt-label" for="<?php echo esc_attr( $uid ); ?>-message">
            <?php esc_html_e( 'شرح مشکل یا سؤال', 'signteb-blocks' ); ?>
          </label>
          <textarea
            id="<?php echo esc_attr( $uid ); ?>-message"
            name="stmc_message"
            class="stmb-appt-input stmb-appt-textarea"
            rows="3"
            placeholder="<?php esc_attr_e( 'بیماری، علائم یا سؤال خود را بنویسید...', 'signteb-blocks' ); ?>"
          ></textarea>
        </div>

        <!-- Submit -->
        <div class="stmb-appt-footer">
          <button
            type="button"
            class="stmb-appt-submit stmb-btn stmb-btn--gold stmb-btn--lg"
            data-form="<?php echo esc_attr( $uid ); ?>"
            data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
          >
            <span class="stmb-appt-submit__text">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?php esc_html_e( 'ثبت درخواست نوبت', 'signteb-blocks' ); ?>
            </span>
            <span class="stmb-appt-submit__loading" hidden>
              <svg class="stmb-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <?php esc_html_e( 'در حال ارسال...', 'signteb-blocks' ); ?>
            </span>
          </button>

          <p class="stmb-appt-privacy">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <?php esc_html_e( 'اطلاعات شما کاملاً محرمانه و امن است', 'signteb-blocks' ); ?>
          </p>
        </div>

      </div><!-- /.form-wrap -->

    </div><!-- /.card -->

    <!-- Doctor contact strip (if phone/wa available) -->
    <?php if ( $show_phone && ( $doctor_phone || $doctor_wa ) ) : ?>
    <div class="stmb-appt-contact-strip">
      <span class="stmb-appt-contact-strip__label">
        <?php esc_html_e( 'یا مستقیم تماس بگیرید:', 'signteb-blocks' ); ?>
      </span>
      <?php if ( $doctor_phone ) : ?>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $doctor_phone ) ); ?>" class="stmb-appt-contact-link">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.64A2 2 0 012 .18h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14.92z"/></svg>
          <?php echo esc_html( $doctor_phone ); ?>
        </a>
      <?php endif; ?>
      <?php if ( $doctor_wa ) :
        $wa = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $doctor_wa );
      ?>
        <a href="<?php echo esc_url( $wa ); ?>" class="stmb-appt-contact-link stmb-appt-contact-link--wa" target="_blank" rel="noopener noreferrer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
          WhatsApp
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /.inner -->
</section>
<?php

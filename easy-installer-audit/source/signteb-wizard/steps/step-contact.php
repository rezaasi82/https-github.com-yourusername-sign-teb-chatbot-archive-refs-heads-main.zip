<?php
defined('ABSPATH') || exit;
$saved = (array) get_option('stwiz_step_contact', []);
?>
<div class="stwiz-step-form" data-step="contact">
  <h2><?php esc_html_e('کانال‌های ارتباطی', STWIZ_TEXT); ?></h2>
  <p class="stwiz-step-desc"><?php esc_html_e('این اطلاعات در دکمه‌های WhatsApp، هدر، فوتر، و Schema social profiles استفاده می‌شود.', STWIZ_TEXT); ?></p>

  <div class="stwiz-form-grid">
    <div class="stwiz-field">
      <label>
        <span class="stwiz-channel-icon">📱</span>
        <?php esc_html_e('شماره WhatsApp', STWIZ_TEXT); ?>
      </label>
      <input type="tel" name="whatsapp" value="<?php echo esc_attr($saved['whatsapp'] ?? get_option('stmc_clinic_whatsapp','')); ?>" placeholder="989191182649">
      <small><?php esc_html_e('با کد کشور، بدون +', STWIZ_TEXT); ?></small>
    </div>

    <div class="stwiz-field">
      <label>
        <span class="stwiz-channel-icon">☎️</span>
        <?php esc_html_e('تلفن مستقیم', STWIZ_TEXT); ?>
      </label>
      <input type="tel" name="phone" value="<?php echo esc_attr($saved['phone'] ?? get_option('stmc_clinic_phone','')); ?>" placeholder="02112345678">
    </div>

    <div class="stwiz-field">
      <label>
        <span class="stwiz-channel-icon">📧</span>
        <?php esc_html_e('ایمیل نوبت‌دهی', STWIZ_TEXT); ?>
      </label>
      <input type="email" name="appointment_email" value="<?php echo esc_attr($saved['appointment_email'] ?? get_option('stmc_appointment_email','')); ?>" placeholder="appointments@clinic.ir">
      <small><?php esc_html_e('ایمیل دریافت نوبت‌های جدید', STWIZ_TEXT); ?></small>
    </div>

    <div class="stwiz-field">
      <label>
        <span class="stwiz-channel-icon">📸</span>
        Instagram
      </label>
      <input type="url" name="instagram" value="<?php echo esc_attr($saved['instagram'] ?? get_option('stmc_social_instagram','')); ?>" placeholder="https://instagram.com/...">
    </div>

    <div class="stwiz-field">
      <label>
        <span class="stwiz-channel-icon">▶️</span>
        YouTube
      </label>
      <input type="url" name="youtube" value="<?php echo esc_attr($saved['youtube'] ?? get_option('stmc_social_youtube','')); ?>" placeholder="https://youtube.com/@...">
    </div>

    <div class="stwiz-field">
      <label>
        <span class="stwiz-channel-icon">💼</span>
        LinkedIn
      </label>
      <input type="url" name="linkedin" value="<?php echo esc_attr($saved['linkedin'] ?? get_option('stmc_social_linkedin','')); ?>" placeholder="https://linkedin.com/in/...">
    </div>
  </div>
</div>

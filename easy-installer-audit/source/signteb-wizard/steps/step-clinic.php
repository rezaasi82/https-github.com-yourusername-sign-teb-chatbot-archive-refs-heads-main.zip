<?php
defined('ABSPATH') || exit;
$saved = (array) get_option('stwiz_step_clinic', []);
?>
<div class="stwiz-step-form" data-step="clinic">
  <h2><?php esc_html_e('اطلاعات کلینیک', STWIZ_TEXT); ?></h2>
  <p class="stwiz-step-desc"><?php esc_html_e('این اطلاعات در Schema JSON-LD، NAP (Name-Address-Phone)، و Google My Business استفاده می‌شود.', STWIZ_TEXT); ?></p>

  <div class="stwiz-form-grid">
    <div class="stwiz-field stwiz-field--full">
      <label><?php esc_html_e('نام رسمی مطب / کلینیک', STWIZ_TEXT); ?></label>
      <input type="text" name="clinic_name" value="<?php echo esc_attr($saved['clinic_name'] ?? get_option('stmc_clinic_name','')); ?>" placeholder="<?php esc_attr_e('نام کامل کلینیک / مطب', STWIZ_TEXT); ?>">
    </div>

    <div class="stwiz-field">
      <label><?php esc_html_e('تلفن', STWIZ_TEXT); ?></label>
      <input type="tel" name="phone" value="<?php echo esc_attr($saved['phone'] ?? get_option('stmc_clinic_phone','')); ?>" placeholder="۰۲۱۱۲۳۴۵۶۷۸">
    </div>

    <div class="stwiz-field">
      <label><?php esc_html_e('ایمیل مدیریت', STWIZ_TEXT); ?></label>
      <input type="email" name="email" value="<?php echo esc_attr($saved['email'] ?? get_option('stmc_clinic_email','')); ?>" placeholder="info@clinic.ir">
    </div>

    <div class="stwiz-field">
      <label><?php esc_html_e('کشور', STWIZ_TEXT); ?></label>
      <select name="country">
        <option value="IR" <?php selected($saved['country'] ?? get_option('stmc_country_code','IR'), 'IR'); ?>>ایران</option>
        <option value="AE" <?php selected($saved['country'] ?? '', 'AE'); ?>>امارات</option>
        <option value="TR" <?php selected($saved['country'] ?? '', 'TR'); ?>>ترکیه</option>
        <option value="DE" <?php selected($saved['country'] ?? '', 'DE'); ?>>آلمان</option>
      </select>
    </div>

    <div class="stwiz-field">
      <label><?php esc_html_e('شهر', STWIZ_TEXT); ?></label>
      <input type="text" name="city" value="<?php echo esc_attr($saved['city'] ?? get_option('stmc_geo_placename','')); ?>" placeholder="<?php esc_attr_e('مثال: تهران', STWIZ_TEXT); ?>">
    </div>

    <div class="stwiz-field stwiz-field--full">
      <label><?php esc_html_e('آدرس کامل', STWIZ_TEXT); ?></label>
      <input type="text" name="address" value="<?php echo esc_attr($saved['address'] ?? get_option('stmc_clinic_address','')); ?>" placeholder="<?php esc_attr_e('خیابان، کوچه، پلاک', STWIZ_TEXT); ?>">
    </div>
  </div>

  <div class="stwiz-info-box">
    <strong>💡 SEO Local:</strong>
    <?php esc_html_e('اطلاعات کلینیک به صورت MedicalClinic Schema در head هر صفحه قرار می‌گیرد.', STWIZ_TEXT); ?>
  </div>
</div>

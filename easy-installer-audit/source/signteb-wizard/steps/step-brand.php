<?php
defined('ABSPATH') || exit;
$saved = (array) get_option('stwiz_step_brand', []);
?>
<div class="stwiz-step-form" data-step="brand">
  <h2><?php esc_html_e('هویت برند پزشکی شما', STWIZ_TEXT); ?></h2>
  <p class="stwiz-step-desc"><?php esc_html_e('این اطلاعات در header سایت، Schema JSON-LD، و متا تگ‌ها نمایش داده می‌شود.', STWIZ_TEXT); ?></p>

  <div class="stwiz-form-grid">
    <div class="stwiz-field stwiz-field--full">
      <label for="stwiz-site-name"><?php esc_html_e('نام سایت / مطب / کلینیک', STWIZ_TEXT); ?> <span>*</span></label>
      <input type="text" id="stwiz-site-name" name="site_name" value="<?php echo esc_attr($saved['site_name'] ?? get_option('blogname')); ?>" placeholder="<?php esc_attr_e('مثال: کلینیک تخصصی دکتر احمدی', STWIZ_TEXT); ?>" required>
    </div>

    <div class="stwiz-field stwiz-field--full">
      <label for="stwiz-tagline"><?php esc_html_e('توضیح کوتاه (Tagline)', STWIZ_TEXT); ?></label>
      <input type="text" id="stwiz-tagline" name="tagline" value="<?php echo esc_attr($saved['tagline'] ?? get_option('blogdescription')); ?>" placeholder="<?php esc_attr_e('مثال: متخصص ارتوپدی با ۱۵ سال تجربه', STWIZ_TEXT); ?>">
    </div>

    <div class="stwiz-field">
      <label for="stwiz-primary-color"><?php esc_html_e('رنگ اصلی برند', STWIZ_TEXT); ?></label>
      <div class="stwiz-color-pick">
        <input type="color" id="stwiz-primary-color" name="primary_color" value="<?php echo esc_attr($saved['primary_color'] ?? '#1a56db'); ?>">
        <span class="stwiz-color-preview"></span>
      </div>
    </div>

    <div class="stwiz-field">
      <label for="stwiz-market"><?php esc_html_e('بازار هدف', STWIZ_TEXT); ?></label>
      <select id="stwiz-market" name="market">
        <option value="ir" <?php selected($saved['market'] ?? get_option('stmc_market','ir'), 'ir'); ?>><?php esc_html_e('ایران (فارسی)', STWIZ_TEXT); ?></option>
        <option value="ae" <?php selected($saved['market'] ?? '', 'ae'); ?>><?php esc_html_e('امارات / خلیج (EN + AR)', STWIZ_TEXT); ?></option>
        <option value="multi" <?php selected($saved['market'] ?? '', 'multi'); ?>><?php esc_html_e('چندزبانه (FA + AR + EN)', STWIZ_TEXT); ?></option>
      </select>
    </div>
  </div>

  <div class="stwiz-preview-box">
    <div class="stwiz-preview-box__label"><?php esc_html_e('پیش‌نمایش زنده', STWIZ_TEXT); ?></div>
    <div class="stwiz-brand-preview">
      <div class="stwiz-brand-preview__color" id="preview-color"></div>
      <div>
        <div class="stwiz-brand-preview__name" id="preview-name"><?php echo esc_html(get_option('blogname')); ?></div>
        <div class="stwiz-brand-preview__tagline" id="preview-tagline"><?php echo esc_html(get_option('blogdescription')); ?></div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const nameIn  = document.getElementById('stwiz-site-name');
  const tagIn   = document.getElementById('stwiz-tagline');
  const colorIn = document.getElementById('stwiz-primary-color');
  const pName   = document.getElementById('preview-name');
  const pTag    = document.getElementById('preview-tagline');
  const pColor  = document.getElementById('preview-color');

  function update() {
    if (pName)  pName.textContent  = nameIn?.value  || '';
    if (pTag)   pTag.textContent   = tagIn?.value   || '';
    if (pColor && colorIn) pColor.style.background = colorIn.value;
  }

  nameIn?.addEventListener('input',  update);
  tagIn?.addEventListener('input',   update);
  colorIn?.addEventListener('input', update);
  update();
})();
</script>

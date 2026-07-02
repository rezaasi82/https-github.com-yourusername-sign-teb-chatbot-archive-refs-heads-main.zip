<?php
defined('ABSPATH') || exit;
$demo_type    = get_option('stwiz_demo_installed', '');
$clinic_name  = get_option('stmc_clinic_name', get_option('blogname',''));
$completed    = (array) get_option('stwiz_completed_steps', []);
$total_steps  = 5; // excluding finish
$done_count   = count(array_intersect(['welcome','brand','clinic','contact','demo'], $completed));
?>
<div class="stwiz-finish">
  <div class="stwiz-finish__hero">
    <div class="stwiz-finish__emoji">🎉</div>
    <h1><?php printf(esc_html__('%s آماده است!', STWIZ_TEXT), esc_html($clinic_name)); ?></h1>
    <p><?php esc_html_e('راه‌اندازی با موفقیت انجام شد. وب‌سایت پزشکی شما آماده است.', STWIZ_TEXT); ?></p>
  </div>

  <!-- Completion stats -->
  <div class="stwiz-finish__stats">
    <div class="stwiz-finish__stat">
      <div class="stwiz-finish__stat-val"><?php echo $done_count; ?>/<?php echo $total_steps; ?></div>
      <div class="stwiz-finish__stat-lbl"><?php esc_html_e('مرحله تکمیل شده', STWIZ_TEXT); ?></div>
    </div>
    <?php if ($demo_type) : ?>
    <div class="stwiz-finish__stat">
      <div class="stwiz-finish__stat-val">✅</div>
      <div class="stwiz-finish__stat-lbl"><?php esc_html_e('دمو نصب شده', STWIZ_TEXT); ?></div>
    </div>
    <?php endif; ?>
    <div class="stwiz-finish__stat">
      <div class="stwiz-finish__stat-val">
        <?php echo is_plugin_active('signteb-medical-core/signteb-medical-core.php') ? '✅' : '⚠️'; ?>
      </div>
      <div class="stwiz-finish__stat-lbl">Medical Core</div>
    </div>
  </div>

  <!-- Next steps -->
  <div class="stwiz-finish__next">
    <h3><?php esc_html_e('مراحل بعدی', STWIZ_TEXT); ?></h3>
    <div class="stwiz-next-actions">
      <a href="<?php echo esc_url(admin_url('post-new.php?post_type=doctor')); ?>" class="stwiz-next-card">
        <span class="stwiz-next-card__icon">👨‍⚕️</span>
        <div>
          <strong><?php esc_html_e('افزودن پزشک', STWIZ_TEXT); ?></strong>
          <p><?php esc_html_e('اولین پروفایل پزشک را بسازید', STWIZ_TEXT); ?></p>
        </div>
        <span>←</span>
      </a>
      <a href="<?php echo esc_url(admin_url('post-new.php?post_type=medical-service')); ?>" class="stwiz-next-card">
        <span class="stwiz-next-card__icon">💊</span>
        <div>
          <strong><?php esc_html_e('افزودن خدمات', STWIZ_TEXT); ?></strong>
          <p><?php esc_html_e('خدمات پزشکی کلینیک را معرفی کنید', STWIZ_TEXT); ?></p>
        </div>
        <span>←</span>
      </a>
      <a href="<?php echo esc_url(admin_url('admin.php?page=stmc-settings')); ?>" class="stwiz-next-card">
        <span class="stwiz-next-card__icon">⚙️</span>
        <div>
          <strong><?php esc_html_e('تنظیمات SEO', STWIZ_TEXT); ?></strong>
          <p><?php esc_html_e('Schema، Local SEO، و hreflang را تنظیم کنید', STWIZ_TEXT); ?></p>
        </div>
        <span>←</span>
      </a>
      <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="stwiz-next-card stwiz-next-card--primary">
        <span class="stwiz-next-card__icon">👁️</span>
        <div>
          <strong><?php esc_html_e('مشاهده سایت', STWIZ_TEXT); ?></strong>
          <p><?php esc_html_e('نتیجه نهایی را ببینید', STWIZ_TEXT); ?></p>
        </div>
        <span>←</span>
      </a>
    </div>
  </div>

  <div class="stwiz-finish__brand">
    <p><?php esc_html_e('ساخته شده با ❤️ توسط', STWIZ_TEXT); ?> <a href="https://signteb.com" target="_blank" rel="noopener">SignTeb</a></p>
    <button type="button" class="stwiz-reset-btn" id="stwiz-reset">
      <?php esc_html_e('راه‌اندازی مجدد wizard', STWIZ_TEXT); ?>
    </button>
  </div>
</div>

<script>
document.getElementById('stwiz-reset')?.addEventListener('click', function() {
  if (!confirm('<?php echo esc_js( __( 'آیا مطمئن هستید؟ تمام تنظیمات wizard پاک می‌شود.', STWIZ_TEXT ) ); ?>')) return;
  fetch(stWizData.ajaxUrl, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'stwiz_reset', nonce:stWizData.nonce})
  }).then(r=>r.json()).then(d => { if(d.success && d.data?.redirect) location.href = d.data.redirect; });
});
</script>

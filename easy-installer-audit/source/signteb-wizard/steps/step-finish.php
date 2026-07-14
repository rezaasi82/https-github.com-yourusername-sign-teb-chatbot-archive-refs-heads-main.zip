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

  <!-- ── منطقه‌ی خطر: حذف کامل داده‌های دمو ─────────────────────────────── -->
  <div class="stwiz-info-box" id="stwiz-danger" style="margin-top:2rem;text-align:right;border-color:rgba(248,113,113,0.4);background:rgba(248,113,113,0.06);">
    <strong style="color:#f87171;">⚠️ <?php esc_html_e('حذف داده‌های دمو', STWIZ_TEXT); ?></strong>
    <p style="margin:0.5rem 0 0.75rem;font-size:0.8125rem;">
      <?php esc_html_e('این کار تمام محتوایی که ویزارد ساخته را برای همیشه حذف می‌کند: پزشکان، خدمات، سؤالات متداول، مقالات و صفحات دمو، تصاویر آپلودشده، نظرات نمونه، منو، و تنظیمات دمو. فقط محتوای ساخته‌شده توسط ویزارد حذف می‌شود، نه محتوای خودتان. این عمل قابل بازگشت نیست.', STWIZ_TEXT); ?>
    </p>
    <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;">
      <input type="checkbox" id="stwiz-uninstall-confirm">
      <?php esc_html_e('بله، مطمئنم و می‌خواهم داده‌های دمو حذف شوند.', STWIZ_TEXT); ?>
    </label>
    <button type="button" id="stwiz-uninstall" class="stwiz-btn" disabled
      style="margin-top:0.875rem;background:#dc2626;color:#fff;opacity:0.5;">
      🗑️ <?php esc_html_e('حذف داده‌های دمو', STWIZ_TEXT); ?>
    </button>
    <p id="stwiz-uninstall-result" style="margin-top:0.75rem;font-size:0.8125rem;"></p>
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

// حذف داده‌های دمو — دکمه فقط با تیک تأیید فعال می‌شود (صفحه‌ی تأیید).
(function(){
  var chk = document.getElementById('stwiz-uninstall-confirm');
  var btn = document.getElementById('stwiz-uninstall');
  var res = document.getElementById('stwiz-uninstall-result');
  if(!chk||!btn) return;
  chk.addEventListener('change', function(){
    btn.disabled = !chk.checked;
    btn.style.opacity = chk.checked ? '1' : '0.5';
  });
  btn.addEventListener('click', function(){
    if(!chk.checked) return;
    if(!confirm('<?php echo esc_js( __( 'آخرین تأیید: تمام داده‌های دمو برای همیشه حذف شوند؟', STWIZ_TEXT ) ); ?>')) return;
    btn.disabled = true; btn.style.opacity='0.5';
    res.textContent = '<?php echo esc_js( __( 'در حال حذف...', STWIZ_TEXT ) ); ?>';
    fetch(stWizData.ajaxUrl, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action:'stwiz_uninstall', nonce:stWizData.nonce, confirm:'yes'})
    }).then(r=>r.json()).then(function(d){
      res.style.color = d.success ? '#10b981' : '#f87171';
      res.textContent = (d.success?'✅ ':'❌ ') + (d.data && d.data.message ? d.data.message : '');
      chk.checked=false;
    }).catch(function(){ res.style.color='#f87171'; res.textContent='❌ خطای شبکه'; });
  });
})();
</script>

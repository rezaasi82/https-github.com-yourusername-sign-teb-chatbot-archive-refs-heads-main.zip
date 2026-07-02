<?php
defined('ABSPATH') || exit;
$installed = get_option('stwiz_demo_installed', '');
$demos = [
  'solo-doctor' => [
    'icon'  => '👨‍⚕️',
    'title' => 'پزشک منفرد',
    'desc'  => 'ایده‌آل برای پروفایل یک پزشک متخصص — صفحه Hero، خدمات، نوبت‌دهی',
    'pages' => '۵ صفحه',
    'lang'  => 'فارسی',
    'color' => '#1a56db',
  ],
  'multi-clinic' => [
    'icon'  => '🏥',
    'title' => 'کلینیک چندتخصصی',
    'desc'  => 'برای کلینیک با چندین پزشک — آرشیو پزشکان، خدمات گروهی، نوبت‌دهی مرکزی',
    'pages' => '۷ صفحه',
    'lang'  => 'فارسی',
    'color' => '#059669',
  ],
  'medical-tourism' => [
    'icon'  => '✈️',
    'title' => 'گردشگری پزشکی',
    'desc'  => 'برای جذب بیماران بین‌المللی — سه زبانه، Medical Tourism Funnel، ترکیه / دبی / ایران',
    'pages' => '۸ صفحه',
    'lang'  => 'EN + AR + FA',
    'color' => '#C9A84C',
  ],
];
?>
<div class="stwiz-demo-step" data-step="demo">
  <h2><?php esc_html_e('انتخاب دمو محتوا', STWIZ_TEXT); ?></h2>
  <p class="stwiz-step-desc"><?php esc_html_e('یک قالب دمو انتخاب کنید. صفحات، منوها، و محتوای نمونه به صورت خودکار ایجاد می‌شوند.', STWIZ_TEXT); ?></p>

  <div class="stwiz-demo-grid">
    <?php foreach ($demos as $key => $demo) :
      $is_installed = ($installed === $key);
    ?>
    <div class="stwiz-demo-card <?php echo $is_installed ? 'is-installed' : ''; ?>" data-demo="<?php echo esc_attr($key); ?>">
      <div class="stwiz-demo-card__accent" style="background:<?php echo esc_attr($demo['color']); ?>"></div>
      <div class="stwiz-demo-card__icon"><?php echo $demo['icon']; ?></div>
      <h3 class="stwiz-demo-card__title"><?php echo esc_html($demo['title']); ?></h3>
      <p class="stwiz-demo-card__desc"><?php echo esc_html($demo['desc']); ?></p>
      <div class="stwiz-demo-card__meta">
        <span><?php echo esc_html($demo['pages']); ?></span>
        <span><?php echo esc_html($demo['lang']); ?></span>
      </div>
      <button
        type="button"
        class="stwiz-btn stwiz-demo-install-btn <?php echo $is_installed ? 'stwiz-btn--ghost' : 'stwiz-btn--primary'; ?>"
        data-demo="<?php echo esc_attr($key); ?>"
        <?php echo $is_installed ? 'disabled' : ''; ?>
      >
        <?php if ($is_installed) : ?>
          ✅ <?php esc_html_e('نصب شده', STWIZ_TEXT); ?>
        <?php else : ?>
          <?php esc_html_e('نصب این دمو', STWIZ_TEXT); ?>
        <?php endif; ?>
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="stwiz-demo-status" id="demo-status" hidden>
    <div class="stwiz-demo-progress">
      <div class="stwiz-spinner-lg" aria-hidden="true"></div>
      <p id="demo-status-msg"><?php esc_html_e('در حال نصب دمو...', STWIZ_TEXT); ?></p>
    </div>
  </div>

  <p class="stwiz-demo-skip">
    <?php esc_html_e('ترجیح می‌دهید بدون دمو شروع کنید؟', STWIZ_TEXT); ?>
    <strong><?php esc_html_e('این مرحله اختیاری است.', STWIZ_TEXT); ?></strong>
  </p>
</div>

<script>
(function() {
  document.querySelectorAll('.stwiz-demo-install-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const demo    = btn.dataset.demo;
      const status  = document.getElementById('demo-status');
      const msg     = document.getElementById('demo-status-msg');

      // Show progress
      document.querySelectorAll('.stwiz-demo-card').forEach(c => c.style.opacity='0.5');
      if (status)  status.hidden = false;

      fetch(stWizData.ajaxUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'stwiz_import_demo',
          nonce:   stWizData.nonce,
          demo:    demo
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          if (msg) msg.textContent = '✅ دمو با موفقیت نصب شد!';
          btn.textContent = '✅ نصب شده';
          btn.disabled    = true;
          document.querySelectorAll('.stwiz-demo-card').forEach(c => c.style.opacity='1');
        } else {
          if (msg) msg.textContent = '❌ خطا: ' + (data.data?.message || 'نامشخص');
          document.querySelectorAll('.stwiz-demo-card').forEach(c => c.style.opacity='1');
        }
      })
      .catch(() => {
        if (msg) msg.textContent = '❌ خطای شبکه';
      });
    });
  });
})();
</script>
